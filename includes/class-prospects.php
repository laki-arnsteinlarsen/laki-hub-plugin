<?php
defined('ABSPATH') || exit;

/**
 * Edifice_Prospects — Prospekt-pipeline.
 *
 * Henter norske AS fra Brreg sin enhetsregister-API, scraper hjemmeside
 * for WordPress-deteksjon, henter omsetning fra regnskapsregister-API,
 * og scorer kandidater for hosting/drift-segmentet (primært) og rådgivning.
 *
 * Kilder (alle åpne, gratis, uten autentisering):
 *   - https://data.brreg.no/enhetsregisteret/api/enheter
 *   - https://data.brreg.no/regnskapsregisteret/regnskap/{org_nr}
 */
class Edifice_Prospects {

    // Inkluder-sett for advisory + styreoppdrag-segmentet (NACE 2-siffer)
    // B2B-tjenester + tech der Arnsteins erfaring er sterkest
    const NACE_INCLUDE = [
        '62' => 'Tjenester tilknyttet IT',
        '63' => 'Informasjonstjenester',
        '64' => 'Finansieringsvirksomhet',
        '68' => 'Eiendomsdrift',
        '70' => 'Forretningstjenester',
        '71' => 'Teknisk konsulent',
        '73' => 'Reklame og markedsundersøkelser',
        '78' => 'Arbeidskrafttjenester',
    ];

    // Geografisk fokus: Oslo + Akershus + Østfold + Buskerud + Innlandet
    // (fylkesprefiks i kommunenummer per 2024)
    const KOMMUNE_PREFIXES = ['03', '31', '32', '33', '34'];

    const BRREG_BASE      = 'https://data.brreg.no/enhetsregisteret/api/enheter';
    const REGNSKAP_BASE   = 'https://data.brreg.no/regnskapsregisteret/regnskap/';
    const IMPORT_BATCH_SZ = 50; // antall enheter per import-runde

    // Sweet spot for advisory-segmentet
    const ADVISORY_EMP_MIN = 5;
    const ADVISORY_EMP_MAX = 50;

    // ── Queries ──────────────────────────────────────────────────────────────

    public static function get_all(array $args = []): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        $where = ['1=1'];
        $params = [];

        $status = $args['status'] ?? '';
        if ($status === 'active') {
            $where[] = "status NOT IN ('skipped', 'added_to_crm')";
        } elseif ($status) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if (!empty($args['min_score'])) {
            $where[] = 'hosting_score >= %d';
            $params[] = (int) $args['min_score'];
        }
        if (!empty($args['kommune'])) {
            $where[] = 'kommune_navn LIKE %s';
            $params[] = '%' . $args['kommune'] . '%';
        }
        if (!empty($args['has_wp'])) {
            $where[] = 'has_wordpress = 1';
        }

        $sql = "SELECT * FROM `$t` WHERE " . implode(' AND ', $where)
             . " ORDER BY hosting_score DESC, name ASC LIMIT 500";
        $prepared = $params ? $wpdb->prepare($sql, ...$params) : $sql;
        return $wpdb->get_results($prepared, ARRAY_A) ?: [];
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$t` WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    public static function counts(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        return [
            'total'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t`"),
            'new'          => (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE status='new'"),
            'with_wp'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE has_wordpress=1"),
            'added_to_crm' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE status='added_to_crm'"),
            'skipped'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE status='skipped'"),
            'hot'          => (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE hosting_score >= 50 AND status='new'"),
        ];
    }

    // ── Brreg import ─────────────────────────────────────────────────────────

