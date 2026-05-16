<?php
defined('ABSPATH') || exit;

/**
 * Render et notice-blokk for et Gmail API-verifiseringsresultat.
 * Brukes både rett etter OAuth-callback og fra "Test tilkobling"-knappen.
 */
function edifice_gmail_render_verify_notice(array $verify): void {
    if (!empty($verify['ok'])) {
        $email = $verify['email'] ?? '';
        $count = number_format_i18n((int) ($verify['messages_total'] ?? 0));
        echo '<div class="notice notice-success"><p>✅ Gmail API verifisert. Innlogget som <strong>'
             . esc_html($email) . '</strong> · ' . esc_html($count) . ' meldinger totalt.</p></div>';
        return;
    }

    $reason = $verify['reason'] ?? '';
    $msg    = $verify['error']  ?? 'Ukjent feil';

    if ($reason === 'gmail_api_disabled') {
        $url = $verify['activation_url'] ?? '';
        echo '<div class="notice notice-error" style="border-left-color:#dc2626"><p style="font-size:14px">'
           . '<strong>⚠️ Gmail API er ikke aktivert</strong><br>'
           . 'OAuth-tilkoblingen lyktes, men Gmail API er ikke aktivert i Google Cloud-prosjektet ditt. Du må aktivere det før e-poster kan hentes.'
           . ($url ? '<br><br><a href="' . esc_url($url) . '" target="_blank" rel="noopener" class="button button-primary">'
                  . '🚀 Aktiver Gmail API i Google Cloud →</a>' : '')
           . '<br><br><span style="font-size:12px;color:#6b6b70">Etter aktivering, vent 1–2 minutter og klikk «Test tilkobling» nedenfor.</span>'
           . '</p></div>';
        return;
    }

    if ($reason === 'invalid_credentials') {
        echo '<div class="notice notice-error"><p><strong>🔒 Tokenet ble avvist.</strong> ' . esc_html($msg)
           . ' Trykk «Koble fra», og deretter «Koble til Gmail» på nytt.</p></div>';
        return;
    }

    echo '<div class="notice notice-error"><p>❌ ' . esc_html($msg) . '</p></div>';
}

// Handle Gmail OAuth callback (Google redirects here with ?code=…&state=…)
if (!empty($_GET['_gmail_callback']) && !empty($_GET['code'])) {
    $ok = Edifice_Gmail::handle_callback(
        sanitize_text_field($_GET['code']),
        sanitize_text_field($_GET['state'] ?? '')
    );
    if (!$ok) {
        echo '<div class="notice notice-error"><p>Gmail-kobling feilet. Sjekk Client ID/Secret og prøv igjen.</p></div>';
    } else {
        // Tokenet er lagret — verifiser at Gmail API faktisk svarer
        echo '<div class="notice notice-success"><p>✅ OAuth-tilkobling fullført. Verifiserer Gmail API …</p></div>';
        $verify = Edifice_Gmail::verify_api_access();
        edifice_gmail_render_verify_notice($verify);
    }
}

// Handle "Test tilkobling"-knapp
if (!empty($_POST['_gmail_test'])) {
    check_admin_referer('edifice_gmail_test');
    $verify = Edifice_Gmail::verify_api_access();
    edifice_gmail_render_verify_notice($verify);
}

// Etsy: API-tilgang avslått av Etsy mai 2026. UI viser info-boks i stedet
// for OAuth-form. Edifice_Etsy-klassen ligger igjen i kodebasen som arkiv.

// Handle disconnect
if (!empty($_GET['_gmail_disconnect'])) {
    check_admin_referer('edifice_gmail_disconnect');
    Edifice_Gmail::disconnect();
    wp_safe_redirect(admin_url('admin.php?page=edifice-settings'));
    exit;
}

// Handle auto-login key regeneration
if (!empty($_POST['_regen_autologin_key'])) {
    check_admin_referer('edifice_regen_autologin');
    update_option('edifice_autologin_key', wp_generate_password(48, false));
    wp_safe_redirect(admin_url('admin.php?page=edifice-settings#autologin'));
    exit;
}

