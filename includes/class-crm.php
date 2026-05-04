<?php
defined('ABSPATH') || exit;

class Edifice_CRM {

    // Standardkategorier — vises som checkboxer i skjemaet
    const CATEGORIES = [
        'Kunde'               => 'green',
        'Prospect'            => 'blue',
        'Tidligere kunde'     => 'gray',
        'Styreoppdrag'        => 'purple',
        'Rådgivningsoppdrag'  => 'teal',
        'Leverandør'          => 'orange',
        'Partner'             => 'blue',
        'Nettverk'            => 'gray',
        'Investering'         => 'gold',
    ];

    public static function get_all(array $args = []): array {
        global $wpdb;
        $t     = $wpdb->prefix . 'edifice_contacts';
        $where = 'WHERE 1=1';
        if (!empty($args['status'])) $where .= $wpdb->prepare(' AND status = %s', $args['status']);
        if (!empty($args['type']))   $where .= $wpdb->prepare(' AND type = %s', $args['type']);
        if (!empty($args['search'])) $where .= $wpdb->prepare(
            ' AND (name LIKE %s OR org_nr LIKE %s OR email LIKE %s)',
            '%'.$args['search'].'%', '%'.$args['search'].'%', '%'.$args['search'].'%'
        );
        $rows = $wpdb->get_results("SELECT * FROM $t $where ORDER BY name ASC", ARRAY_A) ?: [];
        return array_map([__CLASS__, 'decode_row'], $rows);
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $t   = $wpdb->prefix . 'edifice_contacts';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id), ARRAY_A);
        return $row ? self::decode_row($row) : null;
    }

    /** Dekod category fra JSON til array */
    private static function decode_row(array $row): array {
        $row['category'] = !empty($row['category'])
            ? (json_decode($row['category'], true) ?: [])
            : [];
        return $row;
    }

    public static function save(array $data): int|false {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';

        // category kommer som JSON-streng fra skjemaet
        $raw_cat = $data['category'] ?? '';
        if (is_string($raw_cat)) {
            $cat_arr = json_decode(stripslashes($raw_cat), true);
            $category = is_array($cat_arr) ? wp_json_encode($cat_arr) : wp_json_encode([]);
        } else {
            $category = wp_json_encode((array)$raw_cat);
        }

        $brreg = isset($data['brreg_data']) && $data['brreg_data']
            ? (is_string($data['brreg_data']) ? $data['brreg_data'] : wp_json_encode($data['brreg_data']))
            : null;

        $fields = [
            'type'       => sanitize_text_field($data['type']    ?? 'company'),
            'name'       => sanitize_text_field($data['name']    ?? ''),
            'org_nr'     => sanitize_text_field($data['org_nr']  ?? ''),
            'email'      => sanitize_email($data['email']        ?? ''),
            'phone'      => sanitize_text_field($data['phone']   ?? ''),
            'address'    => sanitize_textarea_field($data['address'] ?? ''),
            'category'   => $category,
            'status'     => sanitize_text_field($data['status']  ?? 'active'),
            'notes'      => sanitize_textarea_field($data['notes'] ?? ''),
            'brreg_data' => $brreg,
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
        return (bool) $wpdb->delete($wpdb->prefix . 'edifice_contacts', ['id' => $id]);
    }

    public static function ajax_save() {
        check_ajax_referer('edifice_nonce', 'nonce');
        $id = self::save($_POST);
        $id ? wp_send_json_success(['id' => $id]) : wp_send_json_error('Lagring feilet');
    }

    public static function ajax_delete() {
        check_ajax_referer('edifice_nonce', 'nonce');
        self::delete((int)($_POST['id'] ?? 0))
            ? wp_send_json_success()
            : wp_send_json_error('Sletting feilet');
    }
}
