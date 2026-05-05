<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Products_Digital
 *
 * Handles passive/digital income: products, platform listings, and daily
 * revenue snapshots. Kept entirely separate from Edifice_Revenue (which
 * tracks client invoicing for LAKI consulting work).
 */
class Edifice_Products_Digital {

    // ── Products ──────────────────────────────────────────────────────────────

    public static function get_all_products(): array {
        global $wpdb;
        $t  = $wpdb->prefix . 'edifice_products';
        $tl = $wpdb->prefix . 'edifice_product_listings';
        $tr = $wpdb->prefix . 'edifice_product_revenue';

        $rows = $wpdb->get_results("
            SELECT p.*,
                   COUNT(DISTINCT l.id)              AS listing_count,
                   COALESCE(SUM(r.revenue), 0)        AS revenue_total,
                   COALESCE(SUM(r.sales_count), 0)    AS sales_total
            FROM   `$t` p
            LEFT JOIN `$tl` l ON l.product_id = p.id
            LEFT JOIN `$tr` r ON r.listing_id  = l.id
            GROUP BY p.id
            ORDER BY p.name ASC
        ", ARRAY_A);

        return $rows ?: [];
    }

    public static function get_product(int $id): ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_products';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$t` WHERE id = %d", $id
        ), ARRAY_A) ?: null;
    }

    public static function save_product(array $data): int {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_products';

        $fields = [
            'name'        => sanitize_text_field($data['name']   ?? ''),
            'type'        => sanitize_text_field($data['type']   ?? 'ebook'),
            'brand'       => sanitize_text_field($data['brand']  ?? 'LAKI'),
            'status'      => sanitize_text_field($data['status'] ?? 'active'),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
        ];

        $id = (int) ($data['id'] ?? 0);
        if ($id > 0) {
            $wpdb->update($t, $fields, ['id' => $id]);
            return $id;
        }
        $wpdb->insert($t, $fields);
        return (int) $wpdb->insert_id;
    }

    public static function delete_product(int $id): void {
        global $wpdb;
        // Cascade: remove listings and revenue snapshots first
        $listing_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}edifice_product_listings WHERE product_id = %d", $id
        ));
        foreach ($listing_ids as $lid) {
            self::delete_listing((int) $lid);
        }
        $wpdb->delete($wpdb->prefix . 'edifice_products', ['id' => $id]);
    }

    // ── Listings ─────────────────────────────────────────────────────────────

    public static function get_listings_for_product(int $product_id): array {
        global $wpdb;
        $tl = $wpdb->prefix . 'edifice_product_listings';
        $tr = $wpdb->prefix . 'edifice_product_revenue';

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT l.*,
                   COALESCE(SUM(r.revenue), 0)     AS revenue_total,
                   COALESCE(SUM(r.sales_count), 0) AS sales_total,
                   MAX(r.snapshot_date)             AS last_synced
            FROM   `$tl` l
            LEFT JOIN `$tr` r ON r.listing_id = l.id
            WHERE  l.product_id = %d
            GROUP BY l.id
            ORDER BY l.platform ASC
        ", $product_id), ARRAY_A);

        return $rows ?: [];
    }

    public static function get_all_listings(): array {
        global $wpdb;
        $tl = $wpdb->prefix . 'edifice_product_listings';
        $tp = $wpdb->prefix . 'edifice_products';
        $tr = $wpdb->prefix . 'edifice_product_revenue';

        $rows = $wpdb->get_results("
            SELECT l.*,
                   p.name                           AS product_name,
                   p.brand                          AS product_brand,
                   COALESCE(SUM(r.revenue), 0)     AS revenue_total,
                   COALESCE(SUM(r.sales_count), 0) AS sales_total,
                   MAX(r.snapshot_date)             AS last_synced
            FROM   `$tl` l
            LEFT JOIN `$tp` p ON p.id = l.product_id
            LEFT JOIN `$tr` r ON r.listing_id = l.id
            GROUP BY l.id
            ORDER BY p.name ASC, l.platform ASC
        ", ARRAY_A);

        return $rows ?: [];
    }

    public static function save_listing(array $data): int {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_product_listings';

        $fields = [
            'product_id'     => (int) ($data['product_id'] ?? 0),
            'platform'       => sanitize_text_field($data['platform']       ?? ''),
            'listing_url'    => esc_url_raw($data['listing_url']            ?? ''),
            'price'          => (float) ($data['price']                     ?? 0),
            'currency'       => strtoupper(sanitize_text_field($data['currency'] ?? 'USD')),
            'listing_status' => sanitize_text_field($data['listing_status'] ?? 'live'),
            'notes'          => sanitize_textarea_field($data['notes']      ?? ''),
        ];

        $id = (int) ($data['id'] ?? 0);
        if ($id > 0) {
            $wpdb->update($t, $fields, ['id' => $id]);
            return $id;
        }
        $wpdb->insert($t, $fields);
        return (int) $wpdb->insert_id;
    }

    public static function delete_listing(int $id): void {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'edifice_product_revenue',  ['listing_id' => $id]);
        $wpdb->delete($wpdb->prefix . 'edifice_product_listings', ['id'         => $id]);
    }

    // ── Revenue snapshots ────────────────────────────────────────────────────

    /**
     * Upsert a daily revenue snapshot.
     * Uses INSERT … ON DUPLICATE KEY UPDATE to avoid double-counting.
     */
    public static function upsert_revenue(int $listing_id, string $date, float $revenue, int $sales_count, string $currency = 'USD', string $notes = ''): void {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_product_revenue';

        $wpdb->query($wpdb->prepare("
            INSERT INTO `$t`
                (listing_id, snapshot_date, revenue, sales_count, currency, notes, synced_at)
            VALUES (%d, %s, %f, %d, %s, %s, NOW())
            ON DUPLICATE KEY UPDATE
                revenue     = VALUES(revenue),
                sales_count = VALUES(sales_count),
                notes       = VALUES(notes),
                synced_at   = NOW()
        ", $listing_id, $date, $revenue, $sales_count, $currency, $notes));
    }

    /**
     * Save a revenue row from admin form (manual entry).
     */
    public static function save_revenue(array $data): int {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_product_revenue';

        $id         = (int) ($data['id'] ?? 0);
        $listing_id = (int) ($data['listing_id'] ?? 0);
        $date       = sanitize_text_field($data['snapshot_date'] ?? date('Y-m-d'));
        $revenue    = (float) ($data['revenue'] ?? 0);
        $sales      = (int) ($data['sales_count'] ?? 0);
        $currency   = strtoupper(sanitize_text_field($data['currency'] ?? 'USD'));
        $notes      = sanitize_textarea_field($data['notes'] ?? '');

        if ($id > 0) {
            $wpdb->update($t,
                ['revenue' => $revenue, 'sales_count' => $sales, 'currency' => $currency, 'notes' => $notes, 'snapshot_date' => $date],
                ['id' => $id]
            );
            return $id;
        }

        self::upsert_revenue($listing_id, $date, $revenue, $sales, $currency, $notes);
        return (int) $wpdb->insert_id;
    }

    public static function delete_revenue(int $id): void {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'edifice_product_revenue', ['id' => $id]);
    }

    // ── Aggregates ───────────────────────────────────────────────────────────

    public static function get_totals(): array {
        global $wpdb;
        $tr = $wpdb->prefix . 'edifice_product_revenue';
        $tl = $wpdb->prefix . 'edifice_product_listings';

        $ytd_start   = date('Y-01-01');
        $month_start = date('Y-m-01');

        $ytd = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(revenue),0) FROM `$tr` WHERE snapshot_date >= %s", $ytd_start
        ));
        $month = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(revenue),0) FROM `$tr` WHERE snapshot_date >= %s", $month_start
        ));
        $all_time = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(revenue),0) FROM `$tr`"
        );
        $active_listings = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `$tl` WHERE listing_status = 'live'"
        );

        return compact('ytd', 'month', 'all_time', 'active_listings');
    }

    public static function get_recent_snapshots(int $days = 30): array {
        global $wpdb;
        $tr = $wpdb->prefix . 'edifice_product_revenue';
        $tl = $wpdb->prefix . 'edifice_product_listings';
        $tp = $wpdb->prefix . 'edifice_products';

        $from = date('Y-m-d', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare("
            SELECT r.snapshot_date, r.revenue, r.sales_count, r.currency,
                   l.platform, p.name AS product_name, p.brand
            FROM   `$tr` r
            JOIN   `$tl` l ON l.id = r.listing_id
            JOIN   `$tp` p ON p.id = l.product_id
            WHERE  r.snapshot_date >= %s
            ORDER  BY r.snapshot_date DESC, p.name ASC
        ", $from), ARRAY_A) ?: [];
    }

    // ── AJAX handlers ────────────────────────────────────────────────────────

    public static function ajax_save_product(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        $id = self::save_product($_POST);
        wp_send_json_success(['id' => $id]);
    }

    public static function ajax_delete_product(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        self::delete_product((int) ($_POST['id'] ?? 0));
        wp_send_json_success();
    }

    public static function ajax_save_listing(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        $id = self::save_listing($_POST);
        wp_send_json_success(['id' => $id]);
    }

    public static function ajax_delete_listing(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        self::delete_listing((int) ($_POST['id'] ?? 0));
        wp_send_json_success();
    }

    public static function ajax_save_revenue(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        $id = self::save_revenue($_POST);
        wp_send_json_success(['id' => $id]);
    }

    public static function ajax_delete_revenue(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);
        self::delete_revenue((int) ($_POST['id'] ?? 0));
        wp_send_json_success();
    }

    public static function ajax_listings_for_product(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_die(-1);

        $pid = (int) ($_POST['pid'] ?? 0);
        if ($pid <= 0) {
            wp_send_json_error(['message' => 'Invalid product id']);
            return;
        }

        $product  = self::get_product($pid);
        $listings = self::get_listings_for_product($pid);

        // Include revenue snapshots per listing
        global $wpdb;
        $tr = $wpdb->prefix . 'edifice_product_revenue';
        foreach ($listings as &$l) {
            $lid = (int) $l['id'];
            $l['snapshots'] = $wpdb->get_results($wpdb->prepare(
                "SELECT snapshot_date, revenue, sales_count, currency, notes
                 FROM `$tr` WHERE listing_id = %d ORDER BY snapshot_date DESC LIMIT 10",
                $lid
            ), ARRAY_A) ?: [];
        }
        unset($l);

        wp_send_json_success([
            'product'  => $product,
            'listings' => $listings,
        ]);
    }
}
