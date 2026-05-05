<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Sync_Products
 *
 * Handles daily revenue sync from external platforms into edifice_product_revenue.
 * Gumroad: OAuth 2.0 authorization code flow + server-side API.
 * PromptBase / KDP / Upwork: managed via Cowork scheduled task (Chrome).
 */
class Edifice_Sync_Products {

    const CRON_HOOK     = 'edifice_daily_product_sync';
    const OPT_GR_TOKEN  = 'edifice_gumroad_token';
    const OPT_LAST_GR   = 'edifice_gumroad_last_sync';
    const OPT_GR_STATE  = 'edifice_gumroad_oauth_state';

    const GR_CLIENT_ID     = 'ksZTqjTPLRU0xOVG2Ayvu1d024vHgq36TKn8272CGl0';
    const GR_CLIENT_SECRET = 'tEeOM0OrHaZaMDHxeSUGYAqx6hlc1EbCt6EtBu3lodA';
    const GR_REDIRECT_URI  = 'https://edifice.arnsteinlarsen.no';

    // ── Init ─────────────────────────────────────────────────────────────────

    public static function init(): void {
        // OAuth callback: intercept ?code= on the main site (must be early)
        add_action('init', [__CLASS__, 'maybe_handle_oauth_callback'], 5);

        add_action(self::CRON_HOOK, [__CLASS__, 'run_gumroad_sync']);
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            $first = strtotime('tomorrow 06:00 UTC');
            wp_schedule_event($first, 'daily', self::CRON_HOOK);
        }
    }

    // ── OAuth callback handler ────────────────────────────────────────────────

    /**
     * Called on WordPress `init`. If ?code= is present and state matches,
     * exchange for a token and store it, then redirect back to Edifice.
     */
    public static function maybe_handle_oauth_callback(): void {
        if (! isset($_GET['code'])) {
            return;
        }
        // Only handle on the Edifice frontend (not wp-admin)
        if (is_admin()) {
            return;
        }

        $code          = sanitize_text_field($_GET['code']);
        $state_in      = sanitize_text_field($_GET['state'] ?? '');
        $stored_state  = get_option(self::OPT_GR_STATE, '');

        // Accept if state matches OR if no state was stored (legacy flow)
        if ($stored_state && $state_in && $state_in !== $stored_state) {
            wp_redirect(home_url('/#products?gumroad=error&msg=state_mismatch'));
            exit;
        }

        // Exchange code for access token
        $resp = wp_remote_post('https://gumroad.com/oauth/token', [
            'timeout' => 20,
            'body'    => [
                'client_id'     => self::GR_CLIENT_ID,
                'client_secret' => self::GR_CLIENT_SECRET,
                'redirect_uri'  => self::GR_REDIRECT_URI,
                'code'          => $code,
                'grant_type'    => 'authorization_code',
            ],
        ]);

        if (is_wp_error($resp)) {
            wp_redirect(home_url('/#products?gumroad=error&msg=' . urlencode($resp->get_error_message())));
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if (! empty($body['access_token'])) {
            update_option(self::OPT_GR_TOKEN, sanitize_text_field($body['access_token']));
            delete_option(self::OPT_GR_STATE);
            wp_redirect(home_url('/#products?gumroad=connected'));
            exit;
        }

        $err = $body['error_description'] ?? $body['error'] ?? 'ukjent feil';
        wp_redirect(home_url('/#products?gumroad=error&msg=' . urlencode($err)));
        exit;
    }

    // ── OAuth helpers ─────────────────────────────────────────────────────────

    /** Return the Gumroad authorization URL (used by the UI "Connect" button). */
    public static function get_oauth_url(): string {
        $state = wp_generate_uuid4();
        update_option(self::OPT_GR_STATE, $state);

        return add_query_arg([
            'client_id'     => self::GR_CLIENT_ID,
            'redirect_uri'  => self::GR_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'edit_products view_sales',
            'state'         => $state,
        ], 'https://gumroad.com/oauth/authorize');
    }

    public static function ajax_get_oauth_url(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        wp_send_json_success(['url' => self::get_oauth_url()]);
    }

    public static function ajax_disconnect_gumroad(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        delete_option(self::OPT_GR_TOKEN);
        delete_option(self::OPT_GR_STATE);
        wp_send_json_success(['message' => 'Gumroad frakoblet.']);
    }

    // ── Gumroad API ───────────────────────────────────────────────────────────

    public static function run_gumroad_sync(): array {
        $token = get_option(self::OPT_GR_TOKEN, '');
        if (! $token) {
            return ['ok' => false, 'message' => 'Ingen Gumroad-tilkobling. Koble til Gumroad først.'];
        }

        $after = get_option(self::OPT_LAST_GR, date('Y-m-d', strtotime('-90 days')));
        $url   = add_query_arg([
            'access_token' => $token,
            'after'        => $after,
        ], 'https://api.gumroad.com/v2/sales');

        $resp = wp_remote_get($url, ['timeout' => 20]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'message' => 'HTTP-feil: ' . $resp->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($body['success'])) {
            return ['ok' => false, 'message' => 'Gumroad API-feil: ' . ($body['message'] ?? 'Ukjent feil')];
        }

        $sales   = $body['sales'] ?? [];
        $written = 0;
        $errors  = [];

        // Group by permalink + date
        $grouped = [];
        foreach ($sales as $sale) {
            $permalink = $sale['product_permalink'] ?? '';
            $date      = substr($sale['created_at'], 0, 10);
            $amount    = (float) (($sale['price'] ?? 0) / 100);
            $key       = $permalink . '|' . $date;
            $grouped[$key]['permalink'] = $permalink;
            $grouped[$key]['date']      = $date;
            $grouped[$key]['revenue']   = ($grouped[$key]['revenue'] ?? 0) + $amount;
            $grouped[$key]['sales']     = ($grouped[$key]['sales']   ?? 0) + 1;
        }

        foreach ($grouped as $item) {
            $listing_id = self::find_listing_id_by_url($item['permalink'], 'Gumroad');
            if (! $listing_id) {
                $errors[] = 'Ingen listing: ' . $item['permalink'];
                continue;
            }
            Edifice_Products_Digital::upsert_revenue(
                $listing_id,
                $item['date'],
                $item['revenue'],
                $item['sales'],
                'USD',
                'Auto-synket fra Gumroad API'
            );
            $written++;
        }

        update_option(self::OPT_LAST_GR, date('Y-m-d', strtotime('-1 day')));

        $msg = "Gumroad: {$written} dagsoppføringer skrevet.";
        if ($errors) $msg .= ' Advarsler: ' . implode('; ', $errors);

        return ['ok' => true, 'message' => $msg, 'written' => $written, 'errors' => $errors];
    }

    private static function find_listing_id_by_url(string $permalink, string $platform): ?int {
        global $wpdb;
        $tl = $wpdb->prefix . 'edifice_product_listings';
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `$tl`
             WHERE platform = %s AND listing_url LIKE %s
             LIMIT 1",
            $platform,
            '%' . $wpdb->esc_like($permalink) . '%'
        ));
        return $id ? (int) $id : null;
    }

    // ── Chrome-sync endpoints ─────────────────────────────────────────────────

    public static function ajax_chrome_sync_revenue(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);

        $listing_id  = (int) ($_POST['listing_id']  ?? 0);
        $date        = sanitize_text_field($_POST['date']        ?? date('Y-m-d'));
        $revenue     = (float) ($_POST['revenue']    ?? 0);
        $sales_count = (int) ($_POST['sales_count']  ?? 0);
        $currency    = strtoupper(sanitize_text_field($_POST['currency'] ?? 'USD'));
        $notes       = sanitize_textarea_field($_POST['notes']   ?? 'Chrome-sync');

        if (! $listing_id) {
            wp_send_json_error(['message' => 'listing_id mangler']);
            return;
        }

        Edifice_Products_Digital::upsert_revenue($listing_id, $date, $revenue, $sales_count, $currency, $notes);
        wp_send_json_success(['message' => "Lagret: listing $listing_id — $date — $revenue $currency"]);
    }

    public static function ajax_chrome_sync_batch(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);

        $raw     = stripslashes($_POST['entries'] ?? '[]');
        $entries = json_decode($raw, true);

        if (! is_array($entries)) {
            wp_send_json_error(['message' => 'Ugyldig entries JSON']);
            return;
        }

        $written = 0;
        foreach ($entries as $e) {
            $lid = (int) ($e['listing_id'] ?? 0);
            if (! $lid) continue;
            Edifice_Products_Digital::upsert_revenue(
                $lid,
                sanitize_text_field($e['date']        ?? date('Y-m-d')),
                (float) ($e['revenue']                ?? 0),
                (int) ($e['sales_count']              ?? 0),
                strtoupper(sanitize_text_field($e['currency'] ?? 'USD')),
                sanitize_textarea_field($e['notes']   ?? 'Chrome-sync')
            );
            $written++;
        }

        update_option('edifice_chrome_sync_last', current_time('mysql'));
        wp_send_json_success(['written' => $written, 'message' => "$written oppføringer lagret."]);
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public static function ajax_save_settings(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        $token = sanitize_text_field($_POST['gumroad_token'] ?? '');
        update_option(self::OPT_GR_TOKEN, $token);
        wp_send_json_success(['message' => 'Token lagret.']);
    }

    public static function ajax_get_settings(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        $token = get_option(self::OPT_GR_TOKEN, '');
        wp_send_json_success([
            'gumroad_connected' => ! empty($token),
            'gumroad_token'     => $token,
            'last_gumroad'      => get_option(self::OPT_LAST_GR, '—'),
            'last_chrome'       => get_option('edifice_chrome_sync_last', '—'),
        ]);
    }

    public static function ajax_trigger_gumroad(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        $result = self::run_gumroad_sync();
        if ($result['ok']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public static function ajax_get_listings_for_sync(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);

        global $wpdb;
        $tl = $wpdb->prefix . 'edifice_product_listings';
        $tp = $wpdb->prefix . 'edifice_products';

        $rows = $wpdb->get_results("
            SELECT l.id, l.platform, l.listing_url, l.listing_status, l.currency,
                   p.name AS product_name
            FROM `$tl` l
            JOIN `$tp` p ON p.id = l.product_id
            WHERE l.listing_status IN ('live','scheduled','pending_review')
            ORDER BY l.platform, p.name
        ", ARRAY_A);

        wp_send_json_success(['listings' => $rows ?: []]);
    }

    // ── Auto-register PromptBase listing ──────────────────────────────────────

    /**
     * Called by the Cowork scheduled task (via JS fetch from Edifice page)
     * when a PromptBase skill/prompt is detected as approved.
     *
     * POST fields:
     *   nonce, queue_id, title, platform, product_type,
     *   listing_url, price, listing_status
     *
     * Logic:
     *   1. Find or create edifice_products row (match on name).
     *   2. Find or create edifice_product_listings row (match on platform + listing_url).
     *   3. Return listing_id so the caller can store it in promptbase-queue.json.
     */
    public static function ajax_register_promptbase_product(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);

        global $wpdb;
        $tp = $wpdb->prefix . 'edifice_products';
        $tl = $wpdb->prefix . 'edifice_product_listings';

        $queue_id       = sanitize_text_field($_POST['queue_id']       ?? '');
        $title          = sanitize_text_field($_POST['title']          ?? '');
        $platform       = sanitize_text_field($_POST['platform']       ?? 'PromptBase');
        $product_type   = sanitize_text_field($_POST['product_type']   ?? 'prompt');
        $listing_url    = esc_url_raw($_POST['listing_url']            ?? '');
        $price          = (float) ($_POST['price']                     ?? 0);
        $listing_status = sanitize_text_field($_POST['listing_status'] ?? 'pending_review');

        if (! $title) {
            wp_send_json_error(['message' => 'title mangler']);
            return;
        }

        // 1. Find or create product
        $product_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `$tp` WHERE name = %s LIMIT 1",
            $title
        ));

        if (! $product_id) {
            $wpdb->insert($tp, [
                'name'        => $title,
                'type'        => $product_type,   // e.g. 'Agent Skill', 'Prompt'
                'brand'       => 'StrategistKit',
                'status'      => 'active',
                'description' => 'Auto-registrert fra PromptBase (' . $queue_id . ')',
            ]);
            $product_id = (int) $wpdb->insert_id;
        }

        if (! $product_id) {
            wp_send_json_error(['message' => 'Klarte ikke opprette produkt']);
            return;
        }

        // 2. Find or create listing (match on platform + listing_url OR platform + product_id)
        $listing_id = null;

        if ($listing_url) {
            $listing_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `$tl` WHERE platform = %s AND listing_url = %s LIMIT 1",
                $platform, $listing_url
            ));
        }

        if (! $listing_id) {
            // Fall back: same product on same platform
            $listing_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `$tl` WHERE platform = %s AND product_id = %d LIMIT 1",
                $platform, $product_id
            ));
        }

        if ($listing_id) {
            // Update status and URL if they have changed
            $wpdb->update($tl, [
                'listing_url'    => $listing_url    ?: $listing_url,
                'listing_status' => $listing_status,
                'price'          => $price,
            ], ['id' => $listing_id]);
        } else {
            $wpdb->insert($tl, [
                'product_id'     => $product_id,
                'platform'       => $platform,
                'listing_url'    => $listing_url,
                'price'          => $price,
                'currency'       => 'USD',
                'listing_status' => $listing_status,
                'notes'          => 'Auto-registrert fra queue_id=' . $queue_id,
            ]);
            $listing_id = (int) $wpdb->insert_id;
        }

        if (! $listing_id) {
            wp_send_json_error(['message' => 'Klarte ikke opprette listing']);
            return;
        }

        wp_send_json_success([
            'product_id' => $product_id,
            'listing_id' => $listing_id,
            'message'    => "Registrert: $title på $platform (listing #$listing_id)",
        ]);
    }

}
 // ← last line is closing brace of ajax_get_listings_for_sync, handled by sed below
