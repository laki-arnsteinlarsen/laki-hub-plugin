<?php
defined('ABSPATH') || exit;

/**
 * UniMicro / DNBregnskap webhook-mottaker.
 *
 * Lytter på POST /wp-json/edifice/v1/webhook/unimicro og oppdaterer
 * edifice_revenue når CustomerInvoice opprettes/endres/betales i DNBregnskap.
 *
 * Konfigurasjon gjøres manuelt i DNBregnskap-UI under "Automatiseringer":
 *   - Entitet: CustomerInvoice
 *   - Hendelser: Oppretting, Endring, Sletting
 *   - Jobb: Webhook → https://edifice.arnsteinlarsen.no/wp-json/edifice/v1/webhook/unimicro?v=2
 *   - Privat nøkkel: samme verdi som edifice_unimicro_signing_key (Edifice → Innstillinger)
 *
 * Quirks (observert 2026-05-16):
 *
 * 1) DNB pusher med header "Softrig-Signature", IKKE "Unimicro-Signature" som
 *    dokumentert på developer.unimicro.no. Softrig er plattformnavnet til den
 *    nyere UniMicro-stacken DNBregnskap kjorer paa. Format er likt:
 *      Softrig-Signature: t=<unix-timestamp>,v1=<hex-hmac-sha256>
 *    Signaturpayload: timestamp + "." + raw_body.
 *
 * 2) URL-basert circuit breaker hos DNB: etter ~4-5 paafoelgende 401-feil
 *    blokkerer DNB den eksakte URL-en internt. Verken automatiserings-toggling
 *    eller sletting/ny-oppretting reverserer dette. Workaround: legg til en
 *    versjons-query-param (?v=2) saa URL blir "ny" hos DNB. WP REST API
 *    ignorerer ukjente query-params, saa routingen er upaavirket.
 *    Ved fremtidig 401-storm: bump til ?v=3, osv.
 */
class Edifice_Unimicro {

