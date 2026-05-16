<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Hosting — Driftsovervåking og kostnadskontroll for Hetzner-riggen.
 *
 * Henter live status fra Uptime Kuma + UptimeRobot, viser oppetid og responstid
 * per site, summerer månedlige kostnader. Tabellen `edifice_sites` opprettes via
 * Migration 17 og seedes med fem siter ved første aktivering.
 *
 * API-nøkler og webhook lagres i wp_options (edifice_kuma_*, edifice_uptimerobot_key,
 * edifice_slack_webhook_hosting, edifice_hetzner_monthly_eur).
 *
 * Status-data caches i transient 60 sek for å unngå rate limiting.
 */
class Edifice_Hosting {

    const TRANSIENT_STATUS = 'edifice_hosting_status';
    const CACHE_TTL        = 60; // sekunder

    public static function init() {
        add_action('wp_ajax_edifice_hosting_list',       [__CLASS__, 'ajax_list']);
        add_action('wp_ajax_edifice_hosting_status',     [__CLASS__, 'ajax_status']);
        add_action('wp_ajax_edifice_hosting_save',       [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_edifice_hosting_delete',     [__CLASS__, 'ajax_delete']);
        add_action('wp_ajax_edifice_hosting_test_alert', [__CLASS__, 'ajax_test_alert']);
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    /** Alle siter i DB, sortert: aktive først, så alfabetisk. */
    public static function get_sites(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_sites';
        $rows = $wpdb->get_results(
            "SELECT * FROM `$t` ORDER BY active DESC, name ASC",
            ARRAY_A
        );
        return $rows ?: [];
    }

    public static function get_site(int $id): ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_sites';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `$t` WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Kostnadsoppsummering — tre kort: total NOK, Hetzner EUR, snitt per site. */
    public static function get_cost_summary(): array {
        $sites = self::get_sites();
        $active_sites = array_filter($sites, fn($s) => (int) $s['active'] === 1);

        $total_monthly_nok = 0.0;
        foreach ($sites as $s) {
            $total_monthly_nok += (float) $s['monthly_cost_nok'];
        }

        $hetzner_eur     = (float) get_option('edifice_hetzner_monthly_eur', 35);
        $active_count    = count($active_sites);
        $avg_per_site_eur = $active_count > 0 ? round($hetzner_eur / $active_count, 2) : 0;

        return [
            'total_monthly_nok'    => $total_monthly_nok,
            'hetzner_monthly_eur'  => $hetzner_eur,
            'active_count'         => $active_count,
            'avg_per_site_eur'     => $avg_per_site_eur,
        ];
    }

    // ── AJAX: list ───────────────────────────────────────────────────────────

    public static function ajax_list() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');
        wp_send_json_success([
            'sites' => self::get_sites(),
            'costs' => self::get_cost_summary(),
        ]);
    }

    // ── AJAX: status (med 60s transient-cache) ───────────────────────────────

    public static function ajax_status() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');

        $force_refresh = ! empty($_POST['refresh']);
        if ($force_refresh) {
            delete_transient(self::TRANSIENT_STATUS);
        }

        $cached = get_transient(self::TRANSIENT_STATUS);
        if ($cached !== false) {
            wp_send_json_success($cached);
        }

        $sites           = self::get_sites();
        $kuma_monitors   = self::fetch_kuma_monitors();
        $uptimerobot     = self::fetch_uptimerobot_monitors();

        $result = [];
        foreach ($sites as $site) {
            $row = [
                'id'               => (int) $site['id'],
                'name'             => $site['name'],
                'url'              => $site['url'],
                'customer_name'    => $site['customer_name'],
                'monthly_cost_nok' => (float) $site['monthly_cost_nok'],
                'active'           => (int) $site['active'] === 1,
                'kuma'             => null,
                'uptimerobot'      => null,
            ];

            $kuma_id = $site['kuma_monitor_id'];
            if ($kuma_id !== null && $kuma_id !== '' && isset($kuma_monitors[$kuma_id])) {
                $row['kuma'] = $kuma_monitors[$kuma_id];
            }

            $ur_id = $site['uptimerobot_monitor_id'];
            if ($ur_id !== null && $ur_id !== '' && isset($uptimerobot[$ur_id])) {
                $row['uptimerobot'] = $uptimerobot[$ur_id];
            }

            $result[] = $row;
        }

        $payload = [
            'sites'        => $result,
            'fetched_at'   => date('c'),
            'kuma_ok'      => $kuma_monitors !== null,
            'ur_ok'        => $uptimerobot !== null,
        ];

        set_transient(self::TRANSIENT_STATUS, $payload, self::CACHE_TTL);
        wp_send_json_success($payload);
    }

    // ── AJAX: save (insert/update) ───────────────────────────────────────────

    public static function ajax_save() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');

        global $wpdb;
        $t = $wpdb->prefix . 'edifice_sites';

        $id = (int) ($_POST['id'] ?? 0);

        $kuma_raw = trim((string) ($_POST['kuma_monitor_id'] ?? ''));
        $kuma_val = $kuma_raw === '' ? null : (int) $kuma_raw;

        $ur_raw  = trim((string) ($_POST['uptimerobot_monitor_id'] ?? ''));
        $ur_val  = $ur_raw === '' ? null : sanitize_text_field($ur_raw);

        $data = [
            'name'                   => sanitize_text_field($_POST['name'] ?? ''),
            'url'                    => esc_url_raw($_POST['url'] ?? ''),
            'domain'                 => sanitize_text_field($_POST['domain'] ?? ''),
            'coolify_service_uuid'   => sanitize_text_field($_POST['coolify_service_uuid'] ?? ''),
            'coolify_container'      => sanitize_text_field($_POST['coolify_container'] ?? ''),
            'customer_name'          => sanitize_text_field($_POST['customer_name'] ?? ''),
            'monthly_cost_nok'       => (float) ($_POST['monthly_cost_nok'] ?? 0),
            'kuma_monitor_id'        => $kuma_val,
            'uptimerobot_monitor_id' => $ur_val,
            'notes'                  => sanitize_textarea_field($_POST['notes'] ?? ''),
            'active'                 => ! empty($_POST['active']) ? 1 : 0,
        ];

        if ($data['name'] === '' || $data['url'] === '') {
            wp_send_json_error('Navn og URL er påkrevd');
        }

        if ($id > 0) {
            $wpdb->update($t, $data, ['id' => $id]);
        } else {
            $wpdb->insert($t, $data);
            $id = (int) $wpdb->insert_id;
        }

        delete_transient(self::TRANSIENT_STATUS);
        wp_send_json_success(['id' => $id, 'msg' => 'Site lagret ✓']);
    }

