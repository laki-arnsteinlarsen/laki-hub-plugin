<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Etsy — Etsy Open API v3 integration.
 *
 * Etsy bruker OAuth 2.0 med PKCE (mer komplekst enn Gumroad sin
 * authorization_code-flow). Tokens har 1-time levetid; refresh_token
 * varer 90 dager.
 *
 * Flow:
 *   1. Admin lagrer keystring + shared_secret fra Etsy Developer Portal
 *   2. Klikker "Koble til Etsy" → start_oauth() genererer PKCE og redirecter
 *   3. Etsy redirecter tilbake med ?code=… → handle_callback() bytter
 *      code mot tokens med code_verifier
 *   4. sync() kaller GET /shops/{shop_id}/listings og /receipts
 *
 * Endpoints (alle krever x-api-key header + Bearer-token):
 *   - GET /v3/application/users/me              → finn user_id + shop_id
 *   - GET /v3/application/shops/{shop_id}/listings/active
 *   - GET /v3/application/shops/{shop_id}/receipts
 */
class Edifice_Etsy {

    const OPT_CREDS    = 'edifice_etsy_credentials';   // [keystring, shared_secret]
    const OPT_TOKENS   = 'edifice_etsy_tokens';         // [access_token, refresh_token, expires_at, user_id, shop_id]
    const OPT_VERIFIER = 'edifice_etsy_pkce_verifier';  // midlertidig under OAuth-flow

    const AUTH_URL  = 'https://www.etsy.com/oauth/connect';
    const TOKEN_URL = 'https://api.etsy.com/v3/public/oauth/token';
    const API_BASE  = 'https://openapi.etsy.com/v3/application';

    // Scopes vi trenger
    const SCOPES = 'listings_r transactions_r shops_r';

    // ── PKCE-helpers ─────────────────────────────────────────────────────────

    /**
     * Genererer PKCE code_verifier (43-128 tegn, URL-safe).
     */
    private static function generate_verifier(): string {
        $bytes = random_bytes(32);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Genererer PKCE code_challenge (SHA256 av verifier, base64url).
     */
    private static function challenge_from(string $verifier): string {
        $hash = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    public static function redirect_uri(): string {
        return admin_url('admin.php?page=edifice-settings&_etsy_callback=1');
    }

    public static function is_connected(): bool {
        $t = get_option(self::OPT_TOKENS, []);
        return !empty($t['access_token']) && !empty($t['shop_id']);
    }

    // ── OAuth-flyt ───────────────────────────────────────────────────────────

    /**
     * Start OAuth: lagre verifier, returner auth-URL for redirect.
     */
    public static function get_auth_url(): string {
        $creds = get_option(self::OPT_CREDS, []);
        if (empty($creds['keystring'])) return '';

        $verifier  = self::generate_verifier();
        $challenge = self::challenge_from($verifier);
        $state     = wp_create_nonce('edifice_etsy_oauth');

        // Lagre verifier midlertidig — trengs ved callback
        set_transient(self::OPT_VERIFIER, $verifier, 600); // 10 min

        return self::AUTH_URL . '?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => $creds['keystring'],
            'redirect_uri'          => self::redirect_uri(),
            'scope'                 => self::SCOPES,
            'state'                 => $state,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Handle OAuth callback: bytt code mot tokens med PKCE-verifier.
     */
    public static function handle_callback(string $code, string $state): bool {
        if (!wp_verify_nonce($state, 'edifice_etsy_oauth')) return false;

        $verifier = get_transient(self::OPT_VERIFIER);
        if (!$verifier) return false;
        delete_transient(self::OPT_VERIFIER);

        $creds = get_option(self::OPT_CREDS, []);

        $resp = wp_remote_post(self::TOKEN_URL, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $creds['keystring']     ?? '',
                'redirect_uri'  => self::redirect_uri(),
                'code'          => $code,
                'code_verifier' => $verifier,
            ],
            'timeout' => 15,
        ]);
        if (is_wp_error($resp)) return false;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($body['access_token'])) return false;

        // Token-strukturen: access_token er "{user_id}.{token}"
        $access_token = $body['access_token'];
        $parts = explode('.', $access_token, 2);
        $user_id = (int) ($parts[0] ?? 0);

        // Hent shop_id via API
        $shop_id = self::fetch_shop_id($access_token, $user_id);

        update_option(self::OPT_TOKENS, [
            'access_token'  => $access_token,
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at'    => time() + intval($body['expires_in'] ?? 3600) - 60,
            'user_id'       => $user_id,
            'shop_id'       => $shop_id,
        ]);
        return true;
    }

    public static function disconnect(): void {
        delete_option(self::OPT_TOKENS);
    }

