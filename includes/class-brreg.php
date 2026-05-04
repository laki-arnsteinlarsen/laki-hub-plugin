<?php
defined('ABSPATH') || exit;

class LakiHub_Brreg {

    const API = 'https://data.brreg.no/enhetsregisteret/api/enheter/';

    public static function lookup(string $org_nr): ?array {
        $org_nr = preg_replace('/\s+/', '', $org_nr);
        $url    = self::API . $org_nr;

        $resp = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($resp), true);
    }

    public static function search(string $query): array {
        $url = 'https://data.brreg.no/enhetsregisteret/api/enheter?' . http_build_query([
            'navn' => $query,
            'size' => 10,
        ]);

        $resp = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        return $data['_embedded']['enheter'] ?? [];
    }

    public static function ajax_lookup() {
        check_ajax_referer('laki_hub_nonce', 'nonce');

        $org_nr = sanitize_text_field($_POST['org_nr'] ?? '');
        $query  = sanitize_text_field($_POST['query'] ?? '');

        if ($org_nr) {
            $result = self::lookup($org_nr);
            wp_send_json_success($result ?: ['error' => 'Ikke funnet']);
        } elseif ($query) {
            wp_send_json_success(self::search($query));
        } else {
            wp_send_json_error('Mangler søkeparameter');
        }
    }

    public static function format_address(?array $data): string {
        if (!$data) return '';
        $adr = $data['forretningsadresse'] ?? $data['postadresse'] ?? null;
        if (!$adr) return '';
        $parts = array_filter([
            implode(' ', $adr['adresse'] ?? []),
            ($adr['postnummer'] ?? '') . ' ' . ($adr['poststed'] ?? ''),
        ]);
        return implode(', ', $parts);
    }
}
