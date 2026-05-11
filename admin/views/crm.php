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

      <!-- Interaksjonslogg (strukturert) -->
      <div id="view-crm-interactions-section" style="margin-top:20px">
        <div class="lh-view-section-title" style="display:flex;align-items:center;gap:10px">
          <span>📋 Interaksjoner</span>
          <button type="button" class="lh-btn lh-btn-primary lh-btn-sm" id="view-crm-log-btn"
                  style="margin-left:auto">+ Logg</button>
        </div>
        <div id="view-crm-interactions-list" style="margin-top:10px">
          <div style="color:var(--lh-muted);font-size:13px;padding:8px 0">Laster…</div>
        </div>
      </div>
    </div>
    <div class="lh-modal-foot">
      <button class="lh-btn lh-btn-secondary lh-modal-close">Lukk</button>
      <button class="lh-btn lh-btn-primary" id="view-crm-edit-btn">✏️ Rediger</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     LOG INTERACTION MODAL
══════════════════════════════════════════════════════════════════════════ -->
<div class="lh-modal-overlay" id="modal-interaction-log">
  <div class="lh-modal">
    <div class="lh-modal-head">
      <h3>Logg interaksjon</h3>
      <button class="lh-modal-close">×</button>
    </div>
    <div class="lh-modal-body">
      <input type="hidden" id="interaction-contact-id">
      <div class="lh-form-row">
        <label>Kontakt</label>
        <div id="interaction-contact-name" style="font-weight:600;padding:6px 0"></div>
      </div>
      <div class="lh-form-grid">
        <div class="lh-form-row">
          <label for="interaction-dato">Dato <span style="color:#dc2626">*</span></label>
          <input type="date" id="interaction-dato">
        </div>
        <div class="lh-form-row">
          <label for="interaction-tid">Tid (valgfri)</label>
          <input type="time" id="interaction-tid">
        </div>
      </div>
      <div class="lh-form-grid">
        <div class="lh-form-row">
          <label for="interaction-kanal">Kanal <span style="color:#dc2626">*</span></label>
          <select id="interaction-kanal">
            <?php foreach (Edifice_Interactions::KANALER as $k => $label): ?>
              <option value="<?= esc_attr($k) ?>"><?= esc_html($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="lh-form-row">
          <label for="interaction-retning">Retning</label>
          <select id="interaction-retning">
            <?php foreach (Edifice_Interactions::RETNINGER as $k => $label): ?>
              <option value="<?= esc_attr($k) ?>" <?= $k === 'toveis' ? 'selected' : '' ?>><?= esc_html($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="lh-form-row">
        <label for="interaction-sammendrag">Sammendrag <span style="color:#dc2626">*</span></label>
        <input type="text" id="interaction-sammendrag" maxlength="500"
               placeholder="Kort beskrivelse av interaksjonen">
      </div>
      <div class="lh-form-row">
        <label for="interaction-notat">Notat (valgfritt)</label>
        <textarea id="interaction-notat" rows="3"
                  placeholder="Detaljer, sitater, neste steg …"></textarea>
      </div>
    </div>
    <div class="lh-modal-foot">
      <div style="flex:1"></div>
      <button class="lh-btn lh-btn-secondary lh-modal-close">Avbryt</button>
      <button class="lh-btn lh-btn-primary" id="interaction-save-btn">Lagre</button>
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

        <!-- Company links (persons only — flere selskaper med rolle) -->
        <div class="lh-form-row" id="crm-company-row" style="display:none">
          <label>Tilknyttet selskap (første blir primær)</label>
          <div id="crm-companies-list"></div>
          <button type="button" class="lh-btn lh-btn-secondary lh-btn-sm" id="crm-add-company-btn"
                  style="margin-top:6px">+ Legg til selskap</button>
          <input type="hidden" name="companies_json" id="crm-companies-json" value="">
          <template id="crm-company-row-tpl">
            <div class="lh-link-row" style="display:flex;gap:6px;margin-top:6px;align-items:center">
              <select class="js-company-select" style="flex:2">
                <option value="">— Velg selskap —</option>
                <?php foreach ($companies as $co): ?>
                  <option value="<?= $co['id'] ?>"><?= esc_html($co['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" class="js-company-role" placeholder="Rolle (valgfri)" style="flex:1">
              <button type="button" class="lh-btn lh-btn-danger lh-btn-sm js-remove-company-row"
                      title="Fjern" style="padding:4px 10px">×</button>
            </div>
          </template>
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
            <label>E-post (primær)</label>
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
          <label>Mobil</label>
          <div style="display:flex;gap:6px">
            <select name="mobile_cc" style="max-width:140px;flex:0 0 auto">
              <?php foreach ($country_codes as $cn): ?>
                <option value="<?= esc_attr($cn['cc']) ?>" data-flag="<?= esc_attr($cn['flag']) ?>">
                  <?= esc_html($cn['flag'] . ' ' . $cn['cc'] . ' ' . $cn['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="mobile_national" placeholder="91 23 45 67" style="flex:1">
          </div>
        </div>

        <!-- Ekstra e-postadresser -->
        <div class="lh-form-row">
          <label>Flere e-postadresser</label>
          <div id="crm-emails-list"></div>
          <button type="button" class="lh-btn lh-btn-secondary lh-btn-sm" id="crm-add-email-btn"
                  style="margin-top:6px">+ Legg til e-post</button>
          <input type="hidden" name="extra_emails_json" id="crm-extra-emails-json" value="">
          <template id="crm-email-row-tpl">
            <div class="lh-link-row" style="display:flex;gap:6px;margin-top:6px;align-items:center">
              <input type="email" class="js-email-input" placeholder="navn@example.com" style="flex:2">
              <input type="text"  class="js-email-label" placeholder="Etikett (Privat, Jobb…)" style="flex:1">
              <button type="button" class="lh-btn lh-btn-danger lh-btn-sm js-remove-email-row"
                      title="Fjern" style="padding:4px 10px">×</button>
            </div>
          </template>
        </div>

        <!-- Adresser: begge typer har begge felter, label endrer seg etter type.
             Begge har Geonorge-autocomplete (Kartverket sin åpne API). -->
        <div class="lh-form-row">
          <label id="crm-address-label">Adresse</label>
          <input type="text" name="address" class="lh-addr-autocomplete" autocomplete="off">
        </div>
        <div class="lh-form-row" id="crm-postal-address-row">
          <label>Postadresse</label>
          <input type="text" name="postal_address" class="lh-addr-autocomplete" autocomplete="off">
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

/* Sosiale URL-ikoner i view-modal — kompakte 32×32 SVG-knapper */
.lh-social-icons { display:flex; flex-wrap:wrap; gap:6px; margin-top:4px }
.lh-social-icon {
  width:32px; height:32px;
  display:inline-flex; align-items:center; justify-content:center;
  border-radius:7px;
  background:#f1f5f9;
  color:#475569;
  text-decoration:none;
  border:1px solid #e2e8f0;
  transition:background .15s, color .15s, border-color .15s, transform .1s;
}
.lh-social-icon svg { width:16px; height:16px; display:block }
.lh-social-icon:hover {
  background: var(--brand, #1e293b);
  color:#ffffff;
  border-color: var(--brand, #1e293b);
}
.lh-social-icon:active { transform: scale(.96) }

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
  // Begge typer har postadresse — bare label-teksten endres
  const addrLabel = document.getElementById('crm-address-label');
  if (addrLabel) addrLabel.textContent = isPerson ? 'Hjemmeadresse' : 'Besøksadresse';
}
document.getElementById('crm-type-select')?.addEventListener('change', function () {
  crmTypeToggle(this.value);
});
// Reset on modal open. Hvis "Ny kontakt"-knappen brukes (ikke edit) — tøm
// dynamiske rader. Edit-knappen kaller lhFillForm som styrer dette selv.
document.getElementById('modal-crm')?.addEventListener('lh:opened', function () {
  crmTypeToggle(document.getElementById('crm-type-select')?.value || 'company');
  // Hvis form er nullstilt (id-input er tom), tøm dynamiske lister
  const idInput = document.querySelector('#modal-crm form [name="id"]');
  if (idInput && !idInput.value) {
    document.getElementById('crm-emails-list')?.replaceChildren();
    document.getElementById('crm-companies-list')?.replaceChildren();
  }
});

/* ── Dynamiske rader: e-poster og selskaper ─────────────────────────────── */
function crmAddEmailRow(prefill) {
  const tpl = document.getElementById('crm-email-row-tpl');
  const list = document.getElementById('crm-emails-list');
  if (!tpl || !list) return;
  const node = tpl.content.cloneNode(true);
  const row  = node.querySelector('.lh-link-row');
  if (prefill) {
    row.querySelector('.js-email-input').value = prefill.email || '';
    row.querySelector('.js-email-label').value = prefill.label || '';
  }
  row.querySelector('.js-remove-email-row').addEventListener('click', () => row.remove());
  list.appendChild(row);
}
function crmAddCompanyRow(prefill) {
  const tpl = document.getElementById('crm-company-row-tpl');
  const list = document.getElementById('crm-companies-list');
  if (!tpl || !list) return;
  const node = tpl.content.cloneNode(true);
  const row  = node.querySelector('.lh-link-row');
  if (prefill) {
    row.querySelector('.js-company-select').value = prefill.company_id || '';
    row.querySelector('.js-company-role').value   = prefill.role || '';
  }
  row.querySelector('.js-remove-company-row').addEventListener('click', () => row.remove());
  list.appendChild(row);
}
document.getElementById('crm-add-email-btn')?.addEventListener('click', () => crmAddEmailRow());
document.getElementById('crm-add-company-btn')?.addEventListener('click', () => crmAddCompanyRow());

// Eksponer som globaler så admin.js (lhFillForm) kan pre-fylle ved redigering
window.crmAddEmailRow   = crmAddEmailRow;
window.crmAddCompanyRow = crmAddCompanyRow;
window.crmTypeToggle    = crmTypeToggle;

/* ── Geonorge adresse-autocomplete (Kartverket åpen API) ─────────────────── */
function bindGeonorgeAutocomplete(input) {
  if (input.dataset.geonorgeBound) return;
  input.dataset.geonorgeBound = '1';

  // Pakk input i relativ container slik at dropdown kan posisjoneres absolutt
  const wrapper = document.createElement('div');
  wrapper.style.cssText = 'position:relative';
  input.parentNode.insertBefore(wrapper, input);
  wrapper.appendChild(input);

  const dropdown = document.createElement('div');
  dropdown.className = 'lh-geonorge-results';
  dropdown.style.cssText = 'display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;'
    + 'background:#fff;border:1px solid #e2e8f0;border-radius:8px;'
    + 'max-height:280px;overflow-y:auto;z-index:9999;'
    + 'box-shadow:0 8px 24px rgba(15,23,42,.08)';
  wrapper.appendChild(dropdown);

  let timer;
  let activeIdx = -1;
  const escapeHtml = s => String(s).replace(/[&<>"]/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]);

  function renderDropdown(items) {
    if (!items || !items.length) {
      dropdown.style.display = 'none';
      activeIdx = -1;
      return;
    }
    dropdown.innerHTML = items.map((a, i) => {
      const text = `${a.adressetekst}, ${a.postnummer} ${a.poststed}`;
      return `<div class="lh-geonorge-item" data-idx="${i}" data-text="${escapeHtml(text)}"
        style="padding:10px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px">
        <div style="font-weight:500">${escapeHtml(text)}</div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px">${escapeHtml(a.kommunenavn || '')}</div>
      </div>`;
    }).join('');
    dropdown.style.display = 'block';
    activeIdx = -1;
  }

  function fetchAddresses(q) {
    const url = 'https://ws.geonorge.no/adresser/v1/sok?treffPerSide=8&utkoordsys=4258&sok='
              + encodeURIComponent(q);
    return fetch(url).then(r => r.json())
      .then(data => data.adresser || [])
      .catch(() => []);
  }

  input.addEventListener('input', function () {
    clearTimeout(timer);
    const q = this.value.trim();
    if (q.length < 3) { dropdown.style.display = 'none'; return; }
    timer = setTimeout(() => fetchAddresses(q).then(renderDropdown), 250);
  });

  // Klikk på element velger adresse
  dropdown.addEventListener('mousedown', function (e) {
    const item = e.target.closest('.lh-geonorge-item');
    if (!item) return;
    e.preventDefault(); // hindrer blur før vi får oppdatert verdi
    input.value = item.dataset.text;
    dropdown.style.display = 'none';
    input.focus();
  });

  // Tastatur-navigasjon (↑ ↓ Enter Esc)
  input.addEventListener('keydown', function (e) {
    const items = dropdown.querySelectorAll('.lh-geonorge-item');
    if (!items.length || dropdown.style.display === 'none') return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIdx = Math.min(activeIdx + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIdx = Math.max(activeIdx - 1, 0);
    } else if (e.key === 'Enter' && activeIdx >= 0) {
      e.preventDefault();
      input.value = items[activeIdx].dataset.text;
      dropdown.style.display = 'none';
      return;
    } else if (e.key === 'Escape') {
      dropdown.style.display = 'none';
      activeIdx = -1;
      return;
    } else {
      return;
    }
    items.forEach((el, i) => {
      el.style.background = i === activeIdx ? '#f1f5f9' : '';
    });
    items[activeIdx]?.scrollIntoView({ block: 'nearest' });
  });

  // Lukk dropdown når input mister fokus (delay for å rekke klikk)
  input.addEventListener('blur', () => setTimeout(() => {
    dropdown.style.display = 'none';
  }, 150));
}

// Bind ved load + ved type-toggle (i tilfelle felt rendres senere)
function bindAllGeonorge() {
  document.querySelectorAll('.lh-addr-autocomplete').forEach(bindGeonorgeAutocomplete);
}
bindAllGeonorge();
document.getElementById('modal-crm')?.addEventListener('lh:opened', bindAllGeonorge);

/* ── Serialiser dynamiske rader til JSON før form-submit ─────────────────── */
document.querySelector('#modal-crm form')?.addEventListener('submit', function () {
  // E-poster
  const emails = [];
  document.querySelectorAll('#crm-emails-list .lh-link-row').forEach(r => {
    const e = r.querySelector('.js-email-input').value.trim();
    const l = r.querySelector('.js-email-label').value.trim();
    if (e) emails.push({ email: e, label: l });
  });
  document.getElementById('crm-extra-emails-json').value = JSON.stringify(emails);

  // Selskaper
  const companies = [];
  document.querySelectorAll('#crm-companies-list .lh-link-row').forEach(r => {
    const cid = r.querySelector('.js-company-select').value;
    const rl  = r.querySelector('.js-company-role').value.trim();
    if (cid) companies.push({ company_id: parseInt(cid, 10), role: rl });
  });
  document.getElementById('crm-companies-json').value = JSON.stringify(companies);
});
</script>
