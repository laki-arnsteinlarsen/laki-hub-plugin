<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Network — Nettverksoppfølgning (Tier 1-4 system).
 *
 * Bygger på edifice_contacts. Tier-felt er lagt til via Migration 14.
 *
 * - Tier 1 = nære, viktige kontakter (månedlig pleie)
 * - Tier 2 = aktive bransjekontakter (kvartalsvis)
 * - Tier 3 = løse forbindelser (halvårlig)
 * - Tier 4 = passive (følg med, ikke aktiv pleie)
 * - tier IS NULL = ikke kategorisert som nettverkskontakt
 *
 * Modulen tilbyr passiv liste "trenger oppfølging" basert på tier_next_action.
 * Ingen push-varsler — du må selv åpne fanen.
 */
class Edifice_Network {

    const TIERS = [
        1 => ['label' => 'Tier 1', 'emoji' => '🟢', 'desc' => 'Nære — månedlig'],
        2 => ['label' => 'Tier 2', 'emoji' => '🔵', 'desc' => 'Aktive — kvartalsvis'],
        3 => ['label' => 'Tier 3', 'emoji' => '🟡', 'desc' => 'Løse — halvårlig'],
        4 => ['label' => 'Tier 4', 'emoji' => '⚪', 'desc' => 'Passive — ingen pleie'],
    ];

    const FREQUENCIES = [
        'Ukentlig', 'Månedlig', 'Kvartalsvis', 'Halvårlig', 'Årlig', 'Ad hoc',
    ];

    public static function init() {
        add_action('wp_ajax_edifice_network_save', [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_edifice_network_clear', [__CLASS__, 'ajax_clear']);
        add_action('wp_ajax_edifice_network_log_contact', [__CLASS__, 'ajax_log_contact']);
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    /** Alle kontakter med tier satt, gruppert per tier. */
    public static function get_grouped(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        $rows = $wpdb->get_results(
            "SELECT id, name, type, company_id, tier, tier_frequency,
                    tier_last_contact, tier_next_action, tier_next_action_note,
                    tier_relation_note, email, phone, mobile, linkedin_url
             FROM `$t`
             WHERE tier IS NOT NULL
             ORDER BY tier ASC, name ASC",
            ARRAY_A
        );

        $grouped = [1 => [], 2 => [], 3 => [], 4 => []];
        foreach ($rows as $r) {
            $tier = (int) $r['tier'];
            if (isset($grouped[$tier])) {
                // Augment med selskap hvis personkontakt
                if ($r['type'] === 'person' && $r['company_id']) {
                    $company = $wpdb->get_var($wpdb->prepare(
                        "SELECT name FROM `$t` WHERE id = %d", $r['company_id']
                    ));
                    $r['company_name'] = $company;
                }
                $grouped[$tier][] = $r;
            }
        }
        return $grouped;
    }

    /** Kontakter som trenger oppfølging (next_action <= today + window days). */
    public static function get_due_followups(int $window_days = 7): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        $cutoff = date('Y-m-d', strtotime("+$window_days days"));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, tier, tier_next_action, tier_next_action_note,
                    tier_last_contact, type, company_id
             FROM `$t`
             WHERE tier IS NOT NULL
               AND tier_next_action IS NOT NULL
               AND tier_next_action <= %s
             ORDER BY tier_next_action ASC, tier ASC, name ASC",
            $cutoff
        ), ARRAY_A);
    }

