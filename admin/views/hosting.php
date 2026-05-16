<?php
defined('ABSPATH') || exit;

$sites    = Edifice_Hosting::get_sites();
$costs    = Edifice_Hosting::get_cost_summary();
$kuma_url = get_option('edifice_kuma_base_url', 'https://status.arnsteinlarsen.no');
$has_kuma = (bool) get_option('edifice_kuma_api_key', '');
$has_ur   = (bool) get_option('edifice_uptimerobot_key', '');
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div>
      <h1>🖥️ Hosting</h1>
      <div class="lh-subtitle">
        <?= count($sites) ?> siter ·
        <?= $has_kuma ? '✅ Kuma' : '⚠️ Kuma ikke konfigurert' ?> ·
        <?= $has_ur   ? '✅ UptimeRobot' : '⚠️ UptimeRobot ikke konfigurert' ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button id="lh-hosting-refresh" class="lh-btn lh-btn-secondary">🔄 Oppdater</button>
      <button id="lh-hosting-test-alert" class="lh-btn lh-btn-secondary">🔔 Test varsling</button>
      <button class="lh-btn lh-btn-primary"
              onclick="lhHostingOpenEdit(null)">+ Legg til site</button>
    </div>
  </div>

  <!-- ── Kostnadsoppsummering ──────────────────────────────────────────── -->
  <div class="lh-stats" style="margin-bottom:20px">
    <div class="lh-stat">
      <div class="lh-stat-label">Total månedskost (NOK)</div>
      <div class="lh-stat-value"><?= number_format($costs['total_monthly_nok'], 0, ',', ' ') ?></div>
      <div class="lh-stat-sub">sum av alle siter</div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Hetzner-server</div>
      <div class="lh-stat-value"><?= number_format($costs['hetzner_monthly_eur'], 0, ',', ' ') ?></div>
      <div class="lh-stat-sub">EUR/mnd (fast)</div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Snitt per site</div>
      <div class="lh-stat-value"><?= number_format($costs['avg_per_site_eur'], 1, ',', ' ') ?></div>
      <div class="lh-stat-sub">EUR/mnd · <?= $costs['active_count'] ?> aktive</div>
    </div>
  </div>

  <!-- ── Tabell ────────────────────────────────────────────────────────── -->
  <div class="lh-card">
    <div class="lh-card-head" style="display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0">Status</h2>
      <span style="font-size:11px;color:var(--lh-muted)" id="lh-hosting-fetched">
        Henter live status…
      </span>
    </div>
    <div class="lh-table-wrap">
      <?php if ($sites): ?>
      <table class="lh-table" id="lh-hosting-table">
        <thead>
          <tr>
            <th>Site</th>
            <th>Kuma</th>
            <th>UptimeRobot</th>
            <th>Respons</th>
            <th>Oppetid 30d</th>
            <th>Kostnad/mnd</th>
            <th>Kunde</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($sites as $s):
          $not_active = (int) $s['active'] !== 1;
        ?>
          <tr data-site-id="<?= (int) $s['id'] ?>" class="<?= $not_active ? 'lh-row-muted' : '' ?>">
            <td>
              <strong><?= esc_html($s['name']) ?></strong>
              <?php if ($not_active): ?>
                <span class="lh-badge lh-badge-gray" style="margin-left:6px">Inaktiv</span>
              <?php endif; ?>
              <div style="font-size:11px;color:var(--lh-muted);margin-top:2px">
                <a href="<?= esc_url($s['url']) ?>" target="_blank" rel="noopener"><?= esc_html($s['domain'] ?: $s['url']) ?> ↗</a>
              </div>
            </td>
            <td class="lh-hosting-kuma">⚪</td>
            <td class="lh-hosting-ur">⚪</td>
            <td class="lh-hosting-response">—</td>
            <td class="lh-hosting-uptime">—</td>
            <td class="amount">
              <?= $s['monthly_cost_nok'] > 0
                  ? number_format((float) $s['monthly_cost_nok'], 0, ',', ' ') . ' kr'
                  : '—' ?>
            </td>
            <td><?= esc_html($s['customer_name'] ?: '—') ?></td>
            <td class="actions">
              <button class="lh-btn lh-btn-secondary lh-btn-sm"
                      onclick="lhHostingOpenDetail(<?= (int) $s['id'] ?>)">Detaljer</button>
              <button class="lh-btn lh-btn-danger lh-btn-sm lh-delete-btn"
                      data-action="edifice_hosting_delete"
                      data-id="<?= (int) $s['id'] ?>">Slett</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="lh-empty"><p>Ingen siter registrert ennå.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     SITE DETAIL MODAL — 3 tabs: Status / Konfigurasjon / Logg
