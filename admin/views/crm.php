<?php defined('ABSPATH') || exit;
$contacts = Edifice_CRM::get_all();
$categories = Edifice_CRM::CATEGORIES;

$status_labels = ['active' => ['Aktiv','green'], 'lead' => ['Lead','blue'], 'inactive' => ['Inaktiv','gray']];
$type_labels   = [
    'company'     => ['Selskap',     'blue'],
    'person'      => ['Person',      'purple'],
    'association' => ['Forening/lag','teal'],
    'foundation'  => ['Stiftelse',   'orange'],
    'public'      => ['Offentlig',   'gray'],
    'other'       => ['Annet',       'gray'],
];
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div><h1>CRM</h1><div class="lh-subtitle"><?= count($contacts) ?> kontakter</div></div>
    <button class="lh-btn lh-btn-primary" onclick="lhOpenModal('modal-crm')">+ Ny kontakt</button>
  </div>

  <div class="lh-card">
    <div class="lh-card-head"><h2>Alle kontakter</h2></div>
    <div class="lh-card-body" style="padding:16px 20px 0">
      <div class="lh-search">
        <input type="text" id="crm-search" placeholder="Søk navn, org.nr, e-post…">
      </div>
    </div>
    <div class="lh-table-wrap">
      <?php if ($contacts): ?>
      <table class="lh-table" id="crm-table">
        <thead>
          <tr><th>Navn</th><th>Kategorier</th><th>Org.nr</th><th>E-post</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($contacts as $c):
          [$slabel, $scolor] = $status_labels[$c['status']] ?? ['?','gray'];
          [$tlabel, $tcolor] = $type_labels[$c['type']]     ?? ['?','gray'];
        ?>
          <tr>
            <td>
              <strong><?= esc_html($c['name']) ?></strong>
              <span class="lh-badge lh-badge-<?= $tcolor ?>" style="margin-left:5px;font-size:10px"><?= $tlabel ?></span>
            </td>
            <td>
              <?php if (!empty($c['category'])): ?>
                <?php foreach ($c['category'] as $cat):
                  $catColor = $categories[$cat] ?? 'gray';
                ?>
                  <span class="lh-badge lh-badge-<?= $catColor ?>" style="margin-right:3px"><?= esc_html($cat) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="color:var(--lh-muted)">—</span>
              <?php endif; ?>
            </td>
            <td><?= esc_html($c['org_nr']) ?: '—' ?></td>
            <td><?= esc_html($c['email']) ?: '—' ?></td>
            <td><span class="lh-badge lh-badge-<?= $scolor ?>"><?= $slabel ?></span></td>
            <td class="actions">
              <button class="lh-btn lh-btn-secondary lh-btn-sm lh-edit-btn"
                data-modal="modal-crm"
                data-record="<?= esc_attr(json_encode($c)) ?>">Rediger</button>
              <button class="lh-btn lh-btn-danger lh-btn-sm lh-delete-btn"
                data-action="edifice_crm_delete" data-id="<?= $c['id'] ?>">Slett</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="lh-empty"><p>Ingen kontakter ennå. Legg til din første!</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal: CRM -->
<div class="lh-modal-overlay" id="modal-crm">
  <div class="lh-modal">
    <div class="lh-modal-head">
      <h3>Kontakt</h3>
      <button class="lh-modal-close">×</button>
    </div>
    <div class="lh-modal-body">
      <form class="lh-ajax-form" id="crm-form">
        <input type="hidden" name="ajax_action" value="edifice_crm_save">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="brreg_data" value="">
        <input type="hidden" name="category" value="[]">

        <div class="lh-form-row">
          <label>Søk i Brreg</label>
          <input type="text" class="lh-brreg-search" placeholder="Firmanavn eller org.nr…">
          <div class="lh-brreg-results"></div>
        </div>

        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Type</label>
            <select name="type">
              <option value="company">Selskap (AS, ANS, NUF…)</option>
              <option value="person">Person / ENK</option>
              <option value="association">Forening / lag</option>
              <option value="foundation">Stiftelse</option>
              <option value="public">Offentlig sektor</option>
              <option value="other">Annet</option>
            </select>
          </div>
          <div class="lh-form-row">
            <label>Status</label>
            <select name="status">
              <option value="active">Aktiv</option>
              <option value="lead">Lead</option>
              <option value="inactive">Inaktiv</option>
            </select>
          </div>
        </div>

        <div class="lh-form-row">
          <label>Navn *</label>
          <input type="text" name="name" required>
        </div>

        <div class="lh-form-row">
          <label>Kategorier</label>
          <div class="lh-check-group">
            <?php foreach ($categories as $cat => $color): ?>
            <label class="lh-check-label">
              <input type="checkbox" class="lh-cat-check" value="<?= esc_attr($cat) ?>">
              <span class="lh-badge lh-badge-<?= $color ?>"><?= esc_html($cat) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Org.nr</label>
            <input type="text" name="org_nr" placeholder="000 000 000">
          </div>
          <div class="lh-form-row">
            <label>E-post</label>
            <input type="email" name="email">
          </div>
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Telefon</label>
            <input type="text" name="phone">
          </div>
          <div class="lh-form-row">
            <label>Adresse</label>
            <input type="text" name="address">
          </div>
        </div>
        <div class="lh-form-row">
          <label>Notater</label>
          <textarea name="notes" placeholder="Interne notater…"></textarea>
        </div>

        <div class="lh-modal-foot" style="padding:0;margin-top:8px">
          <button type="submit" class="lh-btn lh-btn-primary">Lagre kontakt</button>
          <button type="button" class="lh-btn lh-btn-secondary lh-modal-close">Avbryt</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Live search filter
document.getElementById('crm-search')?.addEventListener('input', function(){
  const q = this.value.toLowerCase();
  document.querySelectorAll('#crm-table tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Sync checkboxer → hidden category-felt før innsending
document.getElementById('crm-form')?.addEventListener('submit', function() {
  const checked = [...this.querySelectorAll('.lh-cat-check:checked')].map(cb => cb.value);
  this.querySelector('[name=category]').value = JSON.stringify(checked);
}, true); // capture = true, kjør FØR generic handler
</script>
