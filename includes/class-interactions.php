<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Interactions — strukturert logg over kontaktinteraksjoner.
 *
 * Bygger på tabellen edifice_contact_interactions (Migration 15).
 *
 * En interaksjon = én konkret hendelse mellom Arnstein og en kontakt.
 *   - dato (obligatorisk), tid (valgfri)
 *   - kanal: sms / epost / telefon / mote / lunsj / kaffe / linkedin / dm / annet
 *   - retning: inn / ut / toveis
 *   - sammendrag (kort), notat (lengre, valgfritt)
 *   - kilde: manuell / gmail / kalender / slack / annet
 *   - ekstern_ref: ID i kildesystemet (for dedup ved auto-synk)
 *
 * Når en interaksjon lagres, oppdateres edifice_contacts.tier_last_contact
 * automatisk til MAX(dato) for kontakten. Hvis tier_frequency er satt,
 * rulles også tier_next_action.
 */
class Edifice_Interactions {

    const KANALER = [
        'sms'      => '💬 SMS',
        'epost'    => '✉️ E-post',
        'telefon'  => '📞 Telefon',
        'mote'     => '🤝 Møte',
        'lunsj'    => '🍽️ Lunsj',
        'kaffe'    => '☕ Kaffe',
        'linkedin' => '💼 LinkedIn',
        'dm'       => '📩 DM',
        'annet'    => '· Annet',
    ];

    const RETNINGER = [
        'inn'    => '← Inn',
        'ut'     => '→ Ut',
        'toveis' => '↔ Toveis',
    ];

    const KILDER = [
        'manuell'  => 'Manuell',
        'gmail'    => 'Gmail',
        'kalender' => 'Kalender',
        'slack'    => 'Slack',
        'imessage' => 'iMessage',
        'annet'    => 'Annet',
    ];

