<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Sync_iMessage — bulk-import av iMessage-tråder til interaksjonsloggen.
 *
 * Cowork-skillet `imessage-sync` orkestrer:
 *   1. Henter kontakter med phone/mobile fra Edifice
 *   2. Kaller iMessage MCP `read_imessages(phone_number)` per kontakt
 *   3. POSTer batch hit
 *
 * Vi:
 *   - Stripper attributedBody-støy (NSDictionary/bplist00) som chat.db etterlater
 *     i utgående meldinger
 *   - Mapper is_from_me → retning (ut/inn)
 *   - Bruker iMessage ROWID som ekstern_ref for dedup ved gjentatte kjøringer
 *   - Hopper over tomme/junk-rader (reaksjoner, tale-meldinger, vedlegg-only)
 */
class Edifice_Sync_iMessage {

    public static function init() {
        add_action('wp_ajax_edifice_imessage_bulk_import', [__CLASS__, 'ajax_bulk_import']);
        add_action('wp_ajax_edifice_sync_get_phone_contacts', [__CLASS__, 'ajax_get_phone_contacts']);
        // CLI-vennlige endpoints (uten nonce, auth via edifice_key):
        add_action('wp_ajax_nopriv_edifice_imessage_bulk_import', [__CLASS__, 'ajax_bulk_import']);
        add_action('wp_ajax_nopriv_edifice_sync_get_phone_contacts', [__CLASS__, 'ajax_get_phone_contacts']);
    }

    /**
     * Auth via enten WP-nonce (browser) eller edifice_key POST-param (CLI).
     * Returnerer true ved gyldig auth, kaller wp_send_json_error() ellers.
     */
    private static function authenticate() {
        // CLI-path: edifice_key
        if (isset($_POST['edifice_key'])) {
            $stored = get_option('edifice_autologin_key', '');
            $given = sanitize_text_field($_POST['edifice_key']);
            if ($stored && hash_equals($stored, $given)) {
                $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
                if (!empty($admins)) {
                    wp_set_current_user((int) $admins[0]);
                    return true;
                }
            }
            wp_send_json_error('invalid edifice_key');
        }
        // Browser-path: nonce
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error('forbidden');
        }
        return true;
    }

    public static function ajax_get_phone_contacts() {
        self::authenticate();
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_contacts';
        $rows = $wpdb->get_results(
            "SELECT id, name, tier, phone, mobile
             FROM `$t`
             WHERE tier IS NOT NULL
               AND ( (phone IS NOT NULL AND phone <> '')
                  OR (mobile IS NOT NULL AND mobile <> '') )
             ORDER BY tier ASC, name ASC",
            ARRAY_A
        );
        $out = [];
        foreach ($rows as $r) {
            // Foretrekk mobile (iMessage primært) ellers phone
            $num = !empty($r['mobile']) ? $r['mobile'] : $r['phone'];
            // Normaliser: kun + og siffer
            $num = preg_replace('/[^+\d]/', '', $num);
            if (!$num || $num[0] !== '+') continue;
            $out[] = [
                'id'    => (int) $r['id'],
                'name'  => $r['name'],
                'tier'  => (int) $r['tier'],
                'phone' => $num,
            ];
        }
        wp_send_json_success($out);
    }

    public static function ajax_bulk_import() {
        self::authenticate();

        $contact_id = (int) ($_POST['contact_id'] ?? 0);
        $raw = $_POST['messages'] ?? '';
        if (is_string($raw)) $raw = wp_unslash($raw);
        $messages = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! $contact_id || ! is_array($messages)) {
            wp_send_json_error('invalid input: trenger contact_id og messages-array');
        }

        $added = 0;
        $skipped_dedup = 0;
        $skipped_empty = 0;
        $errors = [];

        foreach ($messages as $m) {
            // ekstern_ref: bruk message_id hvis MCP-en leverer det; ellers utled
            // en stabil hash av timestamp + is_from_me som dedup-nøkkel.
            $date_iso = (string) ($m['date'] ?? '');
            $msg_id = isset($m['message_id']) ? (string) $m['message_id'] : '';
            if (! $msg_id) {
                if (! $date_iso) { $skipped_empty++; continue; }
                $is_out = ! empty($m['is_from_me']) ? '1' : '0';
                // Format: <ISO-dato>T<tid>:<0|1>  → stabil og lesbar
                $msg_id = $date_iso . ':' . $is_out;
            }

            if (Edifice_Interactions::exists_by_ref('imessage', $msg_id)) {
                $skipped_dedup++;
                continue;
            }

            $content = self::clean_content($m['content'] ?? '');
            if (! trim($content)) { $skipped_empty++; continue; }

            $dato = substr($date_iso, 0, 10);
            $tid  = strlen($date_iso) >= 19 ? substr($date_iso, 11, 8) : null;
            if (! $dato || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dato)) {
                $errors[] = $msg_id . ': ugyldig dato (' . substr($date_iso, 0, 30) . ')';
                continue;
            }

            $retning = ! empty($m['is_from_me']) ? 'ut' : 'inn';
            $sammendrag = mb_substr($content, 0, 500);
            $notat = mb_strlen($content) > 500 ? $content : null;

            $result = Edifice_Interactions::add([
                'contact_id'  => $contact_id,
                'dato'        => $dato,
                'tid'         => $tid,
                'kanal'       => 'sms',
                'retning'     => $retning,
                'sammendrag'  => $sammendrag,
                'notat'       => $notat,
                'kilde'       => 'imessage',
                'ekstern_ref' => $msg_id,
            ]);

            if (is_wp_error($result)) {
                $errors[] = $msg_id . ': ' . $result->get_error_message();
            } else {
                $added++;
            }
        }

        wp_send_json_success([
            'contact_id'    => $contact_id,
            'added'         => $added,
            'skipped_dedup' => $skipped_dedup,
            'skipped_empty' => $skipped_empty,
            'errors'        => $errors,
            'total_input'   => count($messages),
        ]);
    }

    /**
     * Strip iMessage chat.db-støy. Utgående meldinger har attributedBody-binærdata
     * som lekker inn i text-kolonnen (NSDictionary/bplist00-blokker), pluss
     * reaksjonsprefiks ("Likte ...", "Lo av ...") og kontroll-tegn.
     */
    public static function clean_content(string $content): string {
        if ($content === '') return '';

        // 1. Fjern NSDictionary/bplist-blokker (alt etter "iI" hvis det dukker opp)
        //    iI er starten på Apple typedstream-markeren.
        $content = preg_replace('/\s*iI[\s\S]*$/u', '', $content);

        // 2. Fjern reaksjons-/sitat-prefiks som iMessage prepender
        //    Eksempel: ">Likte Sorry. Går nå.", "*Likte ..." , "4Takk. 9. april ..."
        $content = preg_replace('/^["\>\*\<]+/u', '', $content);
        // Likte / Lo av / Elsker / Synd om / Lo av / Spurte (norske reaksjons-prefiks)
        $content = preg_replace('/^(Likte|Lo\s+av|Elsker|Synd\s+om|Spurte|Liked|Loved|Laughed at)\s+/u', '', $content);

        // 3. Strip leading single LOWERCASE letter eller siffer fulgt av storbokstav
        //    (typedstream-lengdebyte): "eHei" → "Hei", "4Takk" → "Takk", "uHei" → "Hei".
        //    Beholder reelle ord som "OK" (begge bokstaver er storbokstav).
        $content = preg_replace('/^[a-z\d]\s*(?=[A-ZÆØÅ])/u', '', $content);

        // 4. Trim
        return trim($content);
    }
}