// Handle Hosting API-settings save
if (!empty($_POST['_edifice_hosting_save'])) {
    check_admin_referer('edifice_hosting_settings');
    update_option('edifice_kuma_base_url',         esc_url_raw($_POST['kuma_base_url'] ?? ''));
    update_option('edifice_kuma_api_key',          sanitize_text_field($_POST['kuma_api_key'] ?? ''));
    update_option('edifice_uptimerobot_key',       sanitize_text_field($_POST['uptimerobot_key'] ?? ''));
    update_option('edifice_slack_webhook_hosting', esc_url_raw($_POST['slack_webhook_hosting'] ?? ''));
    update_option('edifice_hetzner_monthly_eur',   (float) ($_POST['hetzner_monthly_eur'] ?? 35));
    delete_transient(Edifice_Hosting::TRANSIENT_STATUS);
    echo '<div class="notice notice-success"><p>Hosting-innstillinger lagret. Cache tømt.</p></div>';
}

// Handle UniMicro signing-key save
if (!empty($_POST['_edifice_unimicro_save'])) {
    check_admin_referer('edifice_unimicro_settings');
    update_option(Edifice_Unimicro::OPT_SIGNING_KEY, sanitize_text_field($_POST['unimicro_signing_key'] ?? ''));
    echo '<div class="notice notice-success"><p>UniMicro signing key lagret.</p></div>';
}


// Save credentials
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edifice_gmail_save'])) {
    check_admin_referer('edifice_gmail_settings');
    update_option(Edifice_Gmail::OPT_CREDS, [
        'client_id'     => sanitize_text_field($_POST['client_id']     ?? ''),
        'client_secret' => sanitize_text_field($_POST['client_secret'] ?? ''),
    ]);
    echo '<div class="notice notice-success"><p>Legitimasjon lagret.</p></div>';
}

