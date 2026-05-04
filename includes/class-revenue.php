<?php
defined('ABSPATH') || exit;

class Edifice_Revenue {

    public static function get_all(array $args = []): array {
        global $wpdb;
        $tr = $wpdb->prefix . 'edifice_revenue';
        $tc = $wpdb->prefix . 'edifice_contacts';
        $tp = $wpdb->prefix . 'edifice_projects';
        $where = 'WHERE 1=1';
        if (!empty($args['status']))     $where .= $wpdb->prepare(' AND r.status = %s', $args['status']);
        if (!empty($args['contact_id'])) $where .= $wpdb->prepare(' AND r.contact_id = %d', $args['contact_id']);
        return $wpdb->get_results(
            "SELECT r.*, c.name AS contact_name, p.name AS project_name
             FROM $tr r
             LEFT JOIN $tc c ON c.id = r.contact_id
             LEFT JOIN $tp p ON p.id = r.project_id
             $where ORDER BY r.date DESC",
            ARRAY_A
        ) ?: [];
    }

    public static function get_totals(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_revenue';
        $year = date('Y');
        return [
            'invoiced_ytd' => (float)$wpdb->get_var("SELECT SUM(amount) FROM $t WHERE YEAR(date)=$year AND status IN ('sent','paid')"),
            'paid_ytd'     => (float)$wpdb->get_var("SELECT SUM(amount) FROM $t WHERE YEAR(date)=$year AND status='paid'"),
            'overdue'      => (float)$wpdb->get_var("SELECT SUM(amount) FROM $t WHERE status='overdue'"),
            'pipeline'     => (float)$wpdb->get_var("SELECT SUM(amount) FROM $t WHERE status='draft'"),
        ];
    }

    public static function save(array $data): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_revenue';
        $fields = [
            'contact_id'  => !empty($data['contact_id'])  ? (int)$data['contact_id']  : null,
            'project_id'  => !empty($data['project_id'])  ? (int)$data['project_id']  : null,
            'type'        => sanitize_text_field($data['type'] ?? 'invoice'),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'amount'      => (float)($data['amount'] ?? 0),
            'currency'    => strtoupper(sanitize_text_field($data['currency'] ?? 'NOK')),
            'date'        => sanitize_text_field($data['date'] ?? date('Y-m-d')),
            'due_date'    => sanitize_text_field($data['due_date'] ?? '') ?: null,
            'status'      => sanitize_text_field($data['status'] ?? 'draft'),
            'invoice_nr'  => sanitize_text_field($data['invoice_nr'] ?? ''),
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
        return (bool)$wpdb->delete($wpdb->prefix . 'edifice_revenue', ['id' => $id]);
    }

    public static function ajax_save() {
        check_ajax_referer('edifice_nonce', 'nonce');
        $id = self::save($_POST);
        $id ? wp_send_json_success(['id' => $id]) : wp_send_json_error();
    }

    public static function ajax_delete() {
        check_ajax_referer('edifice_nonce', 'nonce');
        self::delete((int)($_POST['id'] ?? 0)) ? wp_send_json_success() : wp_send_json_error();
    }
}
