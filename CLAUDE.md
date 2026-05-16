# CLAUDE.md — Edifice (laki-hub-plugin)

Dette er kontekstfilen for Claude Code når du jobber med Edifice-pluginen.
Legg denne filen i roten av `laki-hub-plugin/`-repoet som `CLAUDE.md`.

---

## Hva er Edifice?

Edifice er et internt forretningssystem bygd som en WordPress-plugin. Det kjører på
`https://edifice.arnsteinlarsen.no` og brukes som daglig operasjonsverktøy av Arnstein Larsen.
Repoet heter `laki-hub-plugin` av historiske grunner, men plugin-mappe og fil-header bruker `edifice`.

Arnstein bruker kun SPA-frontenden på `/hub/` — WP-admin er bare for infrastruktur.

---

## Infrastruktur

| Parameter | Verdi |
|-----------|-------|
| Server | Hetzner VPS `laki-web-01-ubuntu-8gb-nbg1-1`, IP `178.105.42.193` |
| SSH | `ssh -i ~/.ssh/hetzner_laki arnstein@178.105.42.193` |
| Coolify URL | `http://178.105.42.193:8000` |
| WP-container | `wordpress-l78r6g3o96gmke1f64raie3e` |
| Plugin-sti i container | `/var/www/html/wp-content/plugins/edifice/` |
| Frontend URL | `https://edifice.arnsteinlarsen.no/hub/` |

---

## Deploy-flyt

Branch: **master** (ikke main — historisk navnevalg, ikke endre).

```bash
git add -A
git commit -m "beskrivelse"
git push origin master
```

GitHub Actions (`.github/workflows/deploy.yml`) zipper pluginen, SCP-er til Hetzner og
kjører WP-CLI inne i containeren. Deploy tar typisk 30–60 sekunder.

Sjekk deploy-status: `https://github.com/laki-arnsteinlarsen/laki-hub-plugin/actions`

For å teste på serveren direkte:
```bash
ssh -i ~/.ssh/hetzner_laki arnstein@178.105.42.193
sudo docker exec wordpress-l78r6g3o96gmke1f64raie3e wp --allow-root plugin list
```

---

## Plugin-struktur

```
edifice/
├── edifice.php                    # Hovedfil — init, hooks, require_once av alle klasser
├── includes/
│   ├── class-db.php               # DB-installasjon og migrering
│   ├── class-crm.php              # CRM-modul
│   ├── class-network.php          # Nettverks-tier-system
│   ├── class-interactions.php     # Interaksjonslogg
│   ├── class-projects.php         # Prosjekter
│   ├── class-time.php             # Timeføring
│   ├── class-revenue.php          # Inntekt
│   ├── class-products-digital.php # Digitale produkter
│   ├── class-sync-products.php    # Gumroad/Chrome-synk for produkter
│   ├── class-prospects.php        # Brreg-prospektering
│   ├── class-hosting.php          # Driftsovervåking (Kuma + UptimeRobot)
│   ├── class-sync-imessage.php    # iMessage-synk
│   ├── class-brreg.php            # Brreg-oppslag
│   ├── class-gmail.php            # Gmail OAuth
│   └── class-etsy.php             # Etsy-integrasjon (arkivert)
├── admin/
│   ├── admin.php                  # WP-admin-menyer
│   └── views/
│       ├── crm.php
│       ├── network.php
│       ├── projects.php
│       ├── time.php
│       ├── revenue.php
│       ├── products.php
│       ├── prospects.php
│       ├── hosting.php
│       ├── settings.php
│       └── _interaction-log-modal.php   # Felles modal — inkluderes på body-nivå
├── frontend/
│   └── class-frontend.php         # SPA-rendering på /hub/
└── assets/
    ├── js/
    │   ├── frontend.js            # SPA-routing — SECTIONS-array MÅ oppdateres ved ny seksjon
    │   └── admin.js               # Lastes i <head> (in_footer=false)
    └── css/
        ├── frontend.css
        └── admin.css
```

---

## Database-tabeller (12 stk)

| Tabell | Innhold |
|--------|---------|
| `edifice_contacts` | Kontakter (person + selskap), tier-felt |
| `edifice_contact_emails` | Ekstra e-poster per kontakt |
| `edifice_contact_companies` | Person↔selskap junction (mange-til-mange) |
| `edifice_contact_interactions` | Interaksjonslogg (kanonisk historikk) |
| `edifice_projects` | Prosjekter |
| `edifice_time_entries` | Timeregistreringer |
| `edifice_revenue` | Fakturaer / inntekt |
| `edifice_products` | Digitale produkter |
| `edifice_product_listings` | Kanaldetaljer per produkt (Gumroad/KDP/PromptBase) |
| `edifice_product_revenue` | Omsetning per produkt |
| `edifice_prospects` | Brreg-prospekter med advisory-scoring |
| `edifice_sites` | Hosting: siter på Hetzner med Kuma/UR-monitor-IDer + månedskost |

