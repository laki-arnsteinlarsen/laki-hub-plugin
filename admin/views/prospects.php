<?php
defined('ABSPATH') || exit;

$prospects = Edifice_Prospects::get_all(['status' => 'active']);
$counts    = Edifice_Prospects::counts();
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div>
      <h1>🎯 Prospekter</h1>
      <div class="lh-subtitle">
        Rådgivning og styreoppdrag · Oslo + Akershus + Østfold + Buskerud + Innlandet ·
        B2B-tjenester og tech
      </div>
    </div>
    <button class="lh-btn lh-btn-primary" id="prospects-import-btn">🔄 Importer nye fra Brreg</button>
  </div>

  <!-- Stats -->
  <div class="lh-stats" style="margin-bottom:20px">
    <div class="lh-stat">
      <div class="lh-stat-label">Totalt</div>
      <div class="lh-stat-value"><?= number_format_i18n($counts['total']) ?></div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Nye (klar til vurdering)</div>
      <div class="lh-stat-value yellow"><?= number_format_i18n($counts['new']) ?></div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Score ≥ 50 (hot)</div>
      <div class="lh-stat-value green"><?= number_format_i18n($counts['hot']) ?></div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Lagt til i CRM</div>
      <div class="lh-stat-value"><?= number_format_i18n($counts['added_to_crm']) ?></div>
    </div>
  </div>

  <div class="lh-card">
    <div class="lh-card-head">
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <h2>Aktive prospekter</h2>
        <div class="lh-filter-tabs">
          <button class="lh-ftab active" data-prospect-filter="all">Alle</button>
          <button class="lh-ftab" data-prospect-filter="hot">🔥 Hot (≥50)</button>
          <button class="lh-ftab" data-prospect-filter="warm">Varme (30–49)</button>
        </div>
      </div>
    </div>
    <div class="lh-card-body" style="padding:12px 20px 0">
      <div class="lh-search">
        <input type="text" id="prospects-search" placeholder="Søk navn, kommune, NACE …">
      </div>
    </div>

    <div class="lh-table-wrap">
      <?php if ($prospects): ?>
      <table class="lh-table" id="prospects-table">
        <thead>
          <tr>
            <th>Navn</th>
            <th>Kommune</th>
            <th>Bransje</th>
            <th>Ansatte</th>
            <th>Omsetning</th>
            <th>Score</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($prospects as $p):
          $score = (int) $p['advisory_score'];
          $score_cls = $score >= 50 ? 'green' : ($score >= 30 ? 'yellow' : 'gray');
          $rev = (float) $p['revenue_latest'];
          $rev_str = $rev > 0 ? number_format($rev / 1_000_000, 1, ',', ' ') . ' MNOK' : '—';
          $rev_year = $p['revenue_year'] ? ' (' . $p['revenue_year'] . ')' : '';
        ?>
          <tr class="lh-clickable-row lh-view-prospect-btn"
              data-record="<?= esc_attr(json_encode($p)) ?>"
              data-score="<?= $score ?>">
            <td>
              <strong>🏢 <?= esc_html($p['name']) ?></strong>
              <?php if (!empty($p['org_nr'])): ?>
                <div style="font-size:11px;color:var(--lh-muted);margin-top:2px"><?= esc_html($p['org_nr']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= esc_html($p['kommune_navn'] ?: '—') ?></td>
            <td>
              <?= esc_html($p['nace_code'] ?: '') ?>
              <?php if (!empty($p['nace_description'])): ?>
                <div style="font-size:11px;color:var(--lh-muted)"><?= esc_html($p['nace_description']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= $p['employees'] !== null ? esc_html($p['employees']) : '—' ?></td>
            <td>
              <?= esc_html($rev_str) ?>
              <?php if ($rev_year): ?>
                <span style="font-size:11px;color:var(--lh-muted)"><?= esc_html($rev_year) ?></span>
              <?php endif; ?>
            </td>
            <td><span class="lh-badge lh-badge-<?= $score_cls ?>"><?= $score ?></span></td>
            <td class="actions">
              <button class="lh-btn lh-btn-primary lh-btn-sm js-add-to-crm"
                data-id="<?= $p['id'] ?>" title="Legg til i CRM">+ CRM</button>
              <button class="lh-btn lh-btn-secondary lh-btn-sm js-skip-prospect"
                data-id="<?= $p['id'] ?>" title="Hopp over">×</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="lh-empty">
          <p>Ingen prospekter ennå. Klikk "🔄 Importer nye fra Brreg" for å starte.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     VIEW MODAL — prospekt-detaljer
════════════════════════════════════════════════════════════════════════ -->
<div class="lh-modal-overlay" id="modal-prospect-view">
  <div class="lh-modal lh-modal-wide">
    <div class="lh-modal-head">
      <div>
        <span class="lh-badge lh-badge-gray" style="margin-bottom:6px;display:inline-block">🏢 Prospekt</span>
        <h3 id="view-prospect-name" style="margin:0;font-size:18px"></h3>
      </div>
      <button class="lh-modal-close">×</button>
    </div>
    <div class="lh-modal-body">
      <div id="view-prospect-fields" class="lh-view-grid"></div>
    </div>
    <div class="lh-modal-foot">
      <button class="lh-btn lh-btn-secondary lh-modal-close">Lukk</button>
      <button class="lh-btn lh-btn-secondary" id="view-prospect-skip">Hopp over</button>
      <button class="lh-btn lh-btn-primary" id="view-prospect-add">+ Legg til i CRM</button>
    </div>
  </div>
</div>

<!-- Import-progress modal -->
<div class="lh-modal-overlay" id="modal-prospect-import">
  <div class="lh-modal">
    <div class="lh-modal-head">
      <h3>Importerer fra Brreg</h3>
    </div>
    <div class="lh-modal-body">
      <div id="prospect-import-progress" style="text-align:center;padding:20px">
        <div style="font-size:32px;margin-bottom:12px">⏳</div>
        <div style="color:var(--lh-muted);font-size:14px">
          Henter kandidater fra Brreg, scraper hjemmesider og henter regnskapstall …<br>
          Kan ta 1–3 minutter for én batch.
        </div>
      </div>
      <div id="prospect-import-result" style="display:none"></div>
    </div>
    <div class="lh-modal-foot">
      <button class="lh-btn lh-btn-secondary lh-modal-close" id="prospect-import-close" disabled>Lukk</button>
    </div>
  </div>
</div>

<script>
(function () {
  const $ = jQuery;

  // ── Filter tabs ─────────────────────────────────────────────────────────
  document.querySelectorAll('[data-prospect-filter]').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('[data-prospect-filter]').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      applyProspectFilter();
    });
  });
  document.getElementById('prospects-search')?.addEventListener('input', applyProspectFilter);

  function applyProspectFilter() {
    const f = document.querySelector('[data-prospect-filter].active')?.dataset.prospectFilter || 'all';
    const q = (document.getElementById('prospects-search')?.value || '').toLowerCase();
    document.querySelectorAll('#prospects-table tbody tr').forEach(row => {
      const score = parseInt(row.dataset.score, 10) || 0;
      let scoreOk = true;
      if (f === 'hot') scoreOk = score >= 50;
      else if (f === 'warm') scoreOk = score >= 30 && score < 50;
      const textOk = !q || row.textContent.toLowerCase().includes(q);
      row.style.display = scoreOk && textOk ? '' : 'none';
    });
  }

  // ── View modal ──────────────────────────────────────────────────────────
  $(document).on('click', '.lh-view-prospect-btn', function () {
    const d = $(this).data('record');
    $('#view-prospect-name').text(d.name);

    const escHtml = s => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const fmt = (label, value) => value ? `<div class="lh-view-field"><div class="lh-view-label">${label}</div><div class="lh-view-value">${value}</div></div>` : '';
    const rev = parseFloat(d.revenue_latest || 0);
    const revStr = rev > 0
      ? new Intl.NumberFormat('nb-NO', { maximumFractionDigits: 0 }).format(rev) + ' kr' + (d.revenue_year ? ` (${d.revenue_year})` : '')
      : '';
    const wpStr = d.has_wordpress === 1 || d.has_wordpress === '1'
      ? 'Ja' + (d.wp_version ? ` (v${escHtml(d.wp_version)})` : '')
      : (d.has_wordpress === 0 || d.has_wordpress === '0' ? 'Nei' : 'Ikke skannet');

    let html = '';
    html += fmt('Org.nr', escHtml(d.org_nr));
    html += fmt('Bransje', `${escHtml(d.nace_code || '')} ${escHtml(d.nace_description || '')}`);
    html += fmt('Ansatte', escHtml(d.employees));
    html += fmt('Kommune', escHtml(d.kommune_navn));
    html += fmt('Etablert', d.registration_date ? escHtml(d.registration_date) : '');
    html += fmt('Omsetning', escHtml(revStr));
    html += fmt('Advisory-score', `<span class="lh-badge lh-badge-${parseInt(d.advisory_score)>=50?'green':parseInt(d.advisory_score)>=30?'yellow':'gray'}">${d.advisory_score}</span>`);
    html += fmt('Hjemmeside', d.website ? `<a href="${escHtml(d.website)}" target="_blank" rel="noopener">${escHtml(d.website)}</a>` : '');
    html += fmt('E-post', d.email ? `<a href="mailto:${escHtml(d.email)}">${escHtml(d.email)}</a>` : '');
    html += fmt('Telefon', d.phone ? `<a href="tel:${escHtml(d.phone)}">${escHtml(d.phone)}</a>` : '');
    html += fmt('Adresse', escHtml(d.address));
    html += fmt('Postadresse', escHtml(d.postal_address));
    html += fmt('WordPress', escHtml(wpStr));
    $('#view-prospect-fields').html(html);

    $('#view-prospect-add').off('click').on('click', () => addToCRM(d.id));
    $('#view-prospect-skip').off('click').on('click', () => skipProspect(d.id));

    lhOpenModal('modal-prospect-view');
  });

  // ── Action: Legg til i CRM ──────────────────────────────────────────────
  function addToCRM(id) {
    if (!confirm('Legg til som lead-kontakt i CRM?')) return;
    $.post(Edifice.ajax_url, {
      action: 'edifice_prospect_add_to_crm',
      nonce:  Edifice.nonce,
      id:     id,
    }, function (r) {
      if (r.success) location.reload();
      else alert('Feilet: ' + (r.data || 'ukjent feil'));
    });
  }
  $(document).on('click', '.js-add-to-crm', function (e) {
    e.stopPropagation();
    addToCRM($(this).data('id'));
  });

  // ── Action: Hopp over ───────────────────────────────────────────────────
  function skipProspect(id) {
    const reason = prompt('Hvorfor hopper du over? (valgfritt)') || '';
    if (reason === null) return;
    $.post(Edifice.ajax_url, {
      action: 'edifice_prospect_skip',
      nonce:  Edifice.nonce,
      id:     id,
      reason: reason,
    }, function (r) {
      if (r.success) location.reload();
      else alert('Feilet');
    });
  }
  $(document).on('click', '.js-skip-prospect', function (e) {
    e.stopPropagation();
    skipProspect($(this).data('id'));
  });

  // ── Action: Import ──────────────────────────────────────────────────────
  document.getElementById('prospects-import-btn')?.addEventListener('click', function () {
    document.getElementById('prospect-import-progress').style.display = '';
    document.getElementById('prospect-import-result').style.display = 'none';
    document.getElementById('prospect-import-close').disabled = true;
    lhOpenModal('modal-prospect-import');

    $.post(Edifice.ajax_url, {
      action: 'edifice_prospect_import',
      nonce:  Edifice.nonce,
    }, function (r) {
      document.getElementById('prospect-import-progress').style.display = 'none';
      const result = document.getElementById('prospect-import-result');
      result.style.display = '';
      document.getElementById('prospect-import-close').disabled = false;
      if (!r.success) {
        result.innerHTML = `<div style="color:#dc2626;padding:16px">Feilet: ${r.data || 'ukjent'}</div>`;
        return;
      }
      const s = r.data || {};
      result.innerHTML = `
        <div style="padding:20px;text-align:center">
          <div style="font-size:36px;margin-bottom:8px">✅</div>
          <div style="font-weight:600;font-size:15px;margin-bottom:14px">Import ferdig</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;text-align:left;max-width:300px;margin:0 auto">
            <div>Hentet fra Brreg:</div><div><strong>${s.fetched || 0}</strong></div>
            <div>Nye prospekter lagret:</div><div><strong style="color:#16a34a">${s.imported || 0}</strong></div>
            <div>Eksisterte fra før:</div><div>${s.skipped_existing || 0}</div>
            <div>Utenfor geografi-filter:</div><div>${s.skipped_geo || 0}</div>
            <div>Feil:</div><div>${s.errors || 0}</div>
          </div>
        </div>`;
      // Auto-reload etter 3 sek så bruker ser det nye
      setTimeout(() => location.reload(), 3000);
    }).fail(function () {
      document.getElementById('prospect-import-progress').style.display = 'none';
      document.getElementById('prospect-import-close').disabled = false;
      document.getElementById('prospect-import-result').style.display = '';
      document.getElementById('prospect-import-result').innerHTML =
        '<div style="color:#dc2626;padding:16px">Nettverksfeil under import</div>';
    });
  });

  // Action-celler stopper propagation til row-click
  document.querySelectorAll('#prospects-table .actions').forEach(td => {
    td.addEventListener('click', e => e.stopPropagation());
  });
})();
</script>
