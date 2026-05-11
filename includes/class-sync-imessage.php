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
    }

    public static function ajax_bulk_import() {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (! current_user_can('manage_options')) wp_send_json_error('forbidden');

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
            $msg_id = isset($m['message_id']) ? (string) $m['message_id'] : '';
            if (! $msg_id) { $skipped_empty++; continue; }

            if (Edifice_Interactions::exists_by_ref('imessage', $msg_id)) {
                $skipped_dedup++;
                continue;
            }

            $content = self::clean_content($m['content'] ?? '');
            if (! trim($content)) { $skipped_empty++; continue; }

            $date_iso = (string) ($m['date'] ?? '');
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

        // 3. Strip leading control char + tall (f.eks. "eHei", "4Takk", "uHei")
        //    iMessage lagrer noen ganger en disambiguation-byte foran teksten.
        $content = preg_replace('/^[a-zA-Z\d]\s*(?=[A-ZÆØÅ])/u', '', $content);

        // 4. Trim
        return trim($content);
    }
}