Nåværende versjon: **1.8.1** — hosting-modul fase 1 (drift + kostnad).
Siste migrasjonsnummer: **17**.

---

## Migreringsregler — KRITISK

**`maybe_migrate()` MÅ alltid kalles FØR `install()` i aktiveringshook.**

Tre scenarioer håndteres:
1. Kun gammel tabell finnes → RENAME til ny
2. Begge tabeller finnes men ny er tom → INSERT INTO ny SELECT * FROM gammel + DROP gammel
3. Kun ny tabell → ingenting

Ny migrasjon = nytt migrasjonsnummer. Hent siste nummer fra `class-db.php` og inkrementer.
Migrations kjøres i rekkefølge og er idempotente.

---

## SPA-routing — KRITISK

Når du legger til en ny seksjon (ny modul), må **tre filer** endres samtidig:

1. `class-frontend.php` — sidebar-lenke + section-div med include
2. `admin/admin.php` — admin-menypunkt + `page_*()`-metode
3. `assets/js/frontend.js` — **SECTIONS-array** (glemmer du denne, faller routing tilbake til dashboard)

---

## Interaksjonslogg og Nettverk — nøkkelarkitektur

- `edifice_contact_interactions` er kanonisk historikk. Felter: `contact_id`, `project_id` (valgfri FK), `dato`, `tid`, `kanal` (sms/epost/telefon/mote/lunsj/kaffe/linkedin/dm/annet), `retning` (inn/ut/toveis), `sammendrag`, `notat`, `kilde`, `ekstern_ref`
- `tier_last_contact` på `edifice_contacts` er en cache-kolonne: oppdateres automatisk til `MAX(dato)` fra interaksjonsloggen ved hver `add()`/`delete()` i `Edifice_Interactions`
- Tier-system: 1 = månedlig, 2 = kvartalsvis, 3 = årlig. Tier 4 er fjernet (Migration 16 satte disse til NULL)
- Felles interaksjonslogg-modal: `admin/views/_interaction-log-modal.php` — inkluderes på body-nivå i `class-frontend.php` FØR `<main>`. Har dobbel-include-guard via `EDIFICE_INTERACTION_MODAL_RENDERED`-konstant. z-index 100001.

---

## JS-lasting — KRITISK

`admin.js` lastes i `<head>` (`in_footer=false`) så `lhAjax`/`lhOpenModal` er definert
før inline-skript i body kjører.

Network-IIFE er pakket i `jQuery(function ($) {...})` (DOMContentLoaded) fordi den
leser `window.EdificeNetwork` som settes via inline-skript i `network.php`.

---

## AJAX-actions (alle prefixet `edifice_`)

| Action | Klasse |
|--------|--------|
| `edifice_network_save/clear/log_contact` | `Edifice_Network` |
| `edifice_interaction_log/delete/list` | `Edifice_Interactions` |
| `edifice_imessage_bulk_import` | `Edifice_Sync_iMessage` |
| `edifice_sync_get_phone_contacts` | `Edifice_Sync_iMessage` |
| `edifice_hosting_list/status/save/delete/test_alert` | `Edifice_Hosting` |
| `edifice_daily_product_sync` | WP Cron, kl. 06:00 UTC |

CLI-vennlige endpoints autentiserer via `edifice_key` (auto-login-nøkkel) i stedet for nonce.

---

## iMessage-synk

- `imessage-extract.command` på brukerens Mac leser `chat.db` direkte (Terminal trenger Full Disk Access)
- `ekstern_ref` for dedup: `<ISO-dato>:<0|1>`
- MCP `Read and Send iMessages` strippes norske tegn i utgående meldinger — bruk ikke MCP for bulk-import, kun for ad-hoc lookup
- Python-orchestrator: `~/Claude Cowork Station/.edifice-sync/imessage-sync-all.py`

---

## Produkter-modul

- Gumroad OAuth 2.0: `client_id=ksZTqjTPLRU0xOVG2Ayvu1d024vHgq36TKn8272CGl0`
- Daglig WP Cron: `edifice_daily_product_sync` kl. 06:00 UTC
- Auto-innlogging: token-basert URL generert i Innstillinger-siden, 48 tegn, `hash_equals`

