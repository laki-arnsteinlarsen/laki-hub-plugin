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
     * Each item: [id, subject, from, to, date, date_ts, sent]
     */
    public static function get_emails_for_address(string $email, int $limit = 5): array {
        $token = self::get_access_token();
        if (!$token || !$email) return [];

        $q        = rawurlencode('to:' . $email . ' OR from:' . $email);
        $list_url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages?q=' . $q . '&maxResults=' . $limit;
        $list_r   = wp_remote_get($list_url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 10,
        ]);
        if (is_wp_error($list_r)) return [];
        $list = json_decode(wp_remote_retrieve_body($list_r), true);
        if (empty($list['messages'])) return [];

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
        return $emails;
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
        $emails = self::get_emails_for_address($email);
        wp_send_json_success($emails);
    }
}