    private static function fetch_shop_id(string $access_token, int $user_id): ?int {
        $creds = get_option(self::OPT_CREDS, []);
        $resp  = wp_remote_get(self::API_BASE . "/users/$user_id/shops", [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'x-api-key'     => $creds['keystring'] ?? '',
            ],
            'timeout' => 10,
        ]);
        if (is_wp_error($resp)) return null;
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        // Etsy returnerer enkelt-shop-objekt direkte (ikke array)
        return isset($data['shop_id']) ? (int) $data['shop_id'] : null;
    }

    // ── Token-refresh ────────────────────────────────────────────────────────

    public static function get_access_token(): string {
        $t = get_option(self::OPT_TOKENS, []);
        if (empty($t['access_token'])) return '';
        if (time() < (int)($t['expires_at'] ?? 0)) return $t['access_token'];

        // Refresh
        if (empty($t['refresh_token'])) return '';
        $creds = get_option(self::OPT_CREDS, []);
        $resp  = wp_remote_post(self::TOKEN_URL, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $creds['keystring'] ?? '',
                'refresh_token' => $t['refresh_token'],
            ],
            'timeout' => 15,
        ]);
        if (is_wp_error($resp)) return '';
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['access_token'])) return '';

        $t['access_token']  = $data['access_token'];
        $t['refresh_token'] = $data['refresh_token'] ?? $t['refresh_token'];
        $t['expires_at']    = time() + intval($data['expires_in'] ?? 3600) - 60;
        update_option(self::OPT_TOKENS, $t);
        return $t['access_token'];
    }

    // ── API-helpers ──────────────────────────────────────────────────────────

    private static function api_get(string $path): array {
        $token = self::get_access_token();
        if (!$token) return ['ok' => false, 'error' => 'Ingen access token'];
        $creds = get_option(self::OPT_CREDS, []);
        $resp = wp_remote_get(self::API_BASE . $path, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'x-api-key'     => $creds['keystring'] ?? '',
            ],
            'timeout' => 15,
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'error' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code !== 200) {
            return ['ok' => false, 'error' => 'HTTP ' . $code . ': ' . ($body['error'] ?? 'ukjent')];
        }
        return ['ok' => true, 'data' => $body];
    }

    public static function verify_api(): array {
        $t = get_option(self::OPT_TOKENS, []);
        if (empty($t['shop_id'])) {
            return ['ok' => false, 'error' => 'Ingen shop tilkoblet'];
        }
        $r = self::api_get("/shops/{$t['shop_id']}");
        if (!$r['ok']) return $r;
        return [
            'ok'        => true,
            'shop_name' => $r['data']['shop_name'] ?? '',
            'shop_id'   => $t['shop_id'],
            'user_id'   => $t['user_id'] ?? null,
        ];
    }

    // ── Sync: hent listings og register som edifice_product_listings ─────────

    public static function sync_listings(): array {
        $t = get_option(self::OPT_TOKENS, []);
        if (empty($t['shop_id'])) {
            return ['ok' => false, 'error' => 'Ikke koblet til Etsy'];
        }
        global $wpdb;
        $tl = $wpdb->prefix . 'edifice_product_listings';
        $tp = $wpdb->prefix . 'edifice_products';

        $r = self::api_get("/shops/{$t['shop_id']}/listings/active?limit=100");
        if (!$r['ok']) return $r;
        $listings = $r['data']['results'] ?? [];

        $synced = ['new' => 0, 'updated' => 0];
        foreach ($listings as $l) {
            $listing_url = $l['url'] ?? '';
            $title       = $l['title'] ?? '';
            $price_obj   = $l['price'] ?? [];
            $price_dec   = isset($price_obj['amount'], $price_obj['divisor'])
                ? round($price_obj['amount'] / max(1, $price_obj['divisor']), 2) : 0.0;
            $currency    = $price_obj['currency_code'] ?? 'USD';
            $state       = $l['state'] ?? 'active';

            // Match etablert listing på platform=Etsy + listing_url
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `$tl` WHERE platform = %s AND listing_url = %s LIMIT 1",
                'Etsy', $listing_url
            ));

            if ($existing_id) {
                $wpdb->update($tl, [
                    'price'          => $price_dec,
                    'currency'       => $currency,
                    'listing_status' => $state,
                ], ['id' => $existing_id]);
                $synced['updated']++;
                continue;
            }

            // Opprett produkt-rad hvis ikke finnes (ett produkt per Etsy-listing til å begynne med)
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `$tp` WHERE name = %s LIMIT 1", $title
            ));
            if (!$product_id) {
                $wpdb->insert($tp, [
                    'name'        => $title,
                    'type'        => 'digital',
                    'brand'       => 'LAKI',
                    'status'      => 'active',
                    'description' => 'Auto-registrert fra Etsy-sync',
                ]);
                $product_id = (int) $wpdb->insert_id;
            }

            $wpdb->insert($tl, [
                'product_id'     => (int) $product_id,
                'platform'       => 'Etsy',
                'listing_url'    => $listing_url,
                'price'          => $price_dec,
                'currency'       => $currency,
                'listing_status' => $state,
                'notes'          => 'Auto-registrert fra Etsy API. Listing ID: ' . ($l['listing_id'] ?? ''),
            ]);
            $synced['new']++;
        }
        return ['ok' => true, 'synced' => $synced, 'total_fetched' => count($listings)];
    }

    // ── AJAX ─────────────────────────────────────────────────────────────────

    public static function ajax_get_auth_url(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        $url = self::get_auth_url();
        $url ? wp_send_json_success(['url' => $url]) : wp_send_json_error('Mangler keystring');
    }

    public static function ajax_disconnect(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        self::disconnect();
        wp_send_json_success();
    }

    public static function ajax_sync_listings(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        wp_send_json_success(self::sync_listings());
    }

    public static function ajax_verify(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        wp_send_json_success(self::verify_api());
    }
}
