<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Gmail — Google OAuth2 + Gmail API integration.
 *
 * Flow:
 *   1. Admin enters Client ID + Secret in Settings page.
 *   2. Clicks "Koble til Gmail" → redirected to Google consent screen.
 *   3. Google redirects back to Settings page with ?code=…&state=…
 *   4. handle_callback() exchanges code for access + refresh tokens.
 *   5. ajax_get_emails() fetches last N emails for a given address.
 */
class Edifice_Gmail {

    const OPT_CREDS  = 'edifice_gmail_credentials'; // ['client_id', 'client_secret']
    const OPT_TOKENS = 'edifice_gmail_tokens';       // ['access_token', 'refresh_token', 'expires_at']

    // ── OAuth helpers ──────────────────────────────────────────────────────

    public static function redirect_uri(): string {
        return admin_url('admin.php?page=edifice-settings&_gmail_callback=1');
    }

    public static function get_auth_url(): string {
        $creds = get_option(self::OPT_CREDS, []);
        if (empty($creds['client_id'])) return '';
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $creds['client_id'],
            'redirect_uri'  => self::redirect_uri(),
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/gmail.readonly',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce('edifice_gmail_oauth'),
        ]);
    }

    public static function is_connected(): bool {
        $t = get_option(self::OPT_TOKENS, []);
        return !empty($t['access_token']);
    }

    // ── Token exchange ─────────────────────────────────────────────────────

    public static function handle_callback(string $code, string $state): bool {
        if (!wp_verify_nonce($state, 'edifice_gmail_oauth')) return false;
        $creds = get_option(self::OPT_CREDS, []);
        $resp  = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $creds['client_id']     ?? '',
                'client_secret' => $creds['client_secret'] ?? '',
                'redirect_uri'  => self::redirect_uri(),
                'grant_type'    => 'authorization_code',
            ],
        ]);
        if (is_wp_error($resp)) return false;
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['access_token'])) return false;
        update_option(self::OPT_TOKENS, [
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_at'    => time() + intval($data['expires_in'] ?? 3600) - 60,
        ]);
        return true;
    }

    public static function disconnect(): void {
        delete_option(self::OPT_TOKENS);
    }

    /**
     * Verifiser at Gmail API faktisk fungerer for denne tilkoblingen.
     * En gyldig OAuth-token kan eksistere selv om Gmail API ikke er
     * aktivert i Google Cloud-prosjektet — dette tester den faktiske
     * API-en med et lett kall til /users/me/profile.
     *
     * Returnerer:
     *   ['ok' => true,  'email' => 'arnstein@…', 'messages_total' => N]
     *   ['ok' => false, 'error' => '...', 'reason' => '...',
     *    'activation_url' => '...', 'detail' => mixed]
     *
     * Spesielle 'reason'-verdier:
     *   'no_token'             — ingen access_token lagret
     *   'gmail_api_disabled'   — 403 SERVICE_DISABLED / accessNotConfigured
     *   'invalid_credentials'  — 401, må koble til på nytt
     *   'http_error'           — annen non-200 respons
     *   'network_error'        — wp_remote_get returnerte WP_Error
     */
    public static function verify_api_access(): array {
        $token = self::get_access_token();
        if (!$token) {
            return [
                'ok'     => false,
                'reason' => 'no_token',
                'error'  => 'Ingen gyldig access token. Koble til Gmail på nytt.',
            ];
        }

        $resp = wp_remote_get('https://gmail.googleapis.com/gmail/v1/users/me/profile', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 8,
        ]);

        if (is_wp_error($resp)) {
            return [
                'ok'     => false,
                'reason' => 'network_error',
                'error'  => 'Kunne ikke nå Gmail API: ' . $resp->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code === 200) {
            return [
                'ok'             => true,
                'email'          => $body['emailAddress']  ?? '',
                'messages_total' => (int) ($body['messagesTotal'] ?? 0),
            ];
        }

        // 401 — token-problem
        if ($code === 401) {
            return [
                'ok'     => false,
                'reason' => 'invalid_credentials',
                'error'  => 'Tokenet ble avvist (401). Koble til Gmail på nytt.',
                'detail' => $body['error'] ?? null,
            ];
        }

        // 403 — sjekk om det er fordi Gmail API ikke er aktivert
        if ($code === 403) {
            $api_disabled   = false;
            $activation_url = '';
            foreach (($body['error']['details'] ?? []) as $d) {
                $reason   = $d['reason'] ?? '';
                $metadata = $d['metadata'] ?? [];
                if ($reason === 'SERVICE_DISABLED' || !empty($metadata['activationUrl'])) {
                    $api_disabled   = true;
                    $activation_url = $metadata['activationUrl'] ?? '';
                    break;
                }
            }
            // Fallback — sjekk top-level errors-array (eldre API-respons)
            foreach (($body['error']['errors'] ?? []) as $e) {
                if (($e['reason'] ?? '') === 'accessNotConfigured') {
                    $api_disabled = true;
                }
            }

            if ($api_disabled) {
                return [
                    'ok'             => false,
                    'reason'         => 'gmail_api_disabled',
                    'error'          => 'Gmail API er ikke aktivert i Google Cloud-prosjektet ditt. Du må aktivere det før integrasjonen kan hente e-poster.',
                    'activation_url' => $activation_url,
                    'detail'         => $body['error'] ?? null,
                ];
            }

            return [
                'ok'     => false,
                'reason' => 'http_error',
                'error'  => '403 Forbidden: ' . ($body['error']['message'] ?? 'Ingen detaljer'),
                'detail' => $body['error'] ?? null,
            ];
        }

        return [
            'ok'     => false,
            'reason' => 'http_error',
            'error'  => 'HTTP ' . $code . ': ' . ($body['error']['message'] ?? 'Ingen detaljer'),
            'detail' => $body['error'] ?? null,
        ];
    }

    // ── Access token (with auto-refresh) ───────────────────────────────────

    public static function get_access_token(): string {
        $t = get_option(self::OPT_TOKENS, []);
        if (empty($t['access_token'])) return '';
        if (time() < intval($t['expires_at'] ?? 0)) return $t['access_token'];

        // Refresh
        if (empty($t['refresh_token'])) return '';
        $creds = get_option(self::OPT_CREDS, []);
        $resp  = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'refresh_token' => $t['refresh_token'],
                'client_id'     => $creds['client_id']     ?? '',
                'client_secret' => $creds['client_secret'] ?? '',
                'grant_type'    => 'refresh_token',
            ],
        ]);
        if (is_wp_error($resp)) return '';
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['access_token'])) return '';
        $t['access_token'] = $data['access_token'];
        $t['expires_at']   = time() + intval($data['expires_in'] ?? 3600) - 60;
        update_option(self::OPT_TOKENS, $t);
        return $t['access_token'];
    }

    // ── Email fetching ─────────────────────────────────────────────────────

    /**
     * Returns last $limit emails involving $email address.
     *
     * Returns:
     *   ['ok' => true,  'emails' => [...], 'query' => '...', 'count' => N]
     *   ['ok' => false, 'error'  => 'Beskrivelse', 'detail' => mixed]
     *
     * Each email item: [id, subject, from, to, date, date_ts, sent]
     */
    public static function get_emails_for_address(string $email, int $limit = 5): array {
        if (!$email) {
            return ['ok' => false, 'error' => 'Tom e-postadresse'];
        }
        $token = self::get_access_token();
        if (!$token) {
            return ['ok' => false, 'error' => 'Ingen access token (kobling kan ha gått ut — koble til på nytt)'];
        }

        $query    = '(to:' . $email . ' OR from:' . $email . ')';
        $q        = rawurlencode($query);
        $list_url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages?q=' . $q . '&maxResults=' . $limit;
        $list_r   = wp_remote_get($list_url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 10,
        ]);
        if (is_wp_error($list_r)) {
            return ['ok' => false, 'error' => 'Nettverksfeil: ' . $list_r->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($list_r);
        $body = wp_remote_retrieve_body($list_r);
        $list = json_decode($body, true);

        if ($code !== 200) {
            $api_err = $list['error']['message'] ?? 'HTTP ' . $code;
            return [
                'ok'     => false,
                'error'  => 'Gmail API svarte ' . $code . ': ' . $api_err,
                'detail' => $list['error'] ?? null,
                'query'  => $query,
            ];
        }

        if (empty($list['messages'])) {
            return [
                'ok'     => true,
                'emails' => [],
                'query'  => $query,
                'count'  => 0,
                'estimate' => $list['resultSizeEstimate'] ?? 0,
            ];
        }

        $emails     = [];
        $admin_mail = strtolower(get_option('admin_email', ''));

        foreach (array_slice($list['messages'], 0, $limit) as $msg) {
            $url  = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $msg['id']
                  . '?format=metadata&metadataHeaders=Subject&metadataHeaders=From&metadataHeaders=To&metadataHeaders=Date';
            $dr   = wp_remote_get($url, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'timeout' => 8,
            ]);
            if (is_wp_error($dr)) continue;
            $detail = json_decode(wp_remote_retrieve_body($dr), true);
            if (empty($detail['payload']['headers'])) continue;

            $h = [];
            foreach ($detail['payload']['headers'] as $hdr) {
                $h[strtolower($hdr['name'])] = $hdr['value'];
            }
            $from    = $h['from'] ?? '';
            $sent    = stripos($from, $admin_mail) !== false;
            $date_s  = $h['date'] ?? '';
            $date_ts = $date_s ? strtotime($date_s) : 0;

            $emails[] = [
                'id'      => $msg['id'],
                'subject' => $h['subject'] ?? '(Ingen emne)',
                'from'    => $from,
                'to'      => $h['to'] ?? '',
                'date'    => $date_s,
                'date_ts' => $date_ts,
                'sent'    => $sent,
            ];
        }

        return [
            'ok'     => true,
            'emails' => $emails,
            'query'  => $query,
            'count'  => count($emails),
        ];
    }

    // ── AJAX ───────────────────────────────────────────────────────────────

    public static function ajax_get_emails(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        $email = sanitize_email($_POST['email'] ?? '');
        if (!$email) {
            wp_send_json_error('Mangler e-postadresse');
            return;
        }
        if (!self::is_connected()) {
            wp_send_json_error('Gmail ikke koblet til');
            return;
        }
        $result = self::get_emails_for_address($email);
        if (!empty($result['ok'])) {
            // Return a flat array of emails for backward compat with admin.js,
            // but include debug fields as a property of the array for inspection.
            wp_send_json_success([
                'emails'   => $result['emails']   ?? [],
                'query'    => $result['query']    ?? '',
                'count'    => $result['count']    ?? 0,
                'estimate' => $result['estimate'] ?? null,
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error']  ?? 'Ukjent feil',
                'detail'  => $result['detail'] ?? null,
                'query'   => $result['query']  ?? null,
            ]);
        }
    }
}