    public static function init() {
        add_action('wp_ajax_edifice_interaction_log',    [__CLASS__, 'ajax_log']);
        add_action('wp_ajax_edifice_interaction_delete', [__CLASS__, 'ajax_delete']);
        add_action('wp_ajax_edifice_interaction_list',   [__CLASS__, 'ajax_list']);
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    /** Hent alle interaksjoner for en kontakt, nyeste først. */
    public static function get_for_contact(int $contact_id, int $limit = 50): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contact_interactions';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, contact_id, project_id, dato, tid, kanal, retning,
                    sammendrag, notat, kilde, ekstern_ref, created_at
             FROM `$t`
             WHERE contact_id = %d
             ORDER BY dato DESC, tid DESC, id DESC
             LIMIT %d",
            $contact_id, $limit
        ), ARRAY_A);
    }

    /** Sjekk om en ekstern_ref allerede finnes (for dedup ved auto-synk). */
    public static function exists_by_ref(string $kilde, string $ekstern_ref): bool {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contact_interactions';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM `$t` WHERE kilde = %s AND ekstern_ref = %s LIMIT 1",
            $kilde, $ekstern_ref
        ));
    }

    // ── Mutators ─────────────────────────────────────────────────────────────

    /**
     * Legg til en ny interaksjon. Returnerer ny ID, eller WP_Error ved feil.
     * Oppdaterer også tier_last_contact og tier_next_action på kontakten.
     */
    public static function add(array $data) {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contact_interactions';

        $contact_id = (int) ($data['contact_id'] ?? 0);
        if (! $contact_id) return new WP_Error('missing_contact_id', 'contact_id mangler');

        $dato = self::sanitize_date($data['dato'] ?? '');
        if (! $dato) return new WP_Error('missing_dato', 'dato mangler eller ugyldig');

        $kanal = $data['kanal'] ?? '';
        if (! isset(self::KANALER[$kanal])) return new WP_Error('invalid_kanal', 'ugyldig kanal');

        $retning = $data['retning'] ?? 'toveis';
        if (! isset(self::RETNINGER[$retning])) $retning = 'toveis';

        $kilde = $data['kilde'] ?? 'manuell';
        if (! isset(self::KILDER[$kilde])) $kilde = 'manuell';

        $sammendrag = trim((string) ($data['sammendrag'] ?? ''));
        if (! $sammendrag) return new WP_Error('missing_sammendrag', 'sammendrag mangler');

        $row = [
            'contact_id' => $contact_id,
            'project_id' => isset($data['project_id']) && $data['project_id']
                ? (int) $data['project_id'] : null,
            'dato'       => $dato,
            'tid'        => self::sanitize_time($data['tid'] ?? ''),
            'kanal'      => $kanal,
            'retning'    => $retning,
            'sammendrag' => $sammendrag,
            'notat'      => isset($data['notat']) ? sanitize_textarea_field($data['notat']) : null,
            'kilde'      => $kilde,
            'ekstern_ref'=> isset($data['ekstern_ref']) && $data['ekstern_ref']
                ? sanitize_text_field($data['ekstern_ref']) : null,
        ];

        $ok = $wpdb->insert($t, $row);
        if ($ok === false) {
            return new WP_Error('db_error', 'DB-feil: ' . $wpdb->last_error);
        }

        $new_id = (int) $wpdb->insert_id;
        self::recompute_contact_cache($contact_id);
        return $new_id;
    }

    /** Slett interaksjon. */
    public static function delete(int $id): bool {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contact_interactions';
        $contact_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT contact_id FROM `$t` WHERE id = %d", $id
        ));
        if (! $contact_id) return false;
        $wpdb->delete($t, ['id' => $id]);
        self::recompute_contact_cache($contact_id);
        return true;
    }

    /**
     * Oppdater denormaliserte felt på kontakten basert på interaksjonsloggen.
     * Setter tier_last_contact = MAX(dato) for kontakten.
     * Hvis tier_frequency er satt, rulles tier_next_action fra siste dato.
     */
    public static function recompute_contact_cache(int $contact_id): void {
        global $wpdb;
        $it = $wpdb->prefix . 'edifice_contact_interactions';
        $ct = $wpdb->prefix . 'edifice_contacts';

        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(dato) FROM `$it` WHERE contact_id = %d",
            $contact_id
        ));

        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT tier_frequency FROM `$ct` WHERE id = %d",
            $contact_id
        ));

        $update = ['tier_last_contact' => $last ?: null];
        if ($contact && $contact->tier_frequency && $last) {
            $next = Edifice_Network::next_action_from_frequency($contact->tier_frequency, $last);
            if ($next) $update['tier_next_action'] = $next;
        }
        $wpdb->update($ct, $update, ['id' => $contact_id]);
    }

    // ── AJAX handlers ────────────────────────────────────────────────────────

    public static function ajax_log() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');

        $result = self::add([
            'contact_id' => $_POST['contact_id'] ?? 0,
            'dato'       => $_POST['dato'] ?? '',
            'tid'        => $_POST['tid'] ?? '',
            'kanal'      => $_POST['kanal'] ?? '',
            'retning'    => $_POST['retning'] ?? 'toveis',
            'sammendrag' => $_POST['sammendrag'] ?? '',
            'notat'      => $_POST['notat'] ?? '',
            'project_id' => $_POST['project_id'] ?? 0,
            'kilde'      => 'manuell',
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        $contact_id = (int) ($_POST['contact_id'] ?? 0);
        wp_send_json_success([
            'id'              => $result,
            'interactions'    => self::get_for_contact($contact_id),
            'last_contact'    => self::get_last_contact_date($contact_id),
        ]);
    }

    public static function ajax_delete() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');
        $id = (int) ($_POST['id'] ?? 0);
        if (! $id) wp_send_json_error('missing id');
        $ok = self::delete($id);
        if (! $ok) wp_send_json_error('not found');
        wp_send_json_success(['id' => $id]);
    }

    public static function ajax_list() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');
        $contact_id = (int) ($_POST['contact_id'] ?? 0);
        if (! $contact_id) wp_send_json_error('missing contact_id');
        wp_send_json_success([
            'interactions' => self::get_for_contact($contact_id),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function sanitize_date(string $d): ?string {
        $d = trim($d);
        if (! $d) return null;
        $ts = strtotime($d);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
    }

    private static function sanitize_time(string $t): ?string {
        $t = trim($t);
        if (! $t) return null;
        // Aksepterer HH:MM eller HH:MM:SS
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t)) {
            $parts = explode(':', $t);
            return sprintf('%02d:%02d:%02d',
                (int)$parts[0], (int)$parts[1], (int)($parts[2] ?? 0));
        }
        return null;
    }

    private static function get_last_contact_date(int $contact_id): ?string {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contact_interactions';
        $d = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(dato) FROM `$t` WHERE contact_id = %d",
            $contact_id
        ));
        return $d ?: null;
    }

    public static function format_date_norwegian(?string $iso): string {
        if (! $iso) return '—';
        $ts = strtotime($iso);
        if ($ts === false) return $iso;
        return date('d.m.Y', $ts);
    }

    public static function format_time_norwegian(?string $iso): string {
        if (! $iso) return '';
        if (preg_match('/^(\d{1,2}):(\d{2})/', $iso, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }
        return $iso;
    }
}
