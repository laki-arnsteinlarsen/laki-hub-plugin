<?php
defined('ABSPATH') || exit;

$contacts       = Edifice_CRM::get_all();
$companies      = Edifice_CRM::get_companies();   // for person-form dropdown
$gmail_on       = Edifice_Gmail::is_connected();
$country_codes  = Edifice_CRM::country_codes();

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
          <tr class="lh-clickable-row lh-view-crm-btn"
              data-type="<?= esc_attr($c['type']) ?>"
              data-record="<?= esc_attr(json_encode($c)) ?>">
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
            <td><?= esc_html(Edifice_CRM::format_phone($c['phone'] ?? '')) ?: '—' ?></td>
            <td><?= $cat_arr ? esc_html(implode(', ', $cat_arr)) : '—' ?></td>
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
            <div style="display:flex;gap:6px">
              <select name="phone_cc" style="max-width:140px;flex:0 0 auto">
                <?php foreach ($country_codes as $cn): ?>
                  <option value="<?= esc_attr($cn['cc']) ?>" data-flag="<?= esc_attr($cn['flag']) ?>">
                    <?= esc_html($cn['flag'] . ' ' . $cn['cc'] . ' ' . $cn['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="phone_national" placeholder="91 23 45 67" style="flex:1">
            </div>
          </div>
        </div>
        <div class="lh-form-row">
          <label>Adresse</label>
          <input type="text" name="address">
        </div>

        <details class="lh-form-details" style="margin:12px 0 4px">
          <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--lh-muted);padding:6px 0">
            🔗 Lenker (LinkedIn, Instagram, Facebook, X, TikTok, valgfri)
          </summary>
          <div style="padding-top:10px">
            <div class="lh-form-grid">
              <div class="lh-form-row">
                <label>LinkedIn</label>
                <input type="text" name="linkedin_url" placeholder="https://linkedin.com/in/…">
              </div>
              <div class="lh-form-row">
                <label>Instagram</label>
                <input type="text" name="instagram_url" placeholder="https://instagram.com/…">
              </div>
            </div>
            <div class="lh-form-grid">
              <div class="lh-form-row">
                <label>Facebook</label>
                <input type="text" name="facebook_url" placeholder="https://facebook.com/…">
              </div>
              <div class="lh-form-row">
                <label>X (tidligere Twitter)</label>
                <input type="text" name="x_url" placeholder="https://x.com/…">
              </div>
            </div>
            <div class="lh-form-grid">
              <div class="lh-form-row">
                <label>TikTok</label>
                <input type="text" name="tiktok_url" placeholder="https://tiktok.com/@…">
              </div>
              <div class="lh-form-row">
                <label>Valgfri URL</label>
                <input type="text" name="custom_url" placeholder="https://…">
              </div>
            </div>
          </div>
        </details>

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

<style>
/* Hele raden er klikkbar — viser cursor + hover-effekt */
.lh-clickable-row { cursor: pointer; }
.lh-clickable-row:hover { background: #f8fafc; }
.lh-clickable-row:hover td:first-child strong { color: #1e3a5f; }

/* Sosiale URL-ikoner i view-modal */
.lh-social-icons { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px }
.lh-social-icon {
  display:inline-flex; align-items:center; gap:6px;
  padding:6px 12px; border-radius:8px;
  background:#f1f5f9; color:#1e293b;
  text-decoration:none; font-size:13px; font-weight:500;
  border:1px solid #e2e8f0;
  transition:background .15s;
}
.lh-social-icon:hover { background:#e2e8f0 }

/* Drill-down: hele person-raden klikkbar */
.lh-person-row.lh-clickable { cursor:pointer; transition:background .15s }
.lh-person-row.lh-clickable:hover { background:#f1f5f9 }
</style>
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

/* ── Action-knapper i raden skal IKKE trigge view-modal ──────────────────── */
document.querySelectorAll('#crm-table .actions').forEach(td => {
  td.addEventListener('click', e => e.stopPropagation());
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