    /** Statistikk for dashbord / oversikt. */
    public static function get_stats(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';

        $counts = $wpdb->get_results(
            "SELECT tier, COUNT(*) as n FROM `$t`
             WHERE tier IS NOT NULL GROUP BY tier ORDER BY tier",
            OBJECT_K
        );

        $today = date('Y-m-d');
        $due_today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$t`
             WHERE tier IS NOT NULL AND tier_next_action IS NOT NULL
               AND tier_next_action <= %s",
            $today
        ));

        $due_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$t`
             WHERE tier IS NOT NULL AND tier_next_action IS NOT NULL
               AND tier_next_action <= %s",
            date('Y-m-d', strtotime('+7 days'))
        ));

        return [
            'tier_counts' => $counts,
            'due_today'   => $due_today,
            'due_week'    => $due_week,
            'total'       => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `$t` WHERE tier IS NOT NULL"
            ),
        ];
    }

    // ── AJAX handlers ────────────────────────────────────────────────────────

    /** Lagre tier-data på en kontakt. */
    public static function ajax_save() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');

        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';

        $contact_id = (int) ($_POST['contact_id'] ?? 0);
        if (! $contact_id) wp_send_json_error('missing contact_id');

        $tier = isset($_POST['tier']) && $_POST['tier'] !== ''
            ? (int) $_POST['tier']
            : null;

        if ($tier !== null && ! isset(self::TIERS[$tier])) {
            wp_send_json_error('invalid tier');
        }

        $data = [
            'tier'                  => $tier,
            'tier_frequency'        => $tier ? sanitize_text_field($_POST['tier_frequency'] ?? '') : null,
            'tier_last_contact'     => self::sanitize_date($_POST['tier_last_contact'] ?? ''),
            'tier_next_action'      => self::sanitize_date($_POST['tier_next_action'] ?? ''),
            'tier_next_action_note' => $tier ? sanitize_text_field($_POST['tier_next_action_note'] ?? '') : null,
            'tier_relation_note'    => $tier ? sanitize_textarea_field($_POST['tier_relation_note'] ?? '') : null,
        ];

        $result = $wpdb->update($t, $data, ['id' => $contact_id]);
        if ($result === false) {
            wp_send_json_error('db error: ' . $wpdb->last_error);
        }
        wp_send_json_success(['contact_id' => $contact_id, 'tier' => $tier]);
    }

    /** Fjern kontakt fra nettverket (tier = NULL). */
    public static function ajax_clear() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');

        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        $contact_id = (int) ($_POST['contact_id'] ?? 0);
        if (! $contact_id) wp_send_json_error('missing contact_id');

        $wpdb->update($t, [
            'tier'                  => null,
            'tier_frequency'        => null,
            'tier_last_contact'     => null,
            'tier_next_action'      => null,
            'tier_next_action_note' => null,
            'tier_relation_note'    => null,
        ], ['id' => $contact_id]);

        wp_send_json_success(['contact_id' => $contact_id]);
    }

    /** Logg ny kontakt — setter tier_last_contact = i dag, ruller next_action. */
    public static function ajax_log_contact() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');

        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        $contact_id = (int) ($_POST['contact_id'] ?? 0);
        if (! $contact_id) wp_send_json_error('missing contact_id');

        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT tier_frequency FROM `$t` WHERE id = %d", $contact_id
        ));
        if (! $contact) wp_send_json_error('not found');

        $today = date('Y-m-d');
        $next = self::next_action_from_frequency($contact->tier_frequency, $today);

        $wpdb->update($t, [
            'tier_last_contact' => $today,
            'tier_next_action'  => $next,
        ], ['id' => $contact_id]);

        wp_send_json_success([
            'contact_id'      => $contact_id,
            'last_contact'    => $today,
            'next_action'     => $next,
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

    public static function next_action_from_frequency(?string $freq, string $from_date): ?string {
        if (! $freq) return null;
        $offsets = [
            'Ukentlig'    => '+1 week',
            'Månedlig'    => '+1 month',
            'Kvartalsvis' => '+3 months',
            'Halvårlig'   => '+6 months',
            'Årlig'       => '+1 year',
            'Ad hoc'      => null,
        ];
        $offset = $offsets[$freq] ?? null;
        if (! $offset) return null;
        return date('Y-m-d', strtotime("$from_date $offset"));
    }

    public static function format_date_norwegian(?string $iso): string {
        if (! $iso) return '—';
        $ts = strtotime($iso);
        if ($ts === false) return $iso;
        return date('d.m.Y', $ts);
    }

    public static function days_until(?string $iso): ?int {
        if (! $iso) return null;
        $ts = strtotime($iso);
        if ($ts === false) return null;
        $today = strtotime(date('Y-m-d'));
        return (int) (($ts - $today) / 86400);
    }
}
