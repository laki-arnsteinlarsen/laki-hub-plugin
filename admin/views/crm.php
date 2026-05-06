<?php
defined('ABSPATH') || exit;

$contacts  = Edifice_CRM::get_all();
$companies = Edifice_CRM::get_companies();   // for person-form dropdown
$gmail_on  = Edifice_Gmail::is_connected();

$status_labels = [
    'active'   => ['Aktiv',   'green'],
    'lead'     => ['Lead',    'blue'],
    'inactive' => ['Inaktiv', 'gray'],
];
$n_companies = count(array_filter($contacts, fn($c) => $c['type'] === 'company'));
$n_persons   = count($contacts) - $n_companies;
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div>
      <h1>CRM</h1>
      <div class="lh-subtitle"><?= $n_companies ?> selskaper · <?= $n_persons ?> personer</div>
    </div>
    <button class="lh-btn lh-btn-primary" onclick="lhOpenModal('modal-crm')">+ Ny kontakt</button>
  </div>

  <div class="lh-card">
    <div class="lh-card-head">
      <div style="display:flex;align-items:center;gap:14px">
        <h2>Alle kontakter</h2>
        <div class="lh-filter-tabs">
          <button class="lh-ftab active" data-filter="all">Alle</button>
          <button class="lh-ftab" data-filter="company">Selskaper</button>
          <button class="lh-ftab" data-filter="person">Personer</button>
        </div>
      </div>
    </div>
    <div class="lh-card-body" style="padding:12px 20px 0">
      <div class="lh-search">
        <input type="text" id="crm-search" placeholder="Søk navn, org.nr, e-post…">
      </div>
    </div>

    <div class="lh-table-wrap">
      <?php if ($contacts): ?>
      <table class="lh-table" id="crm-table">
        <thead>
          <tr>
            <th>Navn</th><th>E-post</th><th>Telefon</th><th>Kategori</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($contacts as $c):
          [$slabel, $scolor] = $status_labels[$c['status']] ?? ['?', 'gray'];
          $icon = $c['type'] === 'company' ? '🏢' : '👤';
          $cat_arr = is_array($c['category']) ? $c['category'] : [];
        ?>
          <tr data-type="<?= esc_attr($c['type']) ?>">
            <td>
              <strong>
                <span style="margin-right:4px"><?= $icon ?></span><?= esc_html($c['name']) ?>
              </strong>
              <?php if ($c['type'] === 'person' && !empty($c['company_name'])): ?>
                <div class="lh-person-company"><?= esc_html($c['company_name']) ?></div>
              <?php elseif ($c['type'] === 'company' && !empty($c['org_nr'])): ?>
                <div style="font-size:11px;color:var(--lh-muted);margin-top:2px"><?= esc_html($c['org_nr']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= esc_html($c['email']) ?: '—' ?></td>
            <td><?= esc_html($c['phone']) ?: '—' ?></td>
            <td><?= $cat_arr ? esc_html(implode(', ', $cat_arr)) : '—' ?></td>
            <td><span class="lh-badge lh-badge-<?= $scolor ?>"><?= $slabel ?></span></td>
            <td class="actions">
              <button class="lh-btn lh-btn-secondary lh-btn-sm lh-view-crm-btn"
                data-record="<?= esc_attr(json_encode($c)) ?>">Vis</button>
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

<!-- ══════════════════════════════════════════════════════════════════════════
     VIEW MODAL  (read-only)
══════════════════════════════════════════════════════════════════════════ -->
<div class="lh-modal-overlay" id="modal-crm-view">
  <div class="lh-modal lh-modal-wide">
    <div class="lh-modal-head">
      <div>
        <span id="view-crm-type-badge" class="lh-badge" style="margin-bottom:6px;display:inline-block"></span>
        <h3 id="view-crm-name" style="margin:0;font-size:18px"></h3>
      </div>
      <button class="lh-modal-close">×</button>
    </div>
    <div class="lh-modal-body">
      <!-- Core fields grid -->
      <div id="view-crm-fields" class="lh-view-grid"></div>

      <!-- Notes -->
      <div id="view-crm-notes-wrap" style="display:none;margin-top:16px">
        <div class="lh-view-section-title">Notater</div>
        <div id="view-crm-notes" class="lh-view-notes"></div>
      </div>

      <!-- Persons list (companies only) -->
      <div id="view-crm-persons-section" style="display:none;margin-top:20px">
        <div class="lh-view-section-title">Kontaktpersoner</div>
        <div id="view-crm-persons-list"></div>
      </div>

      <!-- Gmail email history (persons with email) -->
      <div id="view-crm-gmail-section" style="display:none;margin-top:20px">
        <div class="lh-view-section-title" style="display:flex;align-items:center;gap:10px">
          <span>📧 E-posthistorikk</span>
          <span id="view-gmail-status" class="lh-badge lh-badge-gray" style="font-size:10px">Laster…</span>
        </div>
        <div id="view-crm-gmail-list" style="margin-top:10px"></div>
      </div>
    </div>
    <div class="lh-modal-foot">
      <button class="lh-btn lh-btn-secondary lh-modal-close">Lukk</button>
      <button class="lh-btn lh-btn-primary" id="view-crm-edit-btn">✏️ Rediger</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     EDIT / NEW MODAL
══════════════════════════════════════════════════════════════════════════ -->
<div class="lh-modal-overlay" id="modal-crm">
  <div class="lh-modal">
    <div class="lh-modal-head">
      <h3>Kontakt</h3>
      <button class="lh-modal-close">×</button>
    </div>
    <div class="lh-modal-body">
      <form class="lh-ajax-form">
        <input type="hidden" name="ajax_action" value="edifice_crm_save">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="brreg_data" value="">

        <div class="lh-form-row">
          <label>Søk i Brreg</label>
          <input type="text" class="lh-brreg-search" placeholder="Firmanavn eller org.nr…">
          <div class="lh-brreg-results"></div>
        </div>

        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Type</label>
            <select name="type" id="crm-type-select">
              <option value="company">🏢 Selskap</option>
              <option value="person">👤 Person</option>
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

        <!-- Company link (persons only) -->
        <div class="lh-form-row" id="crm-company-row" style="display:none">
          <label>Tilknyttet selskap</label>
          <select name="company_id">
            <option value="">— Velg selskap —</option>
            <?php foreach ($companies as $co): ?>
              <option value="<?= $co['id'] ?>"><?= esc_html($co['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="lh-form-row">
          <label>Navn *</label>
          <input type="text" name="name" required>
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row" id="crm-orgnr-row">
            <label>Org.nr</label>
            <input type="text" name="org_nr" placeholder="000 000 000">
          </div>
          <div class="lh-form-row">
            <label>Kategori</label>
            <input type="text" name="category" placeholder="Klient, Partner, Leverandør…">
          </div>
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>E-post</label>
            <input type="email" name="email">
          </div>
          <div class="lh-form-row">
            <label>Telefon</label>
            <input type="text" name="phone">
          </div>
        </div>
        <div class="lh-form-row">
          <label>Adresse</label>
          <input type="text" name="address">
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
/* ── Filter tabs ────────────────────────────────────────────────────────── */
document.querySelectorAll('.lh-ftab').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.lh-ftab').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const f = this.dataset.filter;
    const q = (document.getElementById('crm-search')?.value || '').toLowerCase();
    document.querySelectorAll('#crm-table tbody tr').forEach(row => {
      const typeOk = f === 'all' || row.dataset.type === f;
      const textOk = !q || row.textContent.toLowerCase().includes(q);
      row.style.display = typeOk && textOk ? '' : 'none';
    });
  });
});

/* ── Live search ─────────────────────────────────────────────────────────── */
document.getElementById('crm-search')?.addEventListener('input', function () {
  const q = this.value.toLowerCase();
  const af = document.querySelector('.lh-ftab.active')?.dataset.filter || 'all';
  document.querySelectorAll('#crm-table tbody tr').forEach(row => {
    const typeOk = af === 'all' || row.dataset.type === af;
    const textOk = !q || row.textContent.toLowerCase().includes(q);
    row.style.display = typeOk && textOk ? '' : 'none';
  });
});

/* ── Type toggle in edit form ────────────────────────────────────────────── */
function crmTypeToggle(val) {
  const isPerson = val === 'person';
  document.getElementById('crm-company-row').style.display = isPerson ? '' : 'none';
  document.getElementById('crm-orgnr-row').style.display   = isPerson ? 'none' : '';
}
document.getElementById('crm-type-select')?.addEventListener('change', function () {
  crmTypeToggle(this.value);
});
// Reset on modal open
document.getElementById('modal-crm')?.addEventListener('lh:opened', function () {
  crmTypeToggle(document.getElementById('crm-type-select')?.value || 'company');
});
</script>
