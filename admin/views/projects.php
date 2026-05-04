<?php defined('ABSPATH') || exit;
$projects  = Edifice_Projects::get_all();
$contacts  = Edifice_CRM::get_all();
$status_map = [
  'active'    => ['Aktiv',    'green'],
  'on-hold'   => ['På vent',  'yellow'],
  'completed' => ['Fullført', 'blue'],
  'cancelled' => ['Kansellert','gray'],
];
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div><h1>Prosjekter</h1><div class="lh-subtitle"><?= count($projects) ?> prosjekter totalt</div></div>
    <button class="lh-btn lh-btn-primary" onclick="lhOpenModal('modal-project')">+ Nytt prosjekt</button>
  </div>

  <div class="lh-card">
    <div class="lh-table-wrap">
      <?php if ($projects): ?>
      <table class="lh-table">
        <thead>
          <tr><th>Prosjekt</th><th>Klient</th><th>Status</th><th>Start</th><th>Frist</th><th>Budsjett</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($projects as $p):
          [$slabel, $scolor] = $status_map[$p['status']] ?? ['?','gray'];
        ?>
          <tr>
            <td><strong><?= esc_html($p['name']) ?></strong>
              <?php if ($p['description']): ?>
                <div style="font-size:12px;color:var(--lh-muted);margin-top:2px"><?= esc_html(wp_trim_words($p['description'], 10)) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($p['contact_name']): ?>
                <?= esc_html($p['contact_name']) ?>
              <?php else: ?>
                <span class="lh-badge lh-badge-gray">Internt</span>
              <?php endif; ?>
            </td>
            <td><span class="lh-badge lh-badge-<?= $scolor ?>"><?= $slabel ?></span></td>
            <td><?= $p['start_date'] ? date_i18n('d.m.y', strtotime($p['start_date'])) : '—' ?></td>
            <td><?= $p['end_date']   ? date_i18n('d.m.y', strtotime($p['end_date']))   : '—' ?></td>
            <td class="amount"><?= $p['budget'] ? number_format($p['budget'], 0, ',', ' ') . ' kr' : '—' ?></td>
            <td class="actions">
              <button class="lh-btn lh-btn-secondary lh-btn-sm lh-edit-btn"
                data-modal="modal-project" data-record="<?= esc_attr(json_encode($p)) ?>">Rediger</button>
              <button class="lh-btn lh-btn-danger lh-btn-sm lh-delete-btn"
                data-action="edifice_project_delete" data-id="<?= $p['id'] ?>">Slett</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="lh-empty"><p>Ingen prosjekter ennå.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="lh-modal-overlay" id="modal-project">
  <div class="lh-modal">
    <div class="lh-modal-head"><h3>Prosjekt</h3><button class="lh-modal-close">×</button></div>
    <div class="lh-modal-body">
      <form class="lh-ajax-form">
        <input type="hidden" name="ajax_action" value="edifice_project_save">
        <input type="hidden" name="id" value="">
        <div class="lh-form-row">
          <label>Navn *</label>
          <input type="text" name="name" required>
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Klient <span style="font-weight:400;color:var(--lh-muted)">(tom = internt prosjekt)</span></label>
            <select name="contact_id">
              <option value="">— Velg —</option>
              <?php foreach ($contacts as $c): ?>
                <option value="<?= $c['id'] ?>"><?= esc_html($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="lh-form-row">
            <label>Status</label>
            <select name="status">
              <option value="active">Aktiv</option>
              <option value="on-hold">På vent</option>
              <option value="completed">Fullført</option>
              <option value="cancelled">Kansellert</option>
            </select>
          </div>
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row"><label>Startdato</label><input type="date" name="start_date"></div>
          <div class="lh-form-row"><label>Frist</label><input type="date" name="end_date"></div>
        </div>
        <div class="lh-form-row">
          <label>Budsjett (NOK)</label>
          <input type="number" name="budget" step="1000">
        </div>
        <div class="lh-form-row">
          <label>Beskrivelse</label>
          <textarea name="description"></textarea>
        </div>
        <div class="lh-modal-foot" style="padding:0;margin-top:8px">
          <button type="submit" class="lh-btn lh-btn-primary">Lagre</button>
          <button type="button" class="lh-btn lh-btn-secondary lh-modal-close">Avbryt</button>
        </div>
      </form>
    </div>
  </div>
</div>