══════════════════════════════════════════════════════════════════════════ -->
<div class="lh-modal-overlay" id="modal-hosting-site">
  <div class="lh-modal lh-modal-wide">
    <div class="lh-modal-head">
      <div>
        <h3 id="lh-hosting-modal-title" style="margin:0">Site</h3>
        <div id="lh-hosting-modal-url" style="font-size:12px;color:var(--lh-muted);margin-top:2px"></div>
      </div>
      <button class="lh-modal-close">×</button>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:0;border-bottom:1px solid var(--lh-border);padding:0 24px">
      <button class="lh-tab-btn active" data-tab="status">Status</button>
      <button class="lh-tab-btn" data-tab="config">Konfigurasjon</button>
      <button class="lh-tab-btn" data-tab="log">Logg</button>
    </div>

    <div class="lh-modal-body">

      <!-- TAB: Status -->
      <div class="lh-tab-pane" data-pane="status">
        <div class="lh-stats" style="margin:0 0 14px">
          <div class="lh-stat">
            <div class="lh-stat-label">Kuma status</div>
            <div class="lh-stat-value" id="lh-detail-kuma-status">—</div>
            <div class="lh-stat-sub" id="lh-detail-kuma-sub">—</div>
          </div>
          <div class="lh-stat">
            <div class="lh-stat-label">UptimeRobot status</div>
            <div class="lh-stat-value" id="lh-detail-ur-status">—</div>
            <div class="lh-stat-sub" id="lh-detail-ur-sub">—</div>
          </div>
          <div class="lh-stat">
            <div class="lh-stat-label">Responstid (snitt)</div>
            <div class="lh-stat-value" id="lh-detail-response">—</div>
            <div class="lh-stat-sub">ms</div>
          </div>
        </div>
        <div id="lh-detail-no-monitors" style="display:none;padding:14px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;font-size:13px">
          ⚠️ Ingen monitor-ID-er er knyttet til denne siten ennå. Sett dem i Konfigurasjon-tab.
        </div>
      </div>

      <!-- TAB: Konfigurasjon -->
      <div class="lh-tab-pane lh-hidden" data-pane="config">
        <form class="lh-ajax-form" id="lh-hosting-form">
          <input type="hidden" name="ajax_action" value="edifice_hosting_save">
          <input type="hidden" name="id" value="">
          <div class="lh-form-grid">
            <div class="lh-form-row">
              <label>Navn *</label>
              <input type="text" name="name" required>
            </div>
            <div class="lh-form-row">
              <label>URL *</label>
              <input type="url" name="url" required placeholder="https://example.no">
            </div>
            <div class="lh-form-row">
              <label>Domene</label>
              <input type="text" name="domain" placeholder="example.no">
            </div>
            <div class="lh-form-row">
              <label>Kunde</label>
              <input type="text" name="customer_name">
            </div>
            <div class="lh-form-row">
              <label>Coolify-container</label>
              <input type="text" name="coolify_container" placeholder="wordpress-...">
            </div>
            <div class="lh-form-row">
              <label>Coolify service UUID</label>
              <input type="text" name="coolify_service_uuid">
            </div>
            <div class="lh-form-row">
              <label>Kuma monitor-ID</label>
              <input type="number" name="kuma_monitor_id" min="1" placeholder="f.eks. 3">
            </div>
            <div class="lh-form-row">
              <label>UptimeRobot monitor-ID</label>
              <input type="text" name="uptimerobot_monitor_id" placeholder="f.eks. 798231741">
            </div>
            <div class="lh-form-row">
              <label>Månedskost (NOK)</label>
              <input type="number" step="1" name="monthly_cost_nok" value="0">
            </div>
            <div class="lh-form-row">
              <label>Aktiv</label>
              <label style="display:flex;align-items:center;gap:8px;font-weight:normal">
                <input type="checkbox" name="active" value="1" checked>
                Vises i tabell og inngår i kostnadssnitt
              </label>
            </div>
          </div>
          <div class="lh-form-row">
            <label>Notater</label>
            <textarea name="notes" rows="3"></textarea>
          </div>
          <div class="lh-modal-foot" style="padding:0;margin-top:8px">
            <button type="submit" class="lh-btn lh-btn-primary">Lagre</button>
            <button type="button" class="lh-btn lh-btn-secondary lh-modal-close">Avbryt</button>
          </div>
        </form>
      </div>

      <!-- TAB: Logg -->
      <div class="lh-tab-pane lh-hidden" data-pane="log">
        <p style="font-size:13px;color:var(--lh-muted);margin:0 0 12px">
          Hendelseslogg vises i Uptime Kuma. Klikk for å åpne monitoren:
        </p>
        <p>
          <a id="lh-detail-kuma-link" href="<?= esc_url($kuma_url) ?>"
             target="_blank" rel="noopener"
             class="lh-btn lh-btn-secondary">📊 Åpne i Uptime Kuma ↗</a>
        </p>
        <p style="font-size:12px;color:var(--lh-muted);margin-top:18px">
          (Innebygd hendelseslogg kommer i Fase 2 — krever ekstra Kuma <code>/api/heartbeats/{id}</code>-kall per monitor.)
        </p>
      </div>

    </div>
  </div>