    /**
     * Importerer en runde med kandidater fra Brreg basert på MVP-kriterier
     * for ADVISORY + STYREOPPDRAG-segmentet.
     * Returnerer ['imported' => N, 'skipped_existing' => M, 'errors' => K].
     *
     * Kriterier:
     *   - status: aktiv (ikke konkurs, slettet eller under avvikling)
     *   - antallAnsatte: 5–50 (etablerte, ikke ferske)
     *   - geografi: Oslo + Akershus + Østfold + Buskerud + Innlandet
     *   - NACE: B2B-tjenester + tech (62, 63, 64, 68, 70, 71, 73, 78)
     *
     * NB: Hjemmeside-krav er fjernet — irrelevant for advisory-segmentet.
     */
    public static function import_batch(array $opts = []): array {
        $batch_size = (int) ($opts['batch_size'] ?? self::IMPORT_BATCH_SZ);
        $stats = ['imported' => 0, 'skipped_existing' => 0, 'skipped_geo' => 0, 'errors' => 0, 'fetched' => 0];

        foreach (array_keys(self::NACE_INCLUDE) as $nace) {
            if ($stats['imported'] >= $batch_size) break;
            $remaining = $batch_size - $stats['imported'];
            $page_size = min($remaining * 4, 100); // hent ekstra — geografi-filter kutter mange

            $url = self::BRREG_BASE . '?' . http_build_query([
                'naeringskode'      => $nace,
                'fraAntallAnsatte'  => self::ADVISORY_EMP_MIN,
                'tilAntallAnsatte'  => self::ADVISORY_EMP_MAX,
                'konkurs'           => 'false',
                'underAvvikling'    => 'false',
                'underTvangsavviklingEllerTvangsopplosning' => 'false',
                'size'              => $page_size,
                'page'              => 0,
            ]);

            $resp = wp_remote_get($url, ['timeout' => 15]);
            if (is_wp_error($resp)) { $stats['errors']++; continue; }
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if (empty($body['_embedded']['enheter'])) continue;

            foreach ($body['_embedded']['enheter'] as $e) {
                if ($stats['imported'] >= $batch_size) break;
                $stats['fetched']++;

                $org_nr = $e['organisasjonsnummer'] ?? '';
                if (!$org_nr) continue;

                // Geografi-filter: kommunenummer-prefiks (fylke)
                $kn = $e['forretningsadresse']['kommunenummer'] ?? '';
                if (!$kn || !in_array(substr($kn, 0, 2), self::KOMMUNE_PREFIXES, true)) {
                    $stats['skipped_geo']++;
                    continue;
                }

                if (self::exists($org_nr)) { $stats['skipped_existing']++; continue; }

                $id = self::insert_from_brreg($e);
                if (!$id) { $stats['errors']++; continue; }

                // WP-deteksjon kjøres som metadata (ikke kritisk for advisory),
                // men bare hvis hjemmeside finnes
                if (!empty($e['hjemmeside'])) {
                    self::detect_wordpress($id);
                }
                self::fetch_revenue($id);
                self::compute_scores($id);
                $stats['imported']++;
            }
        }
        return $stats;
    }

