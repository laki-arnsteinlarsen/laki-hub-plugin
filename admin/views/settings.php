<?php
defined('ABSPATH') || exit;

// Handle Gmail OAuth callback (Google redirects here with ?code=…&state=…)
if (!empty($_GET['_gmail_callback']) && !empty($_GET['code'])) {
    $ok = LakiHub_Gmail::handle_callback(
        sanitize_text_field($_GET['code']),
        sanitize_text_field($_GET['state'] ?? '')
    );
    echo $ok
        ? '<div class="notice notice-success"><p>✅ Gmail koblet til.</p></div>'
        : '<div class="notice notice-error"><p>Gmail-kobling feilet. Sjekk Client ID/Secret og prøv igjen.</p></div>';
}

// Handle disconnect
if (!empty($_GET['_gmail_disconnect'])) {
    check_admin_referer('laki_gmail_disconnect');
    LakiHub_Gmail::disconnect();
    wp_safe_redirect(admin_url('admin.php?page=laki-hub-settings'));
    exit;
}

// Save credentials
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['laki_gmail_save'])) {
    check_admin_referer('laki_gmail_settings');
    update_option(LakiHub_Gmail::OPT_CREDS, [
        'client_id'     => sanitize_text_field($_POST['client_id']     ?? ''),
        'client_secret' => sanitize_text_field($_POST['client_secret'] ?? ''),
    ]);
    echo '<div class="notice notice-success"><p>Legitimasjon lagret.</p></div>';
}

$creds     = get_option(LakiHub_Gmail::OPT_CREDS, []);
$connected = LakiHub_Gmail::is_connected();
$auth_url  = LakiHub_Gmail::get_auth_url();
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
        <a href="<?= esc_url(wp_nonce_url(admin_url('admin.php?page=laki-hub-settings&_gmail_disconnect=1'), 'laki_gmail_disconnect')) ?>"
           class="lh-btn lh-btn-danger"
           onclick="return confirm('Koble fra Gmail?')">Koble fra Gmail</a>

      <?php else: ?>
        <!-- Setup form -->
        <form method="post" action="">
          <?php wp_nonce_field('laki_gmail_settings'); ?>
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
            <button type="submit" name="laki_gmail_save" value="1" class="lh-btn lh-btn-secondary">
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
                <?= esc_html(LakiHub_Gmail::redirect_uri()) ?>
              </code>
            </li>
            <li>Kopier Client ID og Client Secret hit, lagre, klikk <em>Koble til Gmail</em></li>
          </ol>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