</div>

<style>
  .lh-tab-btn {
    background: none;
    border: 0;
    border-bottom: 2px solid transparent;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 600;
    color: var(--lh-muted);
    cursor: pointer;
  }
  .lh-tab-btn:hover { color: var(--lh-text); }
  .lh-tab-btn.active {
    color: var(--lh-text);
    border-bottom-color: #C9A84C;
  }
  .lh-tab-pane { padding-top: 4px; }
  .lh-row-muted td { opacity: .55; }
</style>

<script>
(function ($) {
  'use strict';

  // Site-data fra PHP — brukes til prefyll i config-tab
  var HOSTING_SITES = <?= wp_json_encode(array_values($sites)) ?>;
  var LIVE_STATUS = null; // fylles av fetch_status()

  function findSite(id) {
    for (var i = 0; i < HOSTING_SITES.length; i++) {
      if (+HOSTING_SITES[i].id === +id) return HOSTING_SITES[i];
    }
    return null;
  }
  function findLive(id) {
    if (!LIVE_STATUS) return null;
    for (var i = 0; i < LIVE_STATUS.sites.length; i++) {
      if (+LIVE_STATUS.sites[i].id === +id) return LIVE_STATUS.sites[i];
    }
    return null;
  }

  function statusEmoji(label) {
    if (label === 'up') return '🟢 Oppe';
    if (label === 'down') return '🔴 Nede';
    if (label === 'paused') return '⏸️ Pauset';
    if (label === 'maintenance') return '🛠️ Vedlikehold';
    return '⚪ Ukjent';
  }
  function combinedDot(kuma, ur) {
    var k = kuma && kuma.status, u = ur && ur.status;
    if (!k && !u) return '⚪';
    if (k === 'up' && (u === 'up' || !u)) return '🟢';
    if ((k === 'down' && u !== 'up') || (k === 'down' && !u)) return '🔴';
    if ((k === 'down') !== (u === 'down')) return '🟡';
    return '⚪';
  }

  function renderStatus(payload) {
    LIVE_STATUS = payload;
    var $table = $('#lh-hosting-table');
    payload.sites.forEach(function (s) {
      var $row = $table.find('tr[data-site-id="' + s.id + '"]');
      if (!$row.length) return;

      $row.find('.lh-hosting-kuma').text(s.kuma ? statusEmoji(s.kuma.status) : '⚪ —');
      $row.find('.lh-hosting-ur').text(s.uptimerobot ? statusEmoji(s.uptimerobot.status) : '⚪ —');

      var resp = (s.kuma && s.kuma.response_ms) || (s.uptimerobot && s.uptimerobot.response_ms);
      $row.find('.lh-hosting-response').text(resp ? resp + ' ms' : '—');

      var up = (s.kuma && s.kuma.uptime_30d != null)
        ? s.kuma.uptime_30d.toFixed(2) + '%'
        : ((s.uptimerobot && s.uptimerobot.uptime_ratio != null)
            ? parseFloat(s.uptimerobot.uptime_ratio).toFixed(2) + '%' : '—');
      $row.find('.lh-hosting-uptime').text(up);
    });

    var when = payload.fetched_at ? new Date(payload.fetched_at).toLocaleString('nb-NO') : '';
    var bits = ['Sist oppdatert: ' + when];
    if (!payload.kuma_ok) bits.push('⚠️ Kuma svarte ikke');
    if (!payload.ur_ok)   bits.push('⚠️ UptimeRobot svarte ikke');
    $('#lh-hosting-fetched').text(bits.join(' · '));
  }

  function fetchStatus(refresh) {
    $('#lh-hosting-fetched').text('Henter live status…');
    $.post(Edifice.ajax_url, {
      action:  'edifice_hosting_status',
      nonce:   Edifice.nonce,
      refresh: refresh ? 1 : 0,
    }, function (r) {
      if (r.success) renderStatus(r.data);
      else $('#lh-hosting-fetched').text('Feil: ' + (r.data || 'ukjent'));
    });
  }

  // ── Detail modal ─────────────────────────────────────────────────────────
  window.lhHostingOpenDetail = function (id) {
    var site = findSite(id);
    if (!site) return;
    var live = findLive(id);

    $('#lh-hosting-modal-title').text(site.name);
    $('#lh-hosting-modal-url').html('<a href="' + site.url + '" target="_blank" rel="noopener">'
      + (site.domain || site.url) + ' ↗</a>');

    // Prefill config form
    var $form = $('#lh-hosting-form');
    $form[0].reset();
    $form.find('[name=id]').val(site.id);
    $form.find('[name=name]').val(site.name);
    $form.find('[name=url]').val(site.url);
    $form.find('[name=domain]').val(site.domain || '');
    $form.find('[name=customer_name]').val(site.customer_name || '');
    $form.find('[name=coolify_container]').val(site.coolify_container || '');
    $form.find('[name=coolify_service_uuid]').val(site.coolify_service_uuid || '');
    $form.find('[name=kuma_monitor_id]').val(site.kuma_monitor_id || '');
    $form.find('[name=uptimerobot_monitor_id]').val(site.uptimerobot_monitor_id || '');
    $form.find('[name=monthly_cost_nok]').val(site.monthly_cost_nok || 0);
    $form.find('[name=notes]').val(site.notes || '');
    $form.find('[name=active]').prop('checked', +site.active === 1);

    // Status tab
    var k = live && live.kuma, u = live && live.uptimerobot;
    $('#lh-detail-kuma-status').text(k ? statusEmoji(k.status) : '⚪ —');
    $('#lh-detail-kuma-sub').text(k && k.uptime_30d != null
      ? 'Oppetid 30d: ' + k.uptime_30d.toFixed(2) + '%' : 'monitor-ID ikke satt');
    $('#lh-detail-ur-status').text(u ? statusEmoji(u.status) : '⚪ —');
    $('#lh-detail-ur-sub').text(u && u.uptime_ratio != null
      ? 'All-time: ' + parseFloat(u.uptime_ratio).toFixed(2) + '%' : 'monitor-ID ikke satt');
    var resp = (k && k.response_ms) || (u && u.response_ms);
    $('#lh-detail-response').text(resp || '—');

    var hasMonitors = (site.kuma_monitor_id && site.kuma_monitor_id !== '')
                   || (site.uptimerobot_monitor_id && site.uptimerobot_monitor_id !== '');
    $('#lh-detail-no-monitors').toggle(!hasMonitors);

    // Reset tab to Status
    showTab('status');
    lhOpenModal('modal-hosting-site');
  };

  // "Legg til site"
  window.lhHostingOpenEdit = function (id) {
    var $form = $('#lh-hosting-form');
    $form[0].reset();
    $form.find('[name=id]').val('');
    $form.find('[name=active]').prop('checked', true);
    $form.find('[name=monthly_cost_nok]').val(0);
    $('#lh-hosting-modal-title').text('Ny site');
    $('#lh-hosting-modal-url').text('');
    $('#lh-detail-no-monitors').hide();
    showTab('config');
    lhOpenModal('modal-hosting-site');
  };

  function showTab(name) {
    $('.lh-tab-btn').removeClass('active').filter('[data-tab="' + name + '"]').addClass('active');
    $('.lh-tab-pane').addClass('lh-hidden').filter('[data-pane="' + name + '"]').removeClass('lh-hidden');
  }
  $(document).on('click', '.lh-tab-btn', function () {
    showTab($(this).data('tab'));
  });

  // Refresh-knapp
  $(document).on('click', '#lh-hosting-refresh', function () {
    fetchStatus(true);
  });

  // Test varsling-knapp
  $(document).on('click', '#lh-hosting-test-alert', function () {
    var $b = $(this).prop('disabled', true);
    lhAjax('edifice_hosting_test_alert', {}, function () {
      $b.prop('disabled', false);
    });
    // Re-enable etter 3s uansett (lhAjax har ingen error-callback)
    setTimeout(function () { $b.prop('disabled', false); }, 3000);
  });

  // Initial fetch (kun hvis siden er synlig)
  $(function () {
    if ($('#lh-hosting-table').length) fetchStatus(false);
  });

})(jQuery);
</script>