$creds     = get_option(Edifice_Gmail::OPT_CREDS, []);
$connected = Edifice_Gmail::is_connected();
$auth_url  = Edifice_Gmail::get_auth_url();
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div>
      <h1>Innstillinger</h1>
      <div class="lh-subtitle">Integrasjoner og tilkoblinger</div>
    </div>
  </div>

  <!-- Gmail card -->
  <div class="lh-card">
    <div class="lh-card-head">
      <h2>📧 Gmail-integrasjon</h2>
      <span class="lh-badge <?= $connected ? 'lh-badge-green' : 'lh-badge-gray' ?>">
        <?= $connected ? 'Koblet til' : 'Ikke koblet' ?>
      </span>
    </div>
    <div class="lh-card-body">
      <p style="font-size:13px;color:var(--lh-muted);margin:0 0 20px">
        Koble til Gmail for å se e-posthistorikk direkte på kontaktpersoner i CRM.
        Krever et Google Cloud-prosjekt med Gmail API aktivert og OAuth 2.0-legitimasjon.
      </p>

      <?php if ($connected): ?>
        <!-- Connected state -->
        <div style="display:flex;align-items:center;gap:16px;padding:16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:20px">
          <span style="font-size:28px">✅</span>
          <div>
            <div style="font-weight:600;color:#15803d">Gmail er koblet til</div>
            <div style="font-size:12px;color:var(--lh-muted);margin-top:2px">E-posthistorikk er tilgjengelig i kontaktvisning (person)</div>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <form method="post" style="margin:0;display:inline">
            <?php wp_nonce_field('edifice_gmail_test'); ?>
            <input type="hidden" name="_gmail_test" value="1">
            <button type="submit" class="lh-btn lh-btn-secondary">🧪 Test tilkobling</button>
          </form>
          <a href="<?= esc_url(wp_nonce_url(admin_url('admin.php?page=edifice-settings&_gmail_disconnect=1'), 'edifice_gmail_disconnect')) ?>"
             class="lh-btn lh-btn-danger"
             onclick="return confirm('Koble fra Gmail?')">Koble fra Gmail</a>
        </div>

      <?php else: ?>
        <!-- Setup form -->
        <form method="post" action="">
          <?php wp_nonce_field('edifice_gmail_settings'); ?>
          <div class="lh-form-grid" style="max-width:640px;margin-bottom:16px">
            <div class="lh-form-row" style="margin-bottom:0">
              <label>Google Client ID</label>
              <input type="text" name="client_id"
                     value="<?= esc_attr($creds['client_id'] ?? '') ?>"
                     placeholder="xxx.apps.googleusercontent.com">
            </div>
            <div class="lh-form-row" style="margin-bottom:0">
              <label>Google Client Secret</label>
              <input type="password" name="client_secret"
                     value="<?= esc_attr($creds['client_secret'] ?? '') ?>"
                     placeholder="GOCSPX-…">
            </div>
          </div>
          <div style="display:flex;gap:10px;align-items:center">
            <button type="submit" name="edifice_gmail_save" value="1" class="lh-btn lh-btn-secondary">
              Lagre legitimasjon
            </button>
            <?php if (!empty($creds['client_id']) && $auth_url): ?>
              <a href="<?= esc_url($auth_url) ?>" class="lh-btn lh-btn-primary">
                🔗 Koble til Gmail
              </a>
            <?php endif; ?>
          </div>
        </form>

        <!-- Instructions -->
        <div style="margin-top:24px;padding:18px 20px;background:#f8fafc;border:1px solid var(--lh-border);border-radius:10px">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--lh-muted);margin-bottom:10px">
            Oppsettsveiledning
          </div>
          <ol style="margin:0;padding-left:20px;font-size:13px;line-height:2;color:var(--lh-text)">
            <li>Gå til <a href="https://console.cloud.google.com" target="_blank" rel="noopener">Google Cloud Console</a> → opprett prosjekt</li>
            <li>APIs &amp; Services → Aktiver <strong>Gmail API</strong></li>
            <li>Credentials → Create → <strong>OAuth client ID</strong> → Nettapplikasjon</li>
            <li>Legg til autorisert omdirigerings-URI:<br>
              <code style="display:inline-block;margin-top:4px;background:#e2e8f0;padding:4px 8px;border-radius:5px;font-size:12px">
                <?= esc_html(Edifice_Gmail::redirect_uri()) ?>
              </code>
            </li>
            <li>Kopier Client ID og Client Secret hit, lagre, klikk <em>Koble til Gmail</em></li>
          </ol>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Etsy card — API access denied (mai 2026) -->
  <div class="lh-card" style="margin-top:24px">
    <div class="lh-card-head">
      <h2>🎨 Etsy-integrasjon</h2>
      <span class="lh-badge lh-badge-gray">API-tilgang avslått</span>
    </div>
    <div class="lh-card-body">
      <div style="padding:18px 20px;background:#fef3c7;border:1px solid #fde68a;border-radius:10px">
        <p style="margin:0 0 12px;font-weight:600">⚠️ Etsy avslo søknad om API-tilgang (mai 2026)</p>
        <p style="margin:0 0 8px;font-size:13px;color:var(--lh-text)">
          Etsy har strammet inn godkjenningskriteriene de siste årene og aksepterer ikke alltid interne apper uten tydelig tredjepartsverdi. De har bekreftet at de ikke vil revurdere.
        </p>
        <p style="margin:0;font-size:13px;color:var(--lh-text)">
          <strong>Workaround:</strong> Bruk <a href="<?= esc_url(admin_url('admin.php?page=edifice-products')) ?>">CSV-import på Produkter-siden</a> for engangs-registrering av listings. Omsetning legges inn manuelt per måned, eller via et nytt CSV-import-pass når du eksporterer fra Etsy.
        </p>
      </div>
      <p style="margin-top:16px;font-size:11px;color:var(--lh-muted)">
        Edifice_Etsy-klassen ligger igjen i kodebasen som arkiv — kan gjenbrukes hvis Etsy senere endrer policy.
      </p>
    </div>
  </div>

  <!-- Hosting card -->
  <?php
    $kuma_base    = get_option('edifice_kuma_base_url', 'https://status.arnsteinlarsen.no');
    $kuma_key     = get_option('edifice_kuma_api_key', '');
    $ur_key       = get_option('edifice_uptimerobot_key', '');
    $slack_hook   = get_option('edifice_slack_webhook_hosting', '');
    $hetzner_eur  = get_option('edifice_hetzner_monthly_eur', 35);
    $hosting_ok   = $kuma_key && $ur_key;
  ?>
  <div class="lh-card" style="margin-top:24px">
    <div class="lh-card-head">
      <h2>🖥️ Hosting-overvåking</h2>
      <span class="lh-badge <?= $hosting_ok ? 'lh-badge-green' : 'lh-badge-gray' ?>">
        <?= $hosting_ok ? 'Konfigurert' : 'Ikke fullkonfigurert' ?>
      </span>
    </div>
    <div class="lh-card-body">
      <p style="font-size:13px;color:var(--lh-muted);margin:0 0 20px">
        API-nøkler for Uptime Kuma og UptimeRobot, samt Slack-webhook til <code>#hosting-varsler</code>.
        Hetzner-månedskost brukes til snittberegning per site på Hosting-fanen.
      </p>

      <form method="post" action="">
        <?php wp_nonce_field('edifice_hosting_settings'); ?>
        <input type="hidden" name="_edifice_hosting_save" value="1">

        <div class="lh-form-grid" style="max-width:720px">
          <div class="lh-form-row">
            <label>Uptime Kuma base URL</label>
            <input type="url" name="kuma_base_url"
                   value="<?= esc_attr($kuma_base) ?>"
                   placeholder="https://status.arnsteinlarsen.no">
          </div>
          <div class="lh-form-row">
            <label>Kuma API-nøkkel</label>
            <input type="password" name="kuma_api_key"
                   value="<?= esc_attr($kuma_key) ?>"
                   placeholder="••••••••">
          </div>
          <div class="lh-form-row">
            <label>UptimeRobot API-nøkkel</label>
            <input type="password" name="uptimerobot_key"
                   value="<?= esc_attr($ur_key) ?>"
                   placeholder="••••••••">
          </div>
          <div class="lh-form-row">
            <label>Hetzner månedskost (EUR)</label>
            <input type="number" step="0.01" name="hetzner_monthly_eur"
                   value="<?= esc_attr($hetzner_eur) ?>">
          </div>
        </div>

        <div class="lh-form-row" style="max-width:720px">
          <label>Slack webhook for #hosting-varsler</label>
          <input type="url" name="slack_webhook_hosting"
                 value="<?= esc_attr($slack_hook) ?>"
                 placeholder="https://hooks.slack.com/services/...">
        </div>

        <div style="display:flex;gap:10px;align-items:center;margin-top:16px">
          <button type="submit" class="lh-btn lh-btn-primary">Lagre Hosting-innstillinger</button>
          <span style="font-size:11px;color:var(--lh-muted)">
            Lagring tømmer status-cachen så ny data hentes umiddelbart.
          </span>
        </div>
      </form>
    </div>
  </div>

  <!-- UniMicro / DNBregnskap card -->
  <?php
    $unimicro_key       = (string) get_option(Edifice_Unimicro::OPT_SIGNING_KEY, '');
    $unimicro_endpoint  = rest_url('edifice/v1/webhook/unimicro');
    $unimicro_connected = $unimicro_key !== '';
  ?>
  <div class="lh-card" style="margin-top:24px">
    <div class="lh-card-head">
      <h2>📥 UniMicro / DNBregnskap</h2>
      <span class="lh-badge <?= $unimicro_connected ? 'lh-badge-green' : 'lh-badge-gray' ?>">
        <?= $unimicro_connected ? 'Konfigurert' : 'Ikke konfigurert' ?>
      </span>
    </div>
    <div class="lh-card-body">
      <p style="font-size:13px;color:var(--lh-muted);margin:0 0 20px">
        Mottar webhook-pushes fra DNBregnskap når CustomerInvoice opprettes, oppdateres eller betales.
        Fakturaer dukker opp i <strong>Inntekt</strong>-modulen automatisk.
      </p>

      <form method="post" action="">
        <?php wp_nonce_field('edifice_unimicro_settings'); ?>
        <input type="hidden" name="_edifice_unimicro_save" value="1">

        <div class="lh-form-grid" style="max-width:720px">
          <div class="lh-form-row">
            <label>Webhook-endepunkt (kopier til UniMicro-eventplan)</label>
            <input type="text" readonly
                   value="<?= esc_attr($unimicro_endpoint) ?>"
                   style="font-family:monospace;font-size:12px;background:#f8fafc">
          </div>
          <div class="lh-form-row">
            <label>UniMicro Signing Key</label>
            <input type="password" name="unimicro_signing_key"
                   value="<?= esc_attr($unimicro_key) ?>"
                   placeholder="••••••••"
                   autocomplete="new-password">
          </div>
        </div>

        <div style="display:flex;gap:10px;align-items:center;margin-top:16px">
          <button type="submit" class="lh-btn lh-btn-primary">Lagre signing key</button>
          <span style="font-size:11px;color:var(--lh-muted)">
            Brukes til å verifisere HMAC-SHA256-signaturen i <code>Unimicro-Signature</code>-headeren.
          </span>
        </div>
      </form>

      <div style="margin-top:24px;padding:18px 20px;background:#f8fafc;border:1px solid var(--lh-border);border-radius:10px">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--lh-muted);margin-bottom:10px">
          Oppsett i DNBregnskap
        </div>
        <ol style="margin:0;padding-left:20px;font-size:13px;line-height:1.8;color:var(--lh-text)">
          <li>Generer en tilfeldig signing key (f.eks. 48 tegn) — bruk samme verdi her og i UniMicro</li>
          <li>POST til <code>/api/biz/eventplans</code> hos UniMicro med <code>ModelFilter=CustomerInvoice</code>, <code>OperationFilter=CUD</code> og endepunktet over</li>
          <li>Test ved å opprette et utkast i DNBregnskap — fakturaen skal dukke opp i Inntekt-modulen umiddelbart</li>
        </ol>
      </div>
    </div>
  </div>

  <!-- Auto-login card -->
  <div id="autologin" class="lh-card" style="margin-top:24px">
    <div class="lh-card-header" style="padding:20px 24px 0">
      <h2>🔑 Auto-innlogging</h2>
    </div>
    <div style="padding:20px 24px">
      <p style="font-size:13px;color:var(--lh-text);margin:0 0 16px">
        Bokmerke denne URL-en på din maskin — klikk for å logge inn automatisk uten passord.
      </p>
      <?php
        $key      = get_option('edifice_autologin_key', '');
        $login_url = add_query_arg('edifice_key', $key, site_url('wp-login.php'));
      ?>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input
          type="text"
          id="edifice-autologin-url"
          readonly
          value="<?= esc_attr($login_url) ?>"
          style="flex:1;min-width:280px;font-family:monospace;font-size:12px;padding:8px 12px;border:1px solid var(--lh-border);border-radius:7px;background:#f8fafc;color:var(--lh-text)"
        >
        <button
          type="button"
          onclick="navigator.clipboard.writeText(document.getElementById('edifice-autologin-url').value).then(()=>{this.textContent='✅ Kopiert!';setTimeout(()=>this.textContent='📋 Kopier',2000)})"
          class="button"
          style="white-space:nowrap"
        >📋 Kopier</button>
      </div>
      <form method="post" style="margin-top:14px" onsubmit="return confirm('Generer ny nøkkel? Den gamle URL-en vil slutte å virke.')">
        <?php wp_nonce_field('edifice_regen_autologin'); ?>
        <input type="hidden" name="_regen_autologin_key" value="1">
        <button type="submit" class="button" style="font-size:12px;color:#b91c1c">
          🔄 Generer ny nøkkel
        </button>
        <span style="font-size:11px;color:var(--lh-muted);margin-left:8px">
          Bruk dette hvis nøkkelen er kompromittert
        </span>
      </form>
      <p style="font-size:11px;color:var(--lh-muted);margin:14px 0 0">
        ⚠️ Ikke del denne URL-en — den gir full tilgang uten passord.
        "Husk meg"-cookies varer nå automatisk i 1 år.
      </p>
    </div>
  </div>

</div>
</div>
