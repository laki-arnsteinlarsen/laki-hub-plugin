<?php defined('ABSPATH') || exit;
$items    = LakiHub_Revenue::get_all();
$totals   = LakiHub_Revenue::get_totals();
$contacts = LakiHub_CRM::get_all();
$projects = LakiHub_Projects::get_all();
$status_map = [
  'draft'   => ['Utkast',   'gray'],
  'sent'    => ['Sendt',    'blue'],
  'paid'    => ['Betalt',   'green'],
  'overdue' => ['Forfalt',  'red'],
];
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div><h1>Inntekt</h1><div class="lh-subtitle">Fakturaer og betalinger</div></div>
    <button class="lh-btn lh-btn-primary" onclick="lhOpenModal('modal-revenue')">+ Ny faktura</button>
  </div>

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
      <div class="lh-stat-label">Forfalte</div>
      <div class="lh-stat-value <?= $totals['overdue'] > 0 ? 'red' : '' ?>"><?= number_format($totals['overdue'], 0, ',', ' ') ?></div>
      <div class="lh-stat-sub">NOK</div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Pipeline (utkast)</div>
      <div class="lh-stat-value yellow"><?= number_format($totals['pipeline'], 0, ',', ' ') ?></div>
      <div class="lh-stat-sub">NOK</div>
    </div>
  </div>

  <div class="lh-card">
    <div class="lh-table-wrap">
      <?php if ($items): ?>
      <table class="lh-table">
        <thead>
          <tr><th>Faktura#</th><th>Klient</th><th>Beskrivelse</th><th>Beløp</th><th>Dato</th><th>Forfaller</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item):
          [$slabel, $scolor] = $status_map[$item['status']] ?? ['?','gray'];
        ?>
          <tr>
            <td><?= esc_html($item['invoice_nr']) ?: '—' ?></td>
            <td><?= esc_html($item['contact_name'] ?? '—') ?></td>
            <td><?= esc_html(wp_trim_words($item['description'], 8)) ?></td>
            <td class="amount"><?= number_format($item['amount'], 0, ',', ' ') ?> <?= esc_html($item['currency']) ?></td>
            <td style="white-space:nowrap"><?= date_i18n('d.m.Y', strtotime($item['date'])) ?></td>
            <td style="white-space:nowrap"><?= $item['due_date'] ? date_i18n('d.m.Y', strtotime($item['due_date'])) : '—' ?></td>
            <td><span class="lh-badge lh-badge-<?= $scolor ?>"><?= $slabel ?></span></td>
            <td class="actions">
              <button class="lh-btn lh-btn-secondary lh-btn-sm lh-edit-btn"
                data-modal="modal-revenue" data-record="<?= esc_attr(json_encode($item)) ?>">Rediger</button>
              <button class="lh-btn lh-btn-danger lh-btn-sm lh-delete-btn"
                data-action="laki_revenue_delete" data-id="<?= $item['id'] ?>">Slett</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="lh-empty"><p>Ingen fakturaer ennå.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="lh-modal-overlay" id="modal-revenue">
  <div class="lh-modal">
    <div class="lh-modal-head"><h3>Faktura / Betaling</h3><button class="lh-modal-close">×</button></div>
    <div class="lh-modal-body">
      <form class="lh-ajax-form">
        <input type="hidden" name="ajax_action" value="laki_revenue_save">
        <input type="hidden" name="id" value="">
        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Fakturanr</label>
            <input type="text" name="invoice_nr" placeholder="2025-001">
          </div>
          <div class="lh-form-row">
            <label>Status</label>
            <select name="status">
              <option value="draft">Utkast</option>
              <option value="sent">Sendt</option>
              <option value="paid">Betalt</option>
              <option value="overdue">Forfalt</option>
            </select>
          </div>
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Klient</label>
            <select name="contact_id">
              <option value="">— Velg —</option>
              <?php foreach ($contacts as $c): ?>
                <option value="<?= $c['id'] ?>"><?= esc_html($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="lh-form-row">
            <label>Prosjekt</label>
            <select name="project_id">
              <option value="">— Velg —</option>
              <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>"><?= esc_html($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="lh-form-row">
          <label>Beskrivelse</label>
          <input type="text" name="description">
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Beløp (NOK) *</label>
            <input type="number" name="amount" step="100" required>
          </div>
          <div class="lh-form-row">
            <label>Valuta</label>
            <select name="currency"><option value="NOK">NOK</option><option value="EUR">EUR</option><option value="USD">USD</option></select>
          </div>
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row"><label>Fakturadato *</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
          <div class="lh-form-row"><label>Forfallsdato</label><input type="date" name="due_date"></div>
        </div>
        <div class="lh-modal-foot" style="padding:0;margin-top:8px">
          <button type="submit" class="lh-btn lh-btn-primary">Lagre</button>
          <button type="button" class="lh-btn lh-btn-secondary lh-modal-close">Avbryt</button>
        </div>
      </form>
    </div>
  </div>
</div>