    // ── AJAX: delete ─────────────────────────────────────────────────────────

    public static function ajax_delete() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');

        global $wpdb;
        $t  = $wpdb->prefix . 'edifice_sites';
        $id = (int) ($_POST['id'] ?? 0);
        if (! $id) wp_send_json_error('missing id');

        $wpdb->delete($t, ['id' => $id]);
        delete_transient(self::TRANSIENT_STATUS);
        wp_send_json_success(['msg' => 'Site slettet']);
    }

    // ── AJAX: test alert (post til Slack #hosting-varsler) ──────────────────

    public static function ajax_test_alert() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');

        $webhook = get_option('edifice_slack_webhook_hosting', '');
        if (! $webhook) {
            wp_send_json_error('Slack webhook ikke konfigurert. Sett den i Innstillinger → Hosting.');
        }

        $msg = sprintf(
            '🧪 *Test-varsling fra Edifice* — hosting-overvåking fungerer. (%s)',
            date('Y-m-d H:i:s')
        );

        $resp = wp_remote_post($webhook, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['text' => $msg]),
            'timeout' => 10,
        ]);

        if (is_wp_error($resp)) {
            wp_send_json_error('Slack-feil: ' . $resp->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            wp_send_json_error('Slack svarte HTTP ' . $code . ': ' . wp_remote_retrieve_body($resp));
        }

        wp_send_json_success(['msg' => 'Test-varsling sendt ✓']);
    }

    // ── API: Uptime Kuma ─────────────────────────────────────────────────────

    /**
     * Henter alle monitorer fra Uptime Kuma indeksert på monitor-ID.
     * Returnerer null hvis API ikke er konfigurert eller kallet feiler.
     */
    private static function fetch_kuma_monitors(): ?array {
        $base = rtrim((string) get_option('edifice_kuma_base_url', ''), '/');
        $key  = (string) get_option('edifice_kuma_api_key', '');
        if (! $base || ! $key) return null;

        $url = $base . '/api/monitors';
        $resp = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $key],
            'timeout' => 8,
        ]);
        if (is_wp_error($resp)) return null;
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) return null;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (! is_array($body)) return null;

        // Kuma kan returnere enten array eller {monitors: [...]} — håndter begge.
        $monitors = $body['monitors'] ?? $body;
        if (! is_array($monitors)) return null;

        $out = [];
        foreach ($monitors as $m) {
            if (! isset($m['id'])) continue;
            $id = (int) $m['id'];
            $latest = $m['latestHeartbeat'] ?? [];
            $out[$id] = [
                'status'      => self::kuma_status_label($m['status'] ?? null),
                'uptime_24h'  => isset($m['uptime']['24'])  ? round((float) $m['uptime']['24']  * 100, 2) : null,
                'uptime_30d'  => isset($m['uptime']['720']) ? round((float) $m['uptime']['720'] * 100, 2) : null,
                'response_ms' => isset($m['avgPing']) ? (int) $m['avgPing']
                                : (isset($latest['ping']) ? (int) $latest['ping'] : null),
                'last_check'  => $latest['time'] ?? null,
            ];
        }
        return $out;
    }

    private static function kuma_status_label($status): string {
        // Kuma: 1=up, 0=down, 2=pending, 3=maintenance
        if ((int) $status === 1) return 'up';
        if ((int) $status === 0) return 'down';
        if ((int) $status === 3) return 'maintenance';
        return 'unknown';
    }

    // ── API: UptimeRobot ─────────────────────────────────────────────────────

    /**
     * Henter alle monitorer fra UptimeRobot v2 API. Indeksert på monitor-ID (string).
     * Returnerer null hvis API ikke er konfigurert eller kallet feiler.
     */
    private static function fetch_uptimerobot_monitors(): ?array {
        $key = (string) get_option('edifice_uptimerobot_key', '');
        if (! $key) return null;

        $resp = wp_remote_post('https://api.uptimerobot.com/v2/getMonitors', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Cache-Control' => 'no-cache',
            ],
            'body'    => http_build_query([
                'api_key'              => $key,
                'format'               => 'json',
                'response_times'       => 1,
                'all_time_uptime_ratio' => 1,
            ]),
            'timeout' => 8,
        ]);
        if (is_wp_error($resp)) return null;
        if (wp_remote_retrieve_response_code($resp) !== 200) return null;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (! is_array($body) || ($body['stat'] ?? '') !== 'ok') return null;

        $out = [];
        foreach (($body['monitors'] ?? []) as $m) {
            $id = (string) ($m['id'] ?? '');
            if ($id === '') continue;
            $rt_latest = null;
            if (! empty($m['response_times']) && is_array($m['response_times'])) {
                $rt_latest = (int) $m['response_times'][0]['value'];
            }
            $out[$id] = [
                'status'       => self::uptimerobot_status_label($m['status'] ?? null),
                'uptime_ratio' => isset($m['all_time_uptime_ratio']) ? (float) $m['all_time_uptime_ratio'] : null,
                'response_ms'  => $rt_latest,
            ];
        }
        return $out;
    }

    private static function uptimerobot_status_label($status): string {
        // UptimeRobot: 2=up, 8=down (seems), 9=down, 0=paused, 1=not checked yet
        $s = (int) $status;
        if ($s === 2) return 'up';
        if ($s === 8 || $s === 9) return 'down';
        if ($s === 0) return 'paused';
        return 'unknown';
    }
}
