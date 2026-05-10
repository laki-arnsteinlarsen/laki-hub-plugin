<?php
defined('ABSPATH') || exit;

class LakiHub_Time {

    // ─── Period helpers ───────────────────────────────────────────────────────

    public static function get_period_range(string $period): array {
        switch ($period) {
            case 'week':
                $from  = date('Y-m-d', strtotime('monday this week'));
                $to    = date('Y-m-d');
                $label = 'Denne uken';
                break;
            case 'last_month':
                $from  = date('Y-m-01', strtotime('first day of last month'));
                $to    = date('Y-m-t',  strtotime('last day of last month'));
                $label = date_i18n('F Y', strtotime('last month'));
                break;
            case 'year':
                $from  = date('Y-01-01');
                $to    = date('Y-m-d');
                $label = 'I år (' . date('Y') . ')';
                break;
            default: // month
                $from  = date('Y-m-01');
                $to    = date('Y-m-d');
                $label = date_i18n('F Y');
        }
        return [$from, $to, $label];
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    public static function get_all(array $args = []): array {
        global $wpdb;
        $tt = $wpdb->prefix . 'laki_time_entries';
        $tc = $wpdb->prefix . 'laki_contacts';
        $tp = $wpdb->prefix . 'laki_projects';
        $where = 'WHERE 1=1';
        if (!empty($args['contact_id'])) $where .= $wpdb->prepare(' AND t.contact_id = %d', $args['contact_id']);
        if (!empty($args['project_id']))  $where .= $wpdb->prepare(' AND t.project_id = %d', $args['project_id']);
        if (!empty($args['from']))        $where .= $wpdb->prepare(' AND t.date >= %s', $args['from']);
        if (!empty($args['to']))          $where .= $wpdb->prepare(' AND t.date <= %s', $args['to']);
        return $wpdb->get_results(
            "SELECT t.*, c.name AS contact_name, p.name AS project_name
             FROM $tt t
             LEFT JOIN $tc c ON c.id = t.contact_id
             LEFT JOIN $tp p ON p.id = t.project_id
             $where ORDER BY t.date DESC, t.id DESC",
            ARRAY_A
        ) ?: [];
    }

    /** Used by dashboard widget */
    public static function get_summary(string $from = '', string $to = ''): array {
        global $wpdb;
        $tt = $wpdb->prefix . 'laki_time_entries';
        $tc = $wpdb->prefix . 'laki_contacts';
        $where = 'WHERE 1=1';
        if ($from) $where .= $wpdb->prepare(' AND t.date >= %s', $from);
        if ($to)   $where .= $wpdb->prepare(' AND t.date <= %s', $to);
        return $wpdb->get_results(
            "SELECT c.name AS contact_name, SUM(t.hours) AS total_hours,
                    SUM(CASE WHEN t.billable=1 THEN t.hours ELSE 0 END) AS billable_hours,
                    SUM(CASE WHEN t.billable=1 THEN t.hours * IFNULL(t.hourly_rate,0) ELSE 0 END) AS billable_value
             FROM $tt t LEFT JOIN $tc c ON c.id = t.contact_id
             $where GROUP BY t.contact_id ORDER BY total_hours DESC",
            ARRAY_A
        ) ?: [];
    }

    public static function get_period_stats(string $from, string $to): array {
        global $wpdb;
        $tt  = $wpdb->prefix . 'laki_time_entries';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(hours), 0)                                                        AS total_hours,
                COALESCE(SUM(CASE WHEN billable=1 THEN hours ELSE 0 END), 0)                   AS billable_hours,
                COALESCE(SUM(CASE WHEN billable=0 THEN hours ELSE 0 END), 0)                   AS unbillable_hours,
                COALESCE(SUM(CASE WHEN billable=1 THEN hours * IFNULL(hourly_rate,0) ELSE 0 END), 0) AS billable_value
             FROM $tt WHERE date >= %s AND date <= %s",
            $from, $to
        ), ARRAY_A);
        return $row ?: ['total_hours' => 0, 'billable_hours' => 0, 'unbillable_hours' => 0, 'billable_value' => 0];
    }

    public static function get_by_client(string $from, string $to): array {
        global $wpdb;
        $tt = $wpdb->prefix . 'laki_time_entries';
        $tc = $wpdb->prefix . 'laki_contacts';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.name AS contact_name,
                    COALESCE(SUM(t.hours), 0) AS total_hours,
                    COALESCE(SUM(CASE WHEN t.billable=1 THEN t.hours ELSE 0 END), 0) AS billable_hours,
                    COALESCE(SUM(CASE WHEN t.billable=1 THEN t.hours * IFNULL(t.hourly_rate,0) ELSE 0 END), 0) AS billable_value
             FROM $tt t LEFT JOIN $tc c ON c.id = t.contact_id
             WHERE t.date >= %s AND t.date <= %s
             GROUP BY t.contact_id ORDER BY total_hours DESC",
            $from, $to
        ), ARRAY_A) ?: [];
    }

    public static function get_by_project(string $from, string $to): array {
        global $wpdb;
        $tt = $wpdb->prefix . 'laki_time_entries';
        $tp = $wpdb->prefix . 'laki_projects';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(p.name, '— Intet prosjekt') AS project_name,
                    COALESCE(SUM(t.hours), 0) AS total_hours,
                    COALESCE(SUM(CASE WHEN t.billable=1 THEN t.hours ELSE 0 END), 0) AS billable_hours
             FROM $tt t LEFT JOIN $tp p ON p.id = t.project_id
             WHERE t.date >= %s AND t.date <= %s
             GROUP BY t.project_id ORDER BY total_hours DESC",
            $from, $to
        ), ARRAY_A) ?: [];
    }

    // ─── Active timer (stored in wp_options) ─────────────────────────────────

    public static function get_active_timer(): ?array {
        $timer = get_option('laki_active_timer');
        if (!$timer || !is_array($timer) || empty($timer['started_at'])) return null;
        $timer['elapsed_seconds'] = time() - (int)$timer['started_at'];
        return $timer;
    }

    public static function start_timer(array $data): bool {
        if (self::get_active_timer()) return false;
        update_option('laki_active_timer', [
            'contact_id'  => (int)($data['contact_id'] ?? 0),
            'project_id'  => (int)($data['project_id'] ?? 0),
            'description' => sanitize_text_field($data['description'] ?? ''),
            'started_at'  => time(),
        ], false);
        return true;
    }

    public static function stop_timer(): array|false {
        $timer = get_option('laki_active_timer');
        if (!$timer || empty($timer['started_at'])) return false;

        $elapsed = time() - (int)$timer['started_at'];
        // Round to nearest 0.25h, minimum 0.25h
        $hours = max(0.25, round(($elapsed / 3600) * 4) / 4);

        $id = self::save([
            'contact_id'  => $timer['contact_id']  ?: null,
            'project_id'  => $timer['project_id']  ?: null,
            'description' => $timer['description'],
            'date'        => date('Y-m-d', (int)$timer['started_at']),
            'hours'       => $hours,
            'billable'    => 1,
        ]);

        delete_option('laki_active_timer');
        return $id ? ['id' => $id, 'hours' => $hours] : false;
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public static function save(array $data): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'laki_time_entries';
        $fields = [
            'project_id'  => !empty($data['project_id'])  ? (int)$data['project_id']  : null,
            'contact_id'  => !empty($data['contact_id'])  ? (int)$data['contact_id']  : null,
            'date'        => sanitize_text_field($data['date'] ?? date('Y-m-d')),
            'hours'       => (float)($data['hours'] ?? 0),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'billable'    => isset($data['billable']) ? 1 : 0,
            'hourly_rate' => !empty($data['hourly_rate']) ? (float)$data['hourly_rate'] : null,
        ];
        if (!empty($data['id'])) {
            $wpdb->update($t, $fields, ['id' => (int)$data['id']]);
            return (int)$data['id'];
        }
        $wpdb->insert($t, $fields);
        return $wpdb->insert_id ?: false;
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool)$wpdb->delete($wpdb->prefix . 'laki_time_entries', ['id' => $id]);
    }

    // ─── AJAX ─────────────────────────────────────────────────────────────────

    public static function ajax_save() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        $id = self::save($_POST);
        $id ? wp_send_json_success(['id' => $id]) : wp_send_json_error();
    }

    public static function ajax_delete() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        self::delete((int)($_POST['id'] ?? 0)) ? wp_send_json_success() : wp_send_json_error();
    }

    public static function ajax_start_timer() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        $ok = self::start_timer($_POST);
        $ok
            ? wp_send_json_success(['started_at' => time()])
            : wp_send_json_error(['message' => 'En timer kjører allerede.']);
    }

    public static function ajax_stop_timer() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        $result = self::stop_timer();
        $result ? wp_send_json_success($result) : wp_send_json_error();
    }

    public static function ajax_active_timer() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        wp_send_json_success(self::get_active_timer());
    }

    public static function ajax_period_data() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        $period = sanitize_text_field($_POST['period'] ?? 'month');
        [$from, $to, $label] = self::get_period_range($period);
        wp_send_json_success([
            'from'       => $from,
            'to'         => $to,
            'label'      => $label,
            'stats'      => self::get_period_stats($from, $to),
            'by_client'  => self::get_by_client($from, $to),
            'by_project' => self::get_by_project($from, $to),
            'entries'    => self::get_all(['from' => $from, 'to' => $to]),
        ]);
    }

    /** CSV export — outputs file directly (not JSON) */
    public static function ajax_export() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        $from    = sanitize_text_field($_REQUEST['from'] ?? date('Y-01-01'));
        $to      = sanitize_text_field($_REQUEST['to']   ?? date('Y-m-d'));
        $entries = self::get_all(['from' => $from, 'to' => $to]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="timeliste-' . $from . '-' . $to . '.csv"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($out, ['Dato', 'Klient', 'Prosjekt', 'Beskrivelse', 'Timer', 'Fakturerbart', 'Timepris', 'Beløp (NOK)'], ';');

        foreach ($entries as $e) {
            $amount = ($e['billable'] && $e['hourly_rate'])
                ? number_format((float)$e['hours'] * (float)$e['hourly_rate'], 0, ',', '')
                : '';
            fputcsv($out, [
                $e['date'],
                $e['contact_name'] ?? '',
                $e['project_name'] ?? '',
                $e['description']  ?? '',
                number_format((float)$e['hours'], 2, ',', ''),
                $e['billable'] ? 'Ja' : 'Nei',
                $e['hourly_rate'] ? number_format((float)$e['hourly_rate'], 0, ',', '') : '',
                $amount,
            ], ';');
        }
        fclose($out);
        exit;
    }
}
