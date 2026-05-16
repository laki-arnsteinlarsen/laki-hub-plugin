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
