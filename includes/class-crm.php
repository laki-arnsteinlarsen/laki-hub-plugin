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
     * Splitt et lagret telefonnummer (E.164 "+4791234567") til
     * landskode + nasjonalt nummer. Brukes for å pre-utfylle skjemaet.
     * Lengste prefiks vinner ved kollisjon. Default: +47.
     * Tåler både gammelt format ("+47 91 23 45 67") og nytt ("+4791234567").
     */
    public static function split_phone(string $phone): array {
        $phone = trim($phone);
        if ($phone === '') {
            return ['cc' => '+47', 'national' => ''];
        }
        if ($phone[0] !== '+') {
            return ['cc' => '+47', 'national' => preg_replace('/\s+/', '', $phone)];
        }
        // Sortér ccs etter lengde DESC for å matche +358 før +35
        $codes = array_unique(array_column(self::country_codes(), 'cc'));
        usort($codes, fn($a, $b) => strlen($b) - strlen($a));
        foreach ($codes as $cc) {
            if (str_starts_with($phone, $cc)) {
                $national = preg_replace('/\s+/', '', substr($phone, strlen($cc)));
                return ['cc' => $cc, 'national' => $national];
            }
        }
        return ['cc' => '+47', 'national' => preg_replace('/\s+/', '', substr($phone, 1))];
    }

    /**
     * Formater et lagret telefonnummer for visning.
     * Norske nummer (+47, 8 siffer): "+47 91 23 45 67"
     * Andre: "+CC nasjonalt-nummer" uten ekstra formatering.
     */
    public static function format_phone(string $stored): string {
        if ($stored === '') return '';
        $split    = self::split_phone($stored);
        $cc       = $split['cc'];
        $national = $split['national'];

        if ($cc === '+47' && strlen($national) === 8 && ctype_digit($national)) {
            $pretty = $national[0] . $national[1] . ' '
                    . $national[2] . $national[3] . ' '
                    . $national[4] . $national[5] . ' '
                    . $national[6] . $national[7];
            return $cc . ' ' . $pretty;
        }
        return $national !== '' ? $cc . ' ' . $national : $cc;
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

    /** Dekod category fra JSON + attach related data (extra emails, companies) */
    private static function decode_row(array $row): array {
        $row['category'] = !empty($row['category'])
            ? (json_decode($row['category'], true) ?: [])
            : [];
        $row['extra_emails'] = self::get_extra_emails((int) $row['id']);
        $row['companies']    = $row['type'] === 'person'
            ? self::get_companies_for_person((int) $row['id'])
            : [];
        return $row;
    }

    /** Hent ekstra e-postadresser for en kontakt (utenom primær) */
    public static function get_extra_emails(int $contact_id): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contact_emails';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, email, label FROM `$t`
                 WHERE contact_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $contact_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /** Returner alle e-poster (primær + ekstra) som flat array av strenger */
    public static function get_all_emails_for_contact(int $contact_id): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        $primary = $wpdb->get_var($wpdb->prepare("SELECT email FROM `$t` WHERE id = %d", $contact_id));
        $extras  = self::get_extra_emails($contact_id);
        $all     = [];
        if ($primary)  $all[] = $primary;
        foreach ($extras as $e) {
            if (!empty($e['email'])) $all[] = $e['email'];
        }
        return array_values(array_unique($all));
    }

    /** Hent alle selskaper en person er tilknyttet */
    public static function get_companies_for_person(int $person_id): array {
        global $wpdb;
        $j  = $wpdb->prefix . 'edifice_contact_companies';
        $tc = $wpdb->prefix . 'edifice_contacts';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT j.id, j.company_id, j.role, j.is_primary, j.sort_order, c.name AS company_name
                 FROM `$j` j
                 LEFT JOIN `$tc` c ON c.id = j.company_id
                 WHERE j.person_id = %d
                 ORDER BY j.is_primary DESC, j.sort_order ASC, c.name ASC",
                $person_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Returns all persons linked to a company VIA junction-tabellen
     * edifice_contact_companies. Returnerer FULL record så drill-down
     * i view-modal kan åpne person-modalen direkte.
     */
    public static function get_persons_for_company(int $company_id): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        $j = $wpdb->prefix . 'edifice_contact_companies';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, comp.name AS company_name, j.role AS link_role, j.is_primary AS link_is_primary
                 FROM `$j` j
                 INNER JOIN `$t` c    ON c.id = j.person_id
                 LEFT  JOIN `$t` comp ON comp.id = j.company_id
                 WHERE j.company_id = %d AND c.type = 'person'
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

        // Telefon-felter: kombiner cc + national. Pure E.164 lagring uten mellomrom.
        // Brukes for både phone (fasttelefon/hovednummer) og mobile (mobil).
        $combine_phone = function (array $d, string $prefix): string {
            if (!empty($d[$prefix . '_national']) || !empty($d[$prefix . '_cc'])) {
                $cc = sanitize_text_field($d[$prefix . '_cc'] ?? '+47');
                $nat = preg_replace('/\s+/', '', sanitize_text_field($d[$prefix . '_national'] ?? ''));
                return $nat !== '' ? $cc . $nat : '';
            }
            if (isset($d[$prefix])) {
                return preg_replace('/\s+/', '', sanitize_text_field($d[$prefix]));
            }
            return '';
        };
        $phone  = $combine_phone($data, 'phone');
        $mobile = $combine_phone($data, 'mobile');

        // Selskaper: parse JSON-array fra form (companies). Først valg blir primær.
        // Format: [{company_id: 12, role: 'Styreleder'}, ...]
        $companies_input = [];
        if (!empty($data['companies_json'])) {
            $decoded = json_decode(stripslashes($data['companies_json']), true);
            if (is_array($decoded)) {
                foreach ($decoded as $c) {
                    if (empty($c['company_id'])) continue;
                    $companies_input[] = [
                        'company_id' => (int) $c['company_id'],
                        'role'       => sanitize_text_field($c['role'] ?? ''),
                    ];
                }
            }
        } elseif ($type === 'person' && !empty($data['company_id'])) {
            // Bakoverkomp: enkel company_id fra gammel form
            $companies_input[] = ['company_id' => (int) $data['company_id'], 'role' => ''];
        }

        // Primær company_id-cache (første link, NULL hvis ingen)
        $primary_company_id = null;
        if ($type === 'person' && !empty($companies_input)) {
            $primary_company_id = (int) $companies_input[0]['company_id'];
        }

        $fields = [
            'type'           => $type,
            'company_id'     => $primary_company_id,
            'name'           => sanitize_text_field($data['name']    ?? ''),
            'org_nr'         => sanitize_text_field($data['org_nr']  ?? ''),
            'email'          => sanitize_email($data['email']        ?? ''),
            'phone'          => $phone,
            'mobile'         => $mobile,
            'address'        => sanitize_textarea_field($data['address']        ?? ''),
            'postal_address' => sanitize_textarea_field($data['postal_address'] ?? ''),
            'category'       => $category,
            'status'         => sanitize_text_field($data['status']  ?? 'active'),
            'linkedin_url'   => self::normalize_url($data['linkedin_url']  ?? ''),
            'instagram_url'  => self::normalize_url($data['instagram_url'] ?? ''),
            'facebook_url'   => self::normalize_url($data['facebook_url']  ?? ''),
            'x_url'          => self::normalize_url($data['x_url']         ?? ''),
            'tiktok_url'     => self::normalize_url($data['tiktok_url']    ?? ''),
            'custom_url'     => self::normalize_url($data['custom_url']    ?? ''),
            'notes'          => sanitize_textarea_field($data['notes']     ?? ''),
            'brreg_data'     => $brreg,
        ];

        if (!empty($data['id'])) {
            $contact_id = (int) $data['id'];
            $wpdb->update($t, $fields, ['id' => $contact_id]);
        } else {
            $wpdb->insert($t, $fields);
            $contact_id = (int) $wpdb->insert_id;
        }
        if (!$contact_id) return false;

        // Sync selskap-linker (kun for personer)
        if ($type === 'person') {
            self::sync_company_links($contact_id, $companies_input);
        }
        // Sync ekstra e-poster
        self::sync_extra_emails($contact_id, $data['extra_emails_json'] ?? '');

        return $contact_id;
    }

    /** Erstatt alle person→selskap-linker for en person */
    private static function sync_company_links(int $person_id, array $links): void {
        global $wpdb;
        $j = $wpdb->prefix . 'edifice_contact_companies';
        $wpdb->delete($j, ['person_id' => $person_id]);
        foreach ($links as $i => $link) {
            $wpdb->insert($j, [
                'person_id'  => $person_id,
                'company_id' => (int) $link['company_id'],
                'role'       => $link['role'] ?? '',
                'is_primary' => $i === 0 ? 1 : 0,
                'sort_order' => $i,
            ]);
        }
    }

    /** Erstatt alle ekstra e-poster for en kontakt */
    private static function sync_extra_emails(int $contact_id, string $json): void {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contact_emails';
        $wpdb->delete($t, ['contact_id' => $contact_id]);
        $decoded = json_decode(stripslashes($json), true);
        if (!is_array($decoded)) return;
        $i = 0;
        foreach ($decoded as $e) {
            $email = sanitize_email($e['email'] ?? '');
            if (!$email) continue;
            $wpdb->insert($t, [
                'contact_id' => $contact_id,
                'email'      => $email,
                'label'      => sanitize_text_field($e['label'] ?? ''),
                'sort_order' => $i++,
            ]);
        }
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
