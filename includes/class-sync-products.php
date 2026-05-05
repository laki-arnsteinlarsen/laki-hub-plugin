<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Sync_Products
 *
 * Handles daily revenue sync from external platforms into edifice_product_revenue.
 * Gumroad: server-side API (no browser needed).
 * PromptBase / KDP / Upwork: managed via Cowork scheduled task (Chrome).
 */
class Edifice_Sync_Products {

    const CRON_HOOK    = 'edifice_daily_product_sync';
    const OPT_GR_TOKEN = 'edifice_gumroad_token';
    const OPT_LAST_GR  = 'edifice_gumroad_last_sync';

    // ── Init ─────────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action(self::CRON_HOOK, [__CLASS__, 'run_gumroad_sync']);
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule for 06:00 UTC daily
            $first = strtotime('tomorrow 06:00 UTC');
            wp_schedule_event($first, 'daily', self::CRON_HOOK);
        }
    }

    // ── Gumroad API ───────────────────────────────────────────────────────────

    /**
     * Fetch all Gumroad sales since last sync and upsert into revenue table.
     * Called by WP Cron daily, or manually via AJAX.
     */
    public static function run_gumroad_sync(): array {
        $token = get_option(self::OPT_GR_TOKEN, '');
        if (! $token) {
            return ['ok' => false, 'message' => 'Ingen Gumroad API-nøkkel konfigurert.'];
        }

        // Fetch sales from Gumroad API
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

        // Group sales by product_permalink + date
        $grouped = [];
        foreach ($sales as $sale) {
            $permalink = $sale['product_permalink'] ?? '';
            $date      = substr($sale['created_at'], 0, 10); // "YYYY-MM-DD"
            $amount    = (float) (($sale['price'] ?? 0) / 100); // pence → dollars
            $key       = $permalink . '|' . $date;
            $grouped[$key]['permalink'] = $permalink;
            $grouped[$key]['date']      = $date;
            $grouped[$key]['revenue']   = ($grouped[$key]['revenue'] ?? 0) + $amount;
            $grouped[$key]['sales']     = ($grouped[$key]['sales']   ?? 0) + 1;
        }

        foreach ($grouped as $item) {
            $listing_id = self::find_listing_id_by_url($item['permalink'], 'Gumroad');
            if (! $listing_id) {
                $errors[] = 'Ingen listing funnet for: ' . $item['permalink'];
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

        // Update last sync marker to yesterday (so next run picks up from there)
        update_option(self::OPT_LAST_GR, date('Y-m-d', strtotime('-1 day')));

        $msg = "Gumroad: {$written} dagsoppføringer skrevet.";
        if ($errors) $msg .= ' Advarsler: ' . implode('; ', $errors);

        return ['ok' => true, 'message' => $msg, 'written' => $written, 'errors' => $errors];
    }

    /**
     * Match a Gumroad permalink to an existing listing by URL substring.
     */
    private static function find_listing_id_by_url(string $permalink, string $platform): ?int {
        global $wpdb;
        $tl = $wpdb->prefix . 'edifice_product_listings';
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `$tl`
             WHERE platform = %s
               AND listing_url LIKE %s
             LIMIT 1",
            $platform,
            '%' . $wpdb->esc_like($permalink) . '%'
        ));
        return $id ? (int) $id : null;
    }

    // ── Chrome-sync endpoint (PromptBase / KDP / Upwork) ─────────────────────

    /**
     * Receive a revenue payload POSTed by the Chrome sync task.
     * Expected POST fields: listing_id, date, revenue, sales_count, platform, notes
     * The Cowork scheduled task reads each platform and POSTs here.
     */
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

    /**
     * Batch endpoint: accept an array of revenue entries at once.
     * POST body: entries = JSON array of {listing_id, date, revenue, sales_count, currency, notes}
     */
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
        wp_send_json_success(['message' => 'Innstillinger lagret.']);
    }

    public static function ajax_get_settings(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);

        wp_send_json_success([
            'gumroad_token'   => get_option(self::OPT_GR_TOKEN, ''),
            'last_gumroad'    => get_option(self::OPT_LAST_GR, '—'),
            'last_chrome'     => get_option('edifice_chrome_sync_last', '—'),
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

    /**
     * Return all listings with their IDs so the Chrome task can look up IDs by platform/URL.
     */
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
}
