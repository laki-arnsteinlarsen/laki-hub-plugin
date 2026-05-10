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

    // Inkluder-sett for advisory + styreoppdrag-segmentet (NACE 2-siffer).
    // Kalibrert 10.05.2026: utvidet fra 8 til 14 koder for å fange Arnsteins
    // bredere bransje-erfaring (telecom, helse, R&D, varehandel inkludert).
    const NACE_INCLUDE = [
        '46' => 'Engroshandel',
        '47' => 'Detaljhandel',
        '61' => 'Telekommunikasjon',
        '62' => 'Tjenester tilknyttet IT',
        '63' => 'Informasjonstjenester',
        '64' => 'Finansieringsvirksomhet',
        '68' => 'Eiendomsdrift',
        '70' => 'Forretningstjenester',
        '71' => 'Teknisk konsulent',
        '72' => 'Forskning og utviklingsarbeid',
        '73' => 'Reklame og markedsundersøkelser',
        '78' => 'Arbeidskrafttjenester',
        '82' => 'Annen forretningsmessig tjenesteyting',
        '86' => 'Helsetjenester',
    ];

    // Geografisk fokus: Oslo + Akershus + Østfold + Buskerud + Innlandet
    // (fylkesprefiks i kommunenummer per 2024)
    const KOMMUNE_PREFIXES = ['03', '31', '32', '33', '34'];

    const BRREG_BASE      = 'https://data.brreg.no/enhetsregisteret/api/enheter';
    const UNDERENHET_BASE = 'https://data.brreg.no/enhetsregisteret/api/underenheter';
    const REGNSKAP_BASE   = 'https://data.brreg.no/regnskapsregisteret/regnskap/';
    const IMPORT_BATCH_SZ = 50; // antall enheter per import-runde

    // Størrelses-filter (kalibrert 10.05.2026)
    // Tidligere 5-50, nå 2-25 — flytter nedre grense for å fange små eier-
    // drevne kompetansebedrifter, kapper toppen for å unngå selskaper
    // med moden intern struktur.
    const ADVISORY_EMP_MIN = 2;
    const ADVISORY_EMP_MAX = 25;

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

            // VIKTIG: Brreg-API bucketer ansatte i SSB-grupper. Gyldige
            // grenseverdier er 0, 1, 5, 10, 20, 50, 100, 250. Verdier som
            // 2, 3, 4 returnerer 0 treff fordi de faller midt i en bucket.
            // Vi ber om 1-25 (gyldig) og post-filtrerer for ≥ ADVISORY_EMP_MIN.
            $url = self::BRREG_BASE . '?' . http_build_query([
                'naeringskode'      => $nace,
                'fraAntallAnsatte'  => 1,
                'tilAntallAnsatte'  => 25,
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

                // Post-filter ansatte: Brreg-API ga oss alle 1-25, vi vil ≥ MIN.
                // (Brreg kan ikke filtrere på 2-4 alene pga. SSB-bucketing.)
                if (isset($e['antallAnsatte']) && is_numeric($e['antallAnsatte'])
                    && (int) $e['antallAnsatte'] < self::ADVISORY_EMP_MIN) {
                    continue;
                }

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

    /**
     * Normaliser URL fra Brreg: prepender https:// hvis schema mangler.
     * Brreg returnerer ofte "www.eksempel.no" uten protokoll, som ville
     * blitt tolket som relativ URL i href-attributter.
     */
    private static function normalize_website(?string $raw): ?string {
        $raw = trim((string) $raw);
        if ($raw === '') return null;
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        return esc_url_raw($raw);
    }

    /**
     * Hent ansatte fra underenheter-API som fallback når hovedenheten
     * ikke har antallAnsatte. Brreg sin ansatte-data kommer fra NAVs
     * arbeidsgiverregister og rapporteres ofte per virksomhet/avdeling.
     * Returnerer null hvis fortsatt ikke funnet.
     */
    public static function fetch_employees_fallback(string $org_nr): ?int {
        $url  = self::UNDERENHET_BASE . '?' . http_build_query([
            'overordnetEnhet' => $org_nr,
            'size' => 50,
        ]);
        $resp = wp_remote_get($url, ['timeout' => 8]);
        if (is_wp_error($resp)) return null;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $units = $body['_embedded']['underenheter'] ?? [];
        if (empty($units)) return null;

        $sum = 0;
        $found_any = false;
        foreach ($units as $u) {
            if (isset($u['antallAnsatte']) && is_numeric($u['antallAnsatte'])) {
                $sum += (int) $u['antallAnsatte'];
                $found_any = true;
            }
        }
        return $found_any ? $sum : null;
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

        // Ansatte: bruk hovedenheten først, fall tilbake til underenheter
        $employees = isset($e['antallAnsatte']) && is_numeric($e['antallAnsatte'])
            ? (int) $e['antallAnsatte']
            : self::fetch_employees_fallback($e['organisasjonsnummer']);

        $wpdb->insert($t, [
            'org_nr'            => $e['organisasjonsnummer'],
            'name'              => $e['navn'] ?? '',
            'nace_code'         => $nace_obj['kode'] ?? null,
            'nace_description'  => $nace_obj['beskrivelse'] ?? null,
            'employees'         => $employees,
            'kommune_nr'        => $forr['kommunenummer'] ?? null,
            'kommune_navn'      => $forr['kommune'] ?? null,
            'registration_date' => $reg_dato ?: null,
            'website'           => self::normalize_website($e['hjemmeside'] ?? null),
            'email'             => $e['epostadresse'] ?? null,
            'phone'             => $e['telefon'] ?? ($e['mobil'] ?? null),
            'address'           => $addr_to_str($forr),
            'postal_address'    => $addr_to_str($post),
            'brreg_data'        => wp_json_encode($e),
            'last_synced_at'    => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * Migrer eksisterende prospekter med rå hjemmeside-URLer (uten schema).
     * Idempotent via flag-option.
     */
    public static function migrate_websites(): void {
        if (get_option('edifice_prospect_urls_normalized', false)) return;
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        $rows = $wpdb->get_results(
            "SELECT id, website FROM `$t`
             WHERE website IS NOT NULL AND website <> ''
               AND website NOT LIKE 'http://%' AND website NOT LIKE 'https://%'"
        );
        foreach ($rows as $r) {
            $clean = self::normalize_website($r->website);
            if ($clean) {
                $wpdb->update($t, ['website' => $clean], ['id' => $r->id]);
            }
        }
        update_option('edifice_prospect_urls_normalized', true);
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
        // employees kan være null (Brreg/NAV mangler data) — IKKE samme som 0
        $emp_known = isset($row['employees']) && $row['employees'] !== null && $row['employees'] !== '';
        $emp = $emp_known ? (int) $row['employees'] : null;
        $rev = (float) $row['revenue_latest'];

        // ── Ansatte (maks 30) ──────────────────────────────────────────────
        // Kalibrert 10.05.2026: filter 2-25, sweet 5-15.
        // Ukjent ansatte gir nøytral middels-score (+10) for å unngå å
        // straffe bedrifter med mangelfull NAV-rapportering.
        if (!$emp_known)                        $advisory += 10;  // nøytral for ukjent
        elseif ($emp >= 5 && $emp <= 15)        $advisory += 30;  // sweet spot
        elseif ($emp >= 16 && $emp <= 25)       $advisory += 25;  // veldig godt
        elseif ($emp >= 2 && $emp <= 4)         $advisory += 15;  // små men relevante (eier-drevne kompetansebedrifter)

        // ── Omsetning (maks 35) — sterkeste enkeltsignal ───────────────────
        // Kalibrert 10.05.2026: sweet 3-20 MNOK (var 5-25), gir +5 for <1.
        if ($rev > 0) {
            if ($rev >= 3_000_000  && $rev <= 20_000_000)     $advisory += 35;  // sweet spot
            elseif ($rev >= 20_000_000 && $rev <= 50_000_000) $advisory += 20;
            elseif ($rev >= 1_000_000 && $rev <  3_000_000)   $advisory += 15;
            elseif ($rev > 0 && $rev < 1_000_000)             $advisory += 5;   // tidlig fase, lavt prio
            elseif ($rev > 50_000_000)                        $advisory += 5;   // for stor (sjelden gitt 25-emp-tak)
        }
        // mangler/0 gir 0 — sterk negativ indikator (typisk holdings/forsinket levering)

        // ── Kontaktinfo (maks 8) — outreach-friksjon ───────────────────────
        if (!empty($row['email']))              $advisory += 5;
        if (!empty($row['phone']))              $advisory += 3;

        // ── Modenhet (maks 10) — alder ─────────────────────────────────────
        // Kalibrert 10.05.2026: sweet 2-5 år (var 2-15). Arnstein jobber
        // gjerne fra oppstart, peak-verdi i tidlig vekst-fase.
        if (!empty($row['registration_date'])) {
            $age_years = (time() - strtotime($row['registration_date'])) / (365 * 86400);
            if ($age_years >= 2 && $age_years <= 5)        $advisory += 10;  // sweet spot
            elseif ($age_years > 5  && $age_years <= 15)   $advisory += 7;   // etablert
            elseif ($age_years >= 0 && $age_years < 2)     $advisory += 5;   // helt fersk — han tar disse også
            elseif ($age_years > 15 && $age_years <= 25)   $advisory += 3;   // mellomalder
            elseif ($age_years > 25)                       $advisory += 1;   // gammel/rigid
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
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        $row = self::get($id);
        if ($row && empty($row['employees'])) {
            $emp = self::fetch_employees_fallback($row['org_nr']);
            if ($emp !== null) {
                $wpdb->update($t, ['employees' => $emp], ['id' => $id]);
            }
        }
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

    /**
     * Slett alle prospekter UNNTATT de som er konvertert til CRM-kontakter.
     * Brukes til å rense data ved scoring-modell-justeringer slik at man
     * kan kjøre frisk import med oppdaterte regler.
     * Returnerer antall rader slettet.
     */
    public static function truncate_unconverted(): int {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_prospects';
        return (int) $wpdb->query("DELETE FROM `$t` WHERE status <> 'added_to_crm'");
    }

    public static function ajax_truncate(): void {
        check_ajax_referer('edifice_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Ikke tillatt');
        }
        $deleted = self::truncate_unconverted();
        wp_send_json_success(['deleted' => $deleted]);
    }
}