    const OPT_SIGNING_KEY    = 'edifice_unimicro_signing_key';
    const REPLAY_TOLERANCE_S = 300; // 5 minutters toleranse mot replay-attacks

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route('edifice/v1', '/webhook/unimicro', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_webhook'],
            'permission_callback' => '__return_true', // auth skjer via signatur-verifisering
        ]);
    }

    public static function handle_webhook(WP_REST_Request $request) {
        $raw_body  = $request->get_body();
        // DNB/UniMicro bruker Softrig-Signature (Softrig er plattformnavnet).
        // Unimicro-Signature beholdes som fallback ifoer dokumentert navn dukker opp paa eldre tenants.
        $signature = $request->get_header('softrig_signature')
                  ?: $request->get_header('Softrig-Signature')
                  ?: $request->get_header('unimicro_signature')
                  ?: $request->get_header('Unimicro-Signature')
                  ?: '';

        // ── 1. Signaturverifisering ────────────────────────────────────────────
        $verify = self::verify_signature($signature, $raw_body);
        if (! $verify['ok']) {
            error_log('[Edifice UniMicro] Signaturverifisering feilet: ' . $verify['reason']);
            return new WP_REST_Response(['error' => $verify['reason']], 401);
        }

        // ── 2. Parse payload ───────────────────────────────────────────────────
        $payload = json_decode($raw_body, true);
        if (! is_array($payload)) {
            error_log('[Edifice UniMicro] Kunne ikke parse JSON-body');
            return new WP_REST_Response(['ok' => true, 'skipped' => 'invalid_json'], 200);
        }

        $entity_name = $payload['EntityName'] ?? '';
        $event_type  = $payload['EventType']  ?? '';
        $entity      = $payload['Entity']     ?? null;

        if ($entity_name !== 'CustomerInvoice' || ! is_array($entity)) {
            // Vi er kun interessert i CustomerInvoice; ignorer alt annet stille.
            return new WP_REST_Response(['ok' => true, 'skipped' => 'not_customer_invoice'], 200);
        }

        // ── 3. Skriv til edifice_revenue ───────────────────────────────────────
        try {
            $result = self::upsert_invoice($entity, $raw_body);
            error_log(sprintf(
                '[Edifice UniMicro] %s CustomerInvoice ID=%s → revenue.id=%d (%s)',
                $event_type,
                (string) ($entity['ID'] ?? '?'),
                $result['id'],
                $result['action']
            ));
            return new WP_REST_Response(['ok' => true] + $result, 200);
        } catch (\Throwable $e) {
            // Returner alltid 200 — ellers vil UniMicro prøve på nytt og spamme oss.
            error_log('[Edifice UniMicro] Exception under upsert: ' . $e->getMessage());
            return new WP_REST_Response(['ok' => true, 'error' => 'internal'], 200);
        }
    }

    /**
     * Verifiser HMAC-SHA256-signaturen fra Softrig/UniMicro.
     * Header: Softrig-Signature (eller Unimicro-Signature paa eldre tenants).
     * Format: "t=1600327644,v1=<hex-signatur>"
     * Signaturpayload: "$timestamp.$raw_body"
     */
    public static function verify_signature(string $header, string $raw_body): array {
        if ($header === '') {
            return ['ok' => false, 'reason' => 'missing_signature'];
        }

        $signing_key = (string) get_option(self::OPT_SIGNING_KEY, '');
        if ($signing_key === '') {
            return ['ok' => false, 'reason' => 'signing_key_not_configured'];
        }

        $parts = [];
        foreach (explode(',', $header) as $segment) {
            $kv = explode('=', trim($segment), 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $timestamp = isset($parts['t']) ? (int) $parts['t'] : 0;
        $provided  = $parts['v1'] ?? '';

        if ($timestamp <= 0 || $provided === '') {
            return ['ok' => false, 'reason' => 'malformed_signature'];
        }

        if (abs(time() - $timestamp) > self::REPLAY_TOLERANCE_S) {
            return ['ok' => false, 'reason' => 'timestamp_outside_tolerance'];
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $raw_body, $signing_key);
        if (! hash_equals($expected, $provided)) {
            return ['ok' => false, 'reason' => 'signature_mismatch'];
        }

        return ['ok' => true, 'reason' => 'verified'];
    }

    /**
     * Map UniMicro StatusCode → Edifice revenue.status.
     */
    private static function map_status(?int $code): string {
        switch ($code) {
            case 42001: return 'draft';   // utkast
            case 42002: return 'sent';    // fakturert / utsendt
            case 42003: return 'sent';    // sendt til inkasso — behold som sent
            case 42004: return 'paid';    // betalt
            case 42005: return 'overdue'; // purret
            default:    return 'draft';
        }
    }

    /**
     * Match CustomerName mot eksisterende kontakt (case-insensitive LIKE).
     * Returnerer contact_id eller null.
     */
    private static function resolve_contact_id(string $customer_name): ?int {
        if ($customer_name === '') return null;
        global $wpdb;
        $tc = $wpdb->prefix . 'edifice_contacts';
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tc WHERE name LIKE %s LIMIT 1",
            $wpdb->esc_like($customer_name)
        ));
        return $id ? (int) $id : null;
    }

    /**
     * Upsert basert på external_id (UniMicro Entity.ID).
     * Returnerer ['id' => int, 'action' => 'inserted'|'updated'].
     */
    private static function upsert_invoice(array $entity, string $raw_body): array {
        global $wpdb;
        $t = $wpdb->prefix . 'edifice_revenue';

        $external_id = isset($entity['ID']) ? (string) $entity['ID'] : '';
        if ($external_id === '') {
            throw new \RuntimeException('Entity.ID mangler');
        }

        $invoice_nr    = isset($entity['InvoiceNumber']) ? (string) $entity['InvoiceNumber'] : '';
        $date          = isset($entity['InvoiceDate'])    ? substr((string) $entity['InvoiceDate'], 0, 10)    : date('Y-m-d');
        $due_date      = isset($entity['PaymentDueDate']) ? substr((string) $entity['PaymentDueDate'], 0, 10) : null;
        $amount        = isset($entity['TaxInclusiveAmountCurrency']) ? (float) $entity['TaxInclusiveAmountCurrency'] : 0.0;
        $amount_ex_vat = isset($entity['TaxExclusiveAmountCurrency']) ? (float) $entity['TaxExclusiveAmountCurrency'] : null;
        $vat_amount    = isset($entity['VatTotalsAmountCurrency'])    ? (float) $entity['VatTotalsAmountCurrency']    : null;
        $currency      = strtoupper((string) ($entity['CurrencyCode']['Code'] ?? 'NOK')) ?: 'NOK';
        $status        = self::map_status(isset($entity['StatusCode']) ? (int) $entity['StatusCode'] : null);
        $customer      = isset($entity['CustomerName']) ? (string) $entity['CustomerName'] : '';
        $contact_id    = self::resolve_contact_id($customer);

        $fields = [
            'type'          => 'invoice',
            'description'   => sanitize_textarea_field($customer),
            'amount'        => $amount,
            'amount_ex_vat' => $amount_ex_vat,
            'vat_amount'    => $vat_amount,
            'currency'      => $currency,
            'date'          => $date,
            'due_date'      => $due_date ?: null,
            'status'        => $status,
            'invoice_nr'    => sanitize_text_field($invoice_nr),
            'external_id'   => $external_id,
            'unimicro_raw'  => $raw_body,
        ];
        if ($contact_id !== null) {
            $fields['contact_id'] = $contact_id;
        }

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $t WHERE external_id = %s LIMIT 1",
            $external_id
        ));

        if ($existing_id) {
            $wpdb->update($t, $fields, ['id' => (int) $existing_id]);
            return ['id' => (int) $existing_id, 'action' => 'updated'];
        }

        $wpdb->insert($t, $fields);
        return ['id' => (int) $wpdb->insert_id, 'action' => 'inserted'];
    }
}
