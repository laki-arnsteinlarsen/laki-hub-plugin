<?php
defined('ABSPATH') || exit;

class LakiHub_CRM {

    public static function get_all(array $args = []): array {
        global $wpdb;
        $t    = $wpdb->prefix . 'laki_contacts';
        $where = 'WHERE 1=1';
        if (!empty($args['status']))   $where .= $wpdb->prepare(' AND status = %s', $args['status']);
        if (!empty($args['type']))     $where .= $wpdb->prepare(' AND type = %s', $args['type']);
        if (!empty($args['search']))   $where .= $wpdb->prepare(' AND (name LIKE %s OR org_nr LIKE %s OR email LIKE %s)',
            '%'.$args['search'].'%', '%'.$args['search'].'%', '%'.$args['search'].'%');
        $order = 'ORDER BY name ASC';
        return $wpdb->get_results("SELECT * FROM $t $where $order", ARRAY_A) ?: [];
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'laki_contacts';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id), ARRAY_A) ?: null;
    }

    public static function save(array $data): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'laki_contacts';

        $fields = [
            'type'      => sanitize_text_field($data['type'] ?? 'company'),
            'name'      => sanitize_text_field($data['name'] ?? ''),
            'org_nr'    => sanitize_text_field($data['org_nr'] ?? ''),
            'email'     => sanitize_email($data['email'] ?? ''),
            'phone'     => sanitize_text_field($data['phone'] ?? ''),
            'address'   => sanitize_textarea_field($data['address'] ?? ''),
            'category'  => sanitize_text_field($data['category'] ?? ''),
            'status'    => sanitize_text_field($data['status'] ?? 'active'),
            'notes'     => sanitize_textarea_field($data['notes'] ?? ''),
            'brreg_data'=> isset($data['brreg_data']) ? wp_json_encode($data['brreg_data']) : null,
        ];

        if (!empty($data['id'])) {
            $wpdb->update($t, $fields, ['id' => (int)$data['id']]);
            return (int)$data['id'];
        } else {
            $wpdb->insert($t, $fields);
            return $wpdb->insert_id;
        }
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete($wpdb->prefix . 'laki_contacts', ['id' => $id]);
    }

    public static function ajax_save() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        $id = self::save($_POST);
        $id ? wp_send_json_success(['id' => $id]) : wp_send_json_error('Lagring feilet');
    }

    public static function ajax_delete() {
        check_ajax_referer('laki_hub_nonce', 'nonce');
        $id = (int)($_POST['id'] ?? 0);
        self::delete($id) ? wp_send_json_success() : wp_send_json_error('Sletting feilet');
    }
}
