<?php
defined('ABSPATH') || exit;

class Edifice_Projects {

    public static function get_all(array $args = []): array {
        global $wpdb;
        $tp = $wpdb->prefix . 'edifice_projects';
        $tc = $wpdb->prefix . 'edifice_contacts';
        $where = 'WHERE 1=1';
        if (!empty($args['status'])) $where .= $wpdb->prepare(' AND p.status = %s', $args['status']);
        return $wpdb->get_results(
            "SELECT p.*, c.name AS contact_name FROM $tp p
             LEFT JOIN $tc c ON c.id = p.contact_id
             $where ORDER BY p.start_date DESC",
            ARRAY_A
        ) ?: [];
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_projects';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id), ARRAY_A) ?: null;
    }

    public static function save(array $data): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_projects';
        $fields = [
            'contact_id'  => !empty($data['contact_id']) ? (int)$data['contact_id'] : null,
            'name'        => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status'      => sanitize_text_field($data['status'] ?? 'active'),
            'start_date'  => sanitize_text_field($data['start_date'] ?? '') ?: null,
            'end_date'    => sanitize_text_field($data['end_date'] ?? '') ?: null,
            'budget'      => !empty($data['budget']) ? (float)$data['budget'] : null,
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
        return (bool)$wpdb->delete($wpdb->prefix . 'edifice_projects', ['id' => $id]);
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
