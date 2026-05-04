<?php
defined('ABSPATH') || exit;

class LakiHub_Time {

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

    public static function get_summary(string $from = '', string $to = ''): array {
        global $wpdb;
        $tt = $wpdb->prefix . 'laki_time_entries';
        $tc = $wpdb->prefix . 'laki_contacts';
        $where = "WHERE 1=1";
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
        return $wpdb->insert_id;
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool)$wpdb->delete($wpdb->prefix . 'laki_time_entries', ['id' => $id]);
    }

    public static function ajax_save() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        $id = self::save($_POST);
        $id ? wp_send_json_success(['id' => $id]) : wp_send_json_error();
    }

    public static function ajax_delete() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        self::delete((int)($_POST['id'] ?? 0)) ? wp_send_json_success() : wp_send_json_error();
    }
}