---

## Prospekter-modul

- Brreg enhetsregister + underenheter + regnskapsregister
- NACE-filter: 46/47/61/62/63/64/68/70/71/72/73/78/82/86 — fylker: 03/31/32/33/34 — ansatte: 2–25
- Scoring maks 83 (hot ≥ 50): ansatte (max 30), omsetning (max 35), modenhet (max 10), kontaktinfo (max 8)
- **Brreg-quirk:** `antallAnsatte`-parametret bucketer i SSB-grupper. Gyldige cutoffs: 0, 1, 5, 10, 20, 50, 100, 250. Post-filtrer i PHP.

---

## Hosting-modul (fase 1 — v1.8.0+)

Driftsovervåking og kostnadskontroll for sitene på Hetzner-riggen. Live status fra
Uptime Kuma + UptimeRobot, månedskost per site og snitt over Hetzner-server.

### Status-flyt

- `class-hosting.php` har 60s transient-cache (`edifice_hosting_status`). View-fila
  rendrer tabell fra DB med ⚪-plassholdere, og henter live status asynkront via
  `edifice_hosting_status`-AJAX ved sidelast. "Oppdater"-knapp sender `refresh=1`
  som tømmer transienten før ny fetch.
- Status per site slås sammen fra Kuma + UptimeRobot basert på `kuma_monitor_id`
  og `uptimerobot_monitor_id` i `edifice_sites`. Begge er valgfrie.

### Kuma-API — KRITISK quirk

**Uptime Kuma 1.x har INGEN `/api/monitors` REST-endepunkt.** Fase 1-specen var feil
på dette punktet — Kuma sin frontend-SPA fanger ukjente paths og returnerer HTML.
Det offisielle, dokumenterte API-et er `/metrics` (Prometheus-format) med HTTP
Basic auth: tom username, API-nøkkel som passord (`Basic base64(":<key>")`).

Vi parser linjene for `monitor_status` (1=up, 0=down, 2=pending, 3=maintenance)
og `monitor_response_time` (ms, -1 når nede) per `monitor_id`-label.

Oppetid 24h/30d er ikke eksponert via `/metrics`. UI-en henter den kolonnen fra
UptimeRobot (`all_time_uptime_ratio`). For Kuma-oppetid senere må vi enten lage
en intern statusside og bruke `/api/status-page/heartbeat/{slug}`, eller vente på
Kuma 2.x sitt fulle REST API.

### UptimeRobot-API

`POST /v2/getMonitors` med `api_key` i form-encoded body. Status: 2=up, 8=down,
9=down (annen variant), 0=paused.

### Innstillinger (lagres i wp_options)

| Option | Innhold |
|--------|---------|
| `edifice_kuma_base_url` | `https://status.arnsteinlarsen.no` |
| `edifice_kuma_api_key` | Bearer-token fra Kuma Settings → API Keys |
| `edifice_uptimerobot_key` | API-nøkkel fra UptimeRobot My Settings |
| `edifice_slack_webhook_hosting` | Webhook til `#hosting-varsler` |
| `edifice_hetzner_monthly_eur` | Fast månedskost (default 35) |
| `edifice_sites_seeded` | Flagg — seedingen kjørte allerede |

### Test-varsling

`edifice_hosting_test_alert`-AJAX poster en testmelding til Slack-webhooken slik
at man kan verifisere at #hosting-varsler får varsler før Kuma/UR utløser dem.

---

## UI-designtokens

| Token | Verdi |
|-------|-------|
| Sidebar bakgrunn | `#1e293b` |
| Logo-felt (øverst i sidebar) | `#3d5268` |
| Aksent | `#C9A84C` (gull) |

---

## GitHub PAT (for API-kall ved behov)

PAT med `repo` + `workflow` scope ligger i `~/Claude Cowork Station/wp-setup-credentials.json`.
Ikke legg nøkkelverdi direkte i denne filen.

---

## Vanlige debug-kommandoer

```bash
# Se siste WP-feil
sudo docker exec wordpress-l78r6g3o96gmke1f64raie3e tail -n 50 /var/www/html/wp-content/debug.log

# Kjør WP-CLI
sudo docker exec wordpress-l78r6g3o96gmke1f64raie3e wp --allow-root <kommando>

# MySQL-tilgang
sudo docker exec -it mysql-l78r6g3o96gmke1f64raie3e mysql -u root -p

# Restart container (tømmer OPcache)
sudo docker restart wordpress-l78r6g3o96gmke1f64raie3e
```
