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

    // ── Queries ────────────────────────────────────────────────────────────

    public static function get_all(array $args = []): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';

        // LEFT JOIN to pull parent company name for persons
        $sql = "SELECT c.*, p.name AS company_name
                FROM `$t` c
                LEFT JOIN `$t` p ON p.id = c.company_id
                WHERE 1=1";

        if (!empty($args['status']))
            $sql .= $wpdb->prepare(' AND c.status = %s', $args['status']);
        if (!empty($args['type']))
            $sql .= $wpdb->prepare(' AND c.type = %s', $args['type']);
        if (!empty($args['search']))
            $sql .= $wpdb->prepare(
                ' AND (c.name LIKE %s OR c.org_nr LIKE %s OR c.email LIKE %s)',
                '%' . $args['search'] . '%',
                '%' . $args['search'] . '%',
                '%' . $args['search'] . '%'
            );

        $sql .= ' ORDER BY c.name ASC';
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map([__CLASS__, 'decode_row'], $rows);
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $t   = $wpdb->prefix . 'edifice_contacts';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*, p.name AS company_name
                 FROM `$t` c
                 LEFT JOIN `$t` p ON p.id = c.company_id
                 WHERE c.id = %d",
                $id
            ),
            ARRAY_A
        );
        return $row ? self::decode_row($row) : null;
    }

    /** Dekod category fra JSON til array */
    private static function decode_row(array $row): array {
        $row['category'] = !empty($row['category'])
            ? (json_decode($row['category'], true) ?: [])
            : [];
        return $row;
    }

    /** Returns all persons linked to a company */
    public static function get_persons_for_company(int $company_id): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, email, phone, category, status
                 FROM `$t`
                 WHERE type = 'person' AND company_id = %d
                 ORDER BY name ASC",
                $company_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /** Returns all company-type contacts (for person-form dropdown) */
    public static function get_companies(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        return $wpdb->get_results(
            "SELECT id, name FROM `$t` WHERE type = 'company' ORDER BY name ASC",
            ARRAY_A
        ) ?: [];
    }

    // ── Save / delete ──────────────────────────────────────────────────────

    public static function save(array $data): int|false {
        global $wpdb;
        $t    = $wpdb->prefix . 'edifice_contacts';
        $type = sanitize_text_field($data['type'] ?? 'company');

        // category comes as JSON string from the form
        $raw_cat = $data['category'] ?? '';
        if (is_string($raw_cat)) {
            $cat_arr  = json_decode(stripslashes($raw_cat), true);
            $category = is_array($cat_arr) ? wp_json_encode($cat_arr) : wp_json_encode([]);
        } else {
            $category = wp_json_encode((array) $raw_cat);
        }

        $brreg = isset($data['brreg_data']) && $data['brreg_data']
            ? (is_string($data['brreg_data']) ? $data['brreg_data'] : wp_json_encode($data['brreg_data']))
            : null;

        $fields = [
            'type'       => $type,
            'company_id' => ($type === 'person' && !empty($data['company_id']))
                                ? (int) $data['company_id'] : null,
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
            $wpdb->update($t, $fields, ['id' => (int) $data['id']]);
            return (int) $data['id'];
        }
        $wpdb->insert($t, $fields);
        return $wpdb->insert_id ?: false;
    }

    public static function delete(int $id): bool {
        global $wpdb;
        // Unlink any persons that belonged to this company
        $wpdb->update(
            $wpdb->prefix . 'edifice_contacts',
            ['company_id' => null],
            ['company_id' => $id]
        );
        return (bool) $wpdb->delete($wpdb->prefix . 'edifice_contacts', ['id' => $id]);
    }

    // ── AJAX ───────────────────────────────────────────────────────────────

    public static function ajax_save(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        $id = self::save($_POST);
        $id ? wp_send_json_success(['id' => $id]) : wp_send_json_error('Lagring feilet');
    }

    public static function ajax_delete(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        $id = (int) ($_POST['id'] ?? 0);
        self::delete($id) ? wp_send_json_success() : wp_send_json_error('Sletting feilet');
    }

    public static function ajax_get_persons(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        $company_id = (int) ($_POST['company_id'] ?? 0);
        if (!$company_id) { wp_send_json_error('Mangler company_id'); return; }
        wp_send_json_success(self::get_persons_for_company($company_id));
    }
}
