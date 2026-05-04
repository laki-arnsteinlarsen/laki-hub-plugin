<?php defined('ABSPATH') || exit;

$totals   = Edifice_Revenue::get_totals();
$contacts = Edifice_CRM::get_all(['status' => 'active']);
$projects = Edifice_Projects::get_all(['status' => 'active']);
$time_sum = Edifice_Time::get_summary(date('Y-m-01'), date('Y-m-d'));
$month    = date_i18n('F Y');
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div>
      <h1>📊 Dashboard</h1>
      <div class="lh-subtitle">God morgen, Arnstein · <?= esc_html($month) ?></div>
    </div>
  </div>

  <!-- KPI-kort -->
  <div class="lh-stats">
    <div class="lh-stat">
      <div class="lh-stat-label">Fakturert YTD</div>
      <div class="lh-stat-value"><?= number_format($totals['invoiced_ytd'], 0, ',', ' ') ?></div>
      <div class="lh-stat-sub">NOK</div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Betalt YTD</div>
      <div class="lh-stat-value green"><?= number_format($totals['paid_ytd'], 0, ',', ' ') ?></div>
      <div class="lh-stat-sub">NOK</div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Forfalte fakturaer</div>
      <div class="lh-stat-value <?= $totals['overdue'] > 0 ? 'red' : '' ?>"><?= number_format($totals['overdue'], 0, ',', ' ') ?></div>
      <div class="lh-stat-sub">NOK</div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Pipeline</div>
      <div class="lh-stat-value yellow"><?= number_format($totals['pipeline'], 0, ',', ' ') ?></div>
      <div class="lh-stat-sub">NOK utkast</div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Aktive klienter</div>
      <div class="lh-stat-value"><?= count($contacts) ?></div>
      <div class="lh-stat-sub">CRM-kontakter</div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Aktive prosjekter</div>
      <div class="lh-stat-value"><?= count($projects) ?></div>
      <div class="lh-stat-sub">pågående</div>
    </div>
  </div>

  <div class="lh-dash-grid">

    <!-- Aktive prosjekter -->
    <div class="lh-card">
      <div class="lh-card-head">
        <h2>Aktive prosjekter</h2>
        <a href="#projects" class="lh-btn lh-btn-secondary lh-btn-sm lh-section-link" data-section="projects">Se alle →</a>
      </div>
      <div class="lh-card-body" style="padding:0">
        <?php if ($projects): ?>
        <table class="lh-table">
          <thead><tr><th>Prosjekt</th><th>Klient</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($projects, 0, 6) as $p): ?>
            <tr>
              <td><strong><?= esc_html($p['name']) ?></strong></td>
              <td><?= esc_html($p['contact_name'] ?? '—') ?></td>
              <td><span class="lh-badge lh-badge-green">Aktiv</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="lh-empty"><p>Ingen aktive prosjekter.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Timer denne måneden -->
    <div class="lh-card">
      <div class="lh-card-head">
        <h2>Timer denne måneden</h2>
        <a href="#time" class="lh-btn lh-btn-secondary lh-btn-sm lh-section-link" data-section="time">Se alle →</a>
      </div>
      <div class="lh-card-body" style="padding:0">
        <?php if ($time_sum): ?>
        <table class="lh-table">
          <thead><tr><th>Klient</th><th>Timer</th><th>Fakturerbart</th></tr></thead>
          <tbody>
          <?php foreach ($time_sum as $row): ?>
            <tr>
              <td><?= esc_html($row['contact_name'] ?? '—') ?></td>
              <td><?= number_format($row['total_hours'], 1, ',', ' ') ?> t</td>
              <td class="amount"><?= number_format($row['billable_value'], 0, ',', ' ') ?> kr</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="lh-empty"><p>Ingen timer registrert denne måneden.</p></div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- .lh-dash-grid -->
</div>

