<?php defined('ABSPATH') || exit;
$entries  = LakiHub_Time::get_all();
$contacts = LakiHub_CRM::get_all();
$projects = LakiHub_Projects::get_all();
$total_h  = array_sum(array_column($entries, 'hours'));
$bill_h   = array_sum(array_column(array_filter($entries, fn($e) => $e['billable']), 'hours'));
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div><h1>Timeføring</h1><div class="lh-subtitle"><?= number_format($total_h, 1, ',', ' ') ?> timer totalt · <?= number_format($bill_h, 1, ',', ' ') ?> fakturerbare</div></div>
    <button class="lh-btn lh-btn-primary" onclick="lhOpenModal('modal-time')">+ Logg timer</button>
  </div>

  <div class="lh-card">
    <div class="lh-table-wrap">
      <?php if ($entries): ?>
      <table class="lh-table">
        <thead>
          <tr><th>Dato</th><th>Klient</th><th>Prosjekt</th><th>Beskrivelse</th><th>Timer</th><th>Fakturerbart</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $e): ?>
          <tr>
            <td style="white-space:nowrap"><?= date_i18n('d.m.Y', strtotime($e['date'])) ?></td>
            <td><?= esc_html($e['contact_name'] ?? '—') ?></td>
            <td><?= esc_html($e['project_name'] ?? '—') ?></td>
            <td><?= esc_html($e['description']) ?></td>
            <td><strong><?= number_format($e['hours'], 1, ',', '.') ?></strong> t</td>
            <td><?= $e['billable'] ? '<span class="lh-badge lh-badge-green">Ja</span>' : '<span class="lh-badge lh-badge-gray">Nei</span>' ?></td>
            <td class="actions">
              <button class="lh-btn lh-btn-secondary lh-btn-sm lh-edit-btn"
                data-modal="modal-time" data-record="<?= esc_attr(json_encode($e)) ?>">Rediger</button>
              <button class="lh-btn lh-btn-danger lh-btn-sm lh-delete-btn"
                data-action="laki_time_delete" data-id="<?= $e['id'] ?>">Slett</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="lh-empty"><p>Ingen timer registrert ennå.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="lh-modal-overlay" id="modal-time">
  <div class="lh-modal">
    <div class="lh-modal-head"><h3>Logg timer</h3><button class="lh-modal-close">×</button></div>
    <div class="lh-modal-body">
      <form class="lh-ajax-form">
        <input type="hidden" name="ajax_action" value="laki_time_save">
        <input type="hidden" name="id" value="">
        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Dato *</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="lh-form-row">
            <label>Timer *</label>
            <input type="number" name="hours" step="0.25" min="0.25" max="24" placeholder="1.5" required>
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
          <input type="text" name="description" placeholder="Hva jobbet du med?">
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Timepris (NOK)</label>
            <input type="number" name="hourly_rate" step="50" placeholder="1500">
          </div>
          <div class="lh-form-row" style="display:flex;align-items:flex-end;padding-bottom:4px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="billable" checked style="width:auto"> Fakturerbart
            </label>
          </div>
        </div>
        <div class="lh-modal-foot" style="padding:0;margin-top:8px">
          <button type="submit" class="lh-btn lh-btn-primary">Lagre</button>
          <button type="button" class="lh-btn lh-btn-secondary lh-modal-close">Avbryt</button>
        </div>
      </form>
    </div>
  </div>
</div>
