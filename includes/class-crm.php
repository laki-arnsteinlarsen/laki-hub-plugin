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

    /**
     * Kuratert liste over landskoder for telefonnummer.
     * Format: ['cc' => '+47', 'flag' => '🇳🇴', 'name' => 'Norge'].
     * Norge ligger først som standard. Resten alfabetisk på norsk navn.
     */
    public static function country_codes(): array {
        return [
            ['cc' => '+47',  'flag' => '🇳🇴', 'name' => 'Norge'],
            ['cc' => '+61',  'flag' => '🇦🇺', 'name' => 'Australia'],
            ['cc' => '+32',  'flag' => '🇧🇪', 'name' => 'Belgia'],
            ['cc' => '+55',  'flag' => '🇧🇷', 'name' => 'Brasil'],
            ['cc' => '+1',   'flag' => '🇨🇦', 'name' => 'Canada'],
            ['cc' => '+45',  'flag' => '🇩🇰', 'name' => 'Danmark'],
            ['cc' => '+372', 'flag' => '🇪🇪', 'name' => 'Estland'],
            ['cc' => '+358', 'flag' => '🇫🇮', 'name' => 'Finland'],
            ['cc' => '+33',  'flag' => '🇫🇷', 'name' => 'Frankrike'],
            ['cc' => '+30',  'flag' => '🇬🇷', 'name' => 'Hellas'],
            ['cc' => '+91',  'flag' => '🇮🇳', 'name' => 'India'],
            ['cc' => '+353', 'flag' => '🇮🇪', 'name' => 'Irland'],
            ['cc' => '+354', 'flag' => '🇮🇸', 'name' => 'Island'],
            ['cc' => '+39',  'flag' => '🇮🇹', 'name' => 'Italia'],
            ['cc' => '+81',  'flag' => '🇯🇵', 'name' => 'Japan'],
            ['cc' => '+86',  'flag' => '🇨🇳', 'name' => 'Kina'],
            ['cc' => '+371', 'flag' => '🇱🇻', 'name' => 'Latvia'],
            ['cc' => '+370', 'flag' => '🇱🇹', 'name' => 'Litauen'],
            ['cc' => '+52',  'flag' => '🇲🇽', 'name' => 'Mexico'],
            ['cc' => '+31',  'flag' => '🇳🇱', 'name' => 'Nederland'],
            ['cc' => '+48',  'flag' => '🇵🇱', 'name' => 'Polen'],
            ['cc' => '+351', 'flag' => '🇵🇹', 'name' => 'Portugal'],
            ['cc' => '+34',  'flag' => '🇪🇸', 'name' => 'Spania'],
            ['cc' => '+44',  'flag' => '🇬🇧', 'name' => 'Storbritannia'],
            ['cc' => '+41',  'flag' => '🇨🇭', 'name' => 'Sveits'],
            ['cc' => '+46',  'flag' => '🇸🇪', 'name' => 'Sverige'],
            ['cc' => '+420', 'flag' => '🇨🇿', 'name' => 'Tsjekkia'],
            ['cc' => '+49',  'flag' => '🇩🇪', 'name' => 'Tyskland'],
            ['cc' => '+1',   'flag' => '🇺🇸', 'name' => 'USA'],
            ['cc' => '+43',  'flag' => '🇦🇹', 'name' => 'Østerrike'],
        ];
    }

    /**
     * Splitt et lagret telefonnummer (E.164-ish "+47 91 23 45 67") til
     * landskode + nasjonalt nummer. Brukes for å pre-utfylle skjemaet.
     * Lengste prefiks vinner ved kollisjon. Default: +47.
     */
    public static function split_phone(string $phone): array {
        $phone = trim($phone);
        if ($phone === '') {
            return ['cc' => '+47', 'national' => ''];
        }
        if ($phone[0] !== '+') {
            // Faller tilbake — burde ikke skje etter migrasjon
            return ['cc' => '+47', 'national' => $phone];
        }
        // Sortér ccs etter lengde DESC for å matche +358 før +35
        $codes = array_unique(array_column(self::country_codes(), 'cc'));
        usort($codes, fn($a, $b) => strlen($b) - strlen($a));
        foreach ($codes as $cc) {
            if (str_starts_with($phone, $cc)) {
                $national = trim(substr($phone, strlen($cc)));
                return ['cc' => $cc, 'national' => $national];
            }
        }
        return ['cc' => '+47', 'national' => substr($phone, 1)]; // ukjent prefiks, drop +
    }

    /**
     * Normaliser en URL: prepend https:// hvis brukeren glemte schema.
     * Tom string returnerer null for å rydde DB.
     */
    private static function normalize_url(?string $raw): ?string {
        $raw = trim((string) $raw);
        if ($raw === '') return null;
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        return esc_url_raw($raw);
    }

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

    /**
     * Returns all persons linked to a company. Returnerer FULL record så
     * drill-down i view-modal kan åpne person-modalen direkte.
     */
    public static function get_persons_for_company(int $company_id): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, p.name AS company_name
                 FROM `$t` c
                 LEFT JOIN `$t` p ON p.id = c.company_id
                 WHERE c.type = 'person' AND c.company_id = %d
                 ORDER BY c.name ASC",
                $company_id
            ),
            ARRAY_A
        ) ?: [];
        return array_map([__CLASS__, 'decode_row'], $rows);
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

        // Telefon: kombiner phone_cc + phone_national hvis sendt fra ny form,
        // ellers fall tilbake til 'phone'-feltet (bakoverkompatibilitet).
        $phone = '';
        if (!empty($data['phone_national']) || !empty($data['phone_cc'])) {
            $cc       = sanitize_text_field($data['phone_cc'] ?? '+47');
            $national = trim(sanitize_text_field($data['phone_national'] ?? ''));
            $phone    = $national !== '' ? $cc . ' ' . $national : '';
        } elseif (isset($data['phone'])) {
            $phone = sanitize_text_field($data['phone']);
        }

        $fields = [
            'type'          => $type,
            'company_id'    => ($type === 'person' && !empty($data['company_id']))
                                  ? (int) $data['company_id'] : null,
            'name'          => sanitize_text_field($data['name']    ?? ''),
            'org_nr'        => sanitize_text_field($data['org_nr']  ?? ''),
            'email'         => sanitize_email($data['email']        ?? ''),
            'phone'         => $phone,
            'address'       => sanitize_textarea_field($data['address'] ?? ''),
            'category'      => $category,
            'status'        => sanitize_text_field($data['status']  ?? 'active'),
            'linkedin_url'  => self::normalize_url($data['linkedin_url']  ?? ''),
            'instagram_url' => self::normalize_url($data['instagram_url'] ?? ''),
            'facebook_url'  => self::normalize_url($data['facebook_url']  ?? ''),
            'x_url'         => self::normalize_url($data['x_url']         ?? ''),
            'tiktok_url'    => self::normalize_url($data['tiktok_url']    ?? ''),
            'custom_url'    => self::normalize_url($data['custom_url']    ?? ''),
            'notes'         => sanitize_textarea_field($data['notes'] ?? ''),
            'brreg_data'    => $brreg,
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