    private static function exists(string $org_nr): bool {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        return (bool) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM `$t` WHERE org_nr = %s", $org_nr)
        );
    }

    private static function insert_from_brreg(array $e): int {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';

        $forr  = $e['forretningsadresse'] ?? null;
        $post  = $e['postadresse'] ?? null;
        $addr_to_str = function (?array $a): string {
            if (!$a) return '';
            return implode(', ', array_filter([
                implode(' ', $a['adresse'] ?? []),
                trim(($a['postnummer'] ?? '') . ' ' . ($a['poststed'] ?? '')),
            ]));
        };

        $nace_obj = $e['naeringskode1'] ?? null;
        $reg_dato = $e['registreringsdatoEnhetsregisteret'] ?? null;

        $wpdb->insert($t, [
            'org_nr'            => $e['organisasjonsnummer'],
            'name'              => $e['navn'] ?? '',
            'nace_code'         => $nace_obj['kode'] ?? null,
            'nace_description'  => $nace_obj['beskrivelse'] ?? null,
            'employees'         => isset($e['antallAnsatte']) ? (int) $e['antallAnsatte'] : null,
            'kommune_nr'        => $forr['kommunenummer'] ?? null,
            'kommune_navn'      => $forr['kommune'] ?? null,
            'registration_date' => $reg_dato ?: null,
            'website'           => $e['hjemmeside'] ?? null,
            'email'             => $e['epostadresse'] ?? null,
            'phone'             => $e['telefon'] ?? ($e['mobil'] ?? null),
            'address'           => $addr_to_str($forr),
            'postal_address'    => $addr_to_str($post),
            'brreg_data'        => wp_json_encode($e),
            'last_synced_at'    => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    // ── Scraping: WP-deteksjon ───────────────────────────────────────────────

    public static function detect_wordpress(int $id): void {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        $row = self::get($id);
        if (!$row || empty($row['website'])) return;

        $url = $row['website'];
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . ltrim($url, '/');

        $resp = wp_remote_get($url, [
            'timeout' => 8,
            'redirection' => 4,
            'user-agent' => 'Mozilla/5.0 EdificeProspectBot/1.0',
        ]);
        $update = ['last_scraped_at' => current_time('mysql')];

        if (is_wp_error($resp)) {
            $update['has_wordpress'] = 0;
            $wpdb->update($t, $update, ['id' => $id]);
            return;
        }

        $body = wp_remote_retrieve_body($resp);
        $is_wp = (
            stripos($body, '/wp-content/') !== false ||
            stripos($body, '/wp-includes/') !== false ||
            stripos($body, 'wp-json') !== false ||
            preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']WordPress/i', $body)
        );
        $update['has_wordpress'] = $is_wp ? 1 : 0;

        if ($is_wp && preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']WordPress\s+([\d.]+)/i', $body, $m)) {
            $update['wp_version'] = $m[1];
        }
        $server = wp_remote_retrieve_header($resp, 'server');
        if ($server) $update['server_header'] = is_array($server) ? implode(',', $server) : (string) $server;

        $wpdb->update($t, $update, ['id' => $id]);
    }

    // ── Brreg regnskapsregister ──────────────────────────────────────────────

    public static function fetch_revenue(int $id): void {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        $row = self::get($id);
        if (!$row) return;

        $url  = self::REGNSKAP_BASE . rawurlencode($row['org_nr']);
        $resp = wp_remote_get($url, ['timeout' => 8]);
        if (is_wp_error($resp)) return;
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code !== 200) return;

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($data) || empty($data)) return;

        // Sortér nyeste først
        usort($data, function ($a, $b) {
            return strcmp(
                $b['regnskapsperiode']['fraDato'] ?? '',
                $a['regnskapsperiode']['fraDato'] ?? ''
            );
        });
        $latest = $data[0];
        $rev = $latest['resultatregnskapResultat']['driftsresultat']['driftsinntekter']['sumDriftsinntekter'] ?? null;
        $year = isset($latest['regnskapsperiode']['fraDato'])
              ? (int) substr($latest['regnskapsperiode']['fraDato'], 0, 4) : null;

        $wpdb->update($t, [
            'revenue_latest' => is_numeric($rev) ? (float) $rev : null,
            'revenue_year'   => $year,
        ], ['id' => $id]);
    }

    // ── Scoring ──────────────────────────────────────────────────────────────

    /**
     * Scoring for ADVISORY + STYREOPPDRAG-segmentet (primær i MVP).
     * Hosting-score beholdes som dvalende kolonne for fremtidig bruk —
     * paused inntil Nettmaker-relaterte saker er løst (se memory).
     */
    public static function compute_scores(int $id): void {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        $row = self::get($id);
        if (!$row) return;

        // ── Advisory-score (primær) ─────────────────────────────────────────
        $advisory = 0;
        $emp = (int) $row['employees'];
        $rev = (float) $row['revenue_latest'];

        // Ansatte sweet spot 5-50: stor nok til å trenge ekstern hjelp,
        // liten nok til at en enkelt rådgiver gjør forskjell
        if ($emp >= 5 && $emp <= 15)            $advisory += 30;  // ideal — minst kapasitet internt
        elseif ($emp >= 16 && $emp <= 30)       $advisory += 25;  // veldig godt
        elseif ($emp >= 31 && $emp <= 50)       $advisory += 15;  // bra
        elseif ($emp >= 51 && $emp <= 100)      $advisory += 5;   // grenseland — har gjerne intern struktur

        // Omsetnings-signaler — sterkest indikator for advisory
        if ($rev > 0) {
            if ($rev >= 5_000_000  && $rev <= 25_000_000)  $advisory += 35;  // sweet spot
            elseif ($rev >= 25_000_000 && $rev <= 75_000_000) $advisory += 25;
            elseif ($rev >= 1_000_000 && $rev <  5_000_000)   $advisory += 15;
            elseif ($rev >= 75_000_000)                       $advisory += 5;
            // < 1 MNOK gir 0 — for tidlig fase / for små
        }

        // Kontaktinfo finnes — gjør outreach mulig
        if (!empty($row['email']))              $advisory += 5;
        if (!empty($row['phone']))              $advisory += 3;

        // Etablert (>2 år) — har overlevd post-startup-fasen
        if (!empty($row['registration_date'])) {
            $age_years = (time() - strtotime($row['registration_date'])) / (365 * 86400);
            if ($age_years >= 2 && $age_years <= 15) $advisory += 10;  // ideal — etablert men ikke gammel og rigid
            elseif ($age_years > 15)                 $advisory += 3;
        }

        // ── Hosting-score (paused — beregnes som referanse, brukes ikke) ────
        $hosting = 0;
        if ((int) $row['has_wordpress'] === 1)  $hosting += 30;
        if (!empty($row['website']))            $hosting += 5;
        if ($emp >= 1 && $emp <= 20)            $hosting += 10;

        $wpdb->update($t, [
            'hosting_score'  => $hosting,
            'advisory_score' => $advisory,
        ], ['id' => $id]);
    }

    // ── Handlinger ───────────────────────────────────────────────────────────

    public static function add_to_crm(int $id): ?int {
        global $wpdb;
        $row = self::get($id);
        if (!$row || $row['status'] === 'added_to_crm') return null;

        // Opprett som selskap-kontakt i CRM
        $contact_id = Edifice_CRM::save([
            'type'           => 'company',
            'name'           => $row['name'],
            'org_nr'         => $row['org_nr'],
            'email'          => $row['email'],
            'phone'          => $row['phone'] ?: '',
            'address'        => $row['address'],
            'postal_address' => $row['postal_address'],
            'category'       => wp_json_encode(['Prospect']),
            'status'         => 'lead',
            'custom_url'     => $row['website'],
            'notes'          => sprintf(
                "Lagt til fra prospekt-pipeline %s.\nHosting-score: %d. WP: %s. Omsetning %s: %s NOK.",
                date('Y-m-d'),
                $row['hosting_score'],
                $row['has_wordpress'] ? 'Ja' . ($row['wp_version'] ? ' (v' . $row['wp_version'] . ')' : '') : 'Nei',
                $row['revenue_year'] ?: '?',
                number_format((float) $row['revenue_latest'], 0, ',', ' ')
            ),
        ]);
        if (!$contact_id) return null;

        $t = $wpdb->prefix . 'edifice_prospects';
        $wpdb->update($t, [
            'status'         => 'added_to_crm',
            'crm_contact_id' => $contact_id,
        ], ['id' => $id]);
        return (int) $contact_id;
    }

    public static function skip(int $id, string $reason = ''): bool {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        return (bool) $wpdb->update($t, [
            'status'      => 'skipped',
            'skip_reason' => $reason,
        ], ['id' => $id]);
    }

    public static function rescan(int $id): void {
        self::detect_wordpress($id);
        self::fetch_revenue($id);
        self::compute_scores($id);
    }

    // ── AJAX ─────────────────────────────────────────────────────────────────

    public static function ajax_import(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        $stats = self::import_batch();
        wp_send_json_success($stats);
    }

    public static function ajax_add_to_crm(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        $id = (int) ($_POST['id'] ?? 0);
        $contact_id = self::add_to_crm($id);
        $contact_id
            ? wp_send_json_success(['contact_id' => $contact_id])
            : wp_send_json_error('Kunne ikke legge til i CRM');
    }

    public static function ajax_skip(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        $id = (int) ($_POST['id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        self::skip($id, $reason)
            ? wp_send_json_success()
            : wp_send_json_error('Kunne ikke hoppe over');
    }

    public static function ajax_rescan(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        $id = (int) ($_POST['id'] ?? 0);
        self::rescan($id);
        wp_send_json_success(self::get($id));
    }
}
