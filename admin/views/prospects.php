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
    <div style="display:flex;gap:8px;align-items:center">
      <button class="lh-btn lh-btn-secondary lh-btn-sm" id="prospects-truncate-btn"
              title="Slett alle prospekter unntatt de som er lagt til i CRM"
              style="opacity:.65">🗑 Tøm</button>
      <button class="lh-btn lh-btn-primary" id="prospects-import-btn">🔄 Importer nye fra Brreg</button>
    </div>
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
      <table class="lh-table lh-sortable" id="prospects-table">
        <thead>
          <tr>
            <th data-sort-key="name"     data-sort-type="text"   data-sort-default="asc">Navn</th>
            <th data-sort-key="kommune"  data-sort-type="text"   data-sort-default="asc">Kommune</th>
            <th data-sort-key="nace"     data-sort-type="text"   data-sort-default="asc">Bransje</th>
            <th data-sort-key="emp"      data-sort-type="number" data-sort-default="desc">Ansatte</th>
            <th data-sort-key="revenue"  data-sort-type="number" data-sort-default="desc">Omsetning</th>
            <th data-sort-key="score"    data-sort-type="number" data-sort-default="desc" class="lh-sort-active lh-sort-desc">Score</th>
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
              data-score="<?= $score ?>"
              data-sort-name="<?= esc_attr(strtolower($p['name'])) ?>"
              data-sort-kommune="<?= esc_attr(strtolower($p['kommune_navn'] ?? '')) ?>"
              data-sort-nace="<?= esc_attr($p['nace_code'] ?? '') ?>"
              data-sort-emp="<?= $p['employees'] !== null ? (int) $p['employees'] : '' ?>"
              data-sort-revenue="<?= $rev > 0 ? $rev : '' ?>"
              data-sort-score="<?= $score ?>">
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
            <td><?= $p['employees'] !== null
                  ? esc_html($p['employees'])
                  : '<span style="color:var(--lh-muted)" title="Ikke registrert i Brreg/NAV">?</span>' ?></td>
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

<style>
/* Sorterbare kolonneoverskrifter */
#prospects-table.lh-sortable thead th[data-sort-key] {
  position: relative;
  padding-right: 18px;
  transition: color .15s;
}
#prospects-table.lh-sortable thead th[data-sort-key]:hover {
  color: #1e3a5f;
}
#prospects-table.lh-sortable thead th[data-sort-key]::after {
  content: '↕';
  position: absolute;
  right: 6px;
  top: 50%;
  transform: translateY(-50%);
  opacity: .25;
  font-size: 11px;
}
#prospects-table.lh-sortable thead th.lh-sort-active::after {
  opacity: .9;
  color: #1e3a5f;
}
#prospects-table.lh-sortable thead th.lh-sort-asc::after  { content: '▲' }
#prospects-table.lh-sortable thead th.lh-sort-desc::after { content: '▼' }
</style>
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

  // ── Score-breakdown — speiler PHP Edifice_Prospects::compute_scores() ───
  // Returnerer array av {label, value, points, note} pluss total og max.
  function scoreBreakdown(d) {
    const lines = [];
    let total = 0;
    const max = 30 + 35 + 8 + 10; // = 83

    // Ansatte (maks 30)
    const empKnown = d.employees !== null && d.employees !== '';
    const emp = empKnown ? parseInt(d.employees, 10) : null;
    if (!empKnown) {
      lines.push({ label: 'Ansatte', value: 'Ukjent', points: 10, note: 'nøytral score for manglende NAV-data' });
      total += 10;
    } else if (emp >= 5 && emp <= 15) {
      lines.push({ label: 'Ansatte', value: emp + ' ansatte', points: 30, note: 'sweet spot 5–15' });
      total += 30;
    } else if (emp >= 16 && emp <= 25) {
      lines.push({ label: 'Ansatte', value: emp + ' ansatte', points: 25, note: 'veldig godt' });
      total += 25;
    } else if (emp >= 2 && emp <= 4) {
      lines.push({ label: 'Ansatte', value: emp + ' ansatte', points: 15, note: 'små men relevante' });
      total += 15;
    } else {
      lines.push({ label: 'Ansatte', value: emp + ' ansatte', points: 0, note: 'utenfor sweet spot' });
    }

    // Omsetning (maks 35)
    const rev = parseFloat(d.revenue_latest || 0);
    const revFmt = rev > 0
      ? new Intl.NumberFormat('nb-NO', { maximumFractionDigits: 1 }).format(rev / 1000000) + ' MNOK'
      : 'Mangler';
    if (rev <= 0) {
      lines.push({ label: 'Omsetning', value: 'Mangler regnskap', points: 0, note: '— typisk ny eller forsinket levering' });
    } else if (rev >= 3000000 && rev <= 20000000) {
      lines.push({ label: 'Omsetning', value: revFmt, points: 35, note: 'sweet spot 3–20 MNOK' });
      total += 35;
    } else if (rev >= 20000000 && rev <= 50000000) {
      lines.push({ label: 'Omsetning', value: revFmt, points: 20, note: 'over sweet, fortsatt relevant' });
      total += 20;
    } else if (rev >= 1000000 && rev < 3000000) {
      lines.push({ label: 'Omsetning', value: revFmt, points: 15, note: 'voksefase, under sweet' });
      total += 15;
    } else if (rev > 50000000) {
      lines.push({ label: 'Omsetning', value: revFmt, points: 5, note: 'for stor — sjeldne 25-emp-bedrifter' });
      total += 5;
    } else {
      lines.push({ label: 'Omsetning', value: revFmt, points: 5, note: 'tidlig fase, lavt prioritet' });
      total += 5;
    }

    // Kontaktinfo (maks 8)
    if (d.email) { lines.push({ label: 'E-post registrert', value: '✓', points: 5, note: '' }); total += 5; }
    else lines.push({ label: 'E-post registrert', value: '—', points: 0, note: '' });
    if (d.phone) { lines.push({ label: 'Telefon registrert', value: '✓', points: 3, note: '' }); total += 3; }
    else lines.push({ label: 'Telefon registrert', value: '—', points: 0, note: '' });

    // Modenhet (maks 10)
    if (d.registration_date) {
      const ageYears = (Date.now() - new Date(d.registration_date).getTime()) / (365 * 86400000);
      const ageStr = ageYears.toFixed(1) + ' år';
      if (ageYears >= 2 && ageYears <= 5) {
        lines.push({ label: 'Modenhet', value: ageStr, points: 10, note: 'sweet spot 2–5 år' });
        total += 10;
      } else if (ageYears > 5 && ageYears <= 15) {
        lines.push({ label: 'Modenhet', value: ageStr, points: 7, note: 'etablert' });
        total += 7;
      } else if (ageYears >= 0 && ageYears < 2) {
        lines.push({ label: 'Modenhet', value: ageStr, points: 5, note: 'helt fersk' });
        total += 5;
      } else if (ageYears > 15 && ageYears <= 25) {
        lines.push({ label: 'Modenhet', value: ageStr, points: 3, note: 'mellomalder' });
        total += 3;
      } else if (ageYears > 25) {
        lines.push({ label: 'Modenhet', value: ageStr, points: 1, note: 'gammel/rigid' });
        total += 1;
      }
    } else {
      lines.push({ label: 'Modenhet', value: 'Ukjent', points: 0, note: '' });
    }

    return { lines, total, max };
  }

  // ── View modal ──────────────────────────────────────────────────────────
  $(document).on('click', '.lh-view-prospect-btn', function () {
    const d = $(this).data('record');
    $('#view-prospect-name').text(d.name);

    const escHtml = s => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const fmt = (label, value) => `<div class="lh-view-field"><div class="lh-view-label">${label}</div><div class="lh-view-value">${value}</div></div>`;
    const muted = txt => `<span style="color:var(--lh-muted)">${escHtml(txt)}</span>`;
    const rev = parseFloat(d.revenue_latest || 0);
    const revStr = rev > 0
      ? new Intl.NumberFormat('nb-NO', { maximumFractionDigits: 0 }).format(rev) + ' kr' + (d.revenue_year ? ` (${d.revenue_year})` : '')
      : '';
    const wpStr = d.has_wordpress === 1 || d.has_wordpress === '1'
      ? 'Ja' + (d.wp_version ? ` (v${escHtml(d.wp_version)})` : '')
      : (d.has_wordpress === 0 || d.has_wordpress === '0' ? 'Nei' : 'Ikke skannet');
    const websiteHref = d.website
      ? (/^https?:\/\//i.test(d.website) ? d.website : 'https://' + d.website.replace(/^\/+/, ''))
      : '';
    const score = parseInt(d.advisory_score) || 0;
    const scoreCls = score >= 50 ? 'green' : score >= 30 ? 'yellow' : 'gray';

    let html = '';

    // Selskapsinfo
    html += '<div class="lh-view-section-title">📋 Selskapsinfo</div>';
    html += fmt('Org.nr', escHtml(d.org_nr));
    html += fmt('Bransje', escHtml((d.nace_code || '') + ' ' + (d.nace_description || '')));
    html += fmt('Ansatte', d.employees !== null && d.employees !== ''
      ? escHtml(d.employees)
      : muted('Ikke registrert i Brreg/NAV'));
    html += fmt('Kommune', d.kommune_navn ? escHtml(d.kommune_navn) : muted('—'));
    html += fmt('Etablert', d.registration_date ? escHtml(d.registration_date) : muted('—'));
    html += fmt('Omsetning', revStr ? escHtml(revStr) : muted('Ikke i regnskapsregisteret'));

    // Kontaktinfo (alltid synlig — også med tomme felt)
    html += '<div class="lh-view-section-title" style="margin-top:14px">📞 Kontaktinfo</div>';
    html += fmt('E-post', d.email
      ? `<a href="mailto:${escHtml(d.email)}">${escHtml(d.email)}</a>`
      : muted('Ikke registrert'));
    html += fmt('Telefon', d.phone
      ? `<a href="tel:${escHtml(d.phone)}">${escHtml(d.phone)}</a>`
      : muted('Ikke registrert'));
    html += fmt('Hjemmeside', websiteHref
      ? `<a href="${escHtml(websiteHref)}" target="_blank" rel="noopener">${escHtml(d.website)}</a>`
      : muted('Ikke registrert'));

    // Adresser
    if (d.address || d.postal_address) {
      html += '<div class="lh-view-section-title" style="margin-top:14px">📍 Adresser</div>';
      if (d.address)        html += fmt('Besøksadresse', escHtml(d.address));
      if (d.postal_address) html += fmt('Postadresse',   escHtml(d.postal_address));
    }

    // Annet
    html += '<div class="lh-view-section-title" style="margin-top:14px">🌐 Annet</div>';
    html += fmt('WordPress', escHtml(wpStr));

    // Score-utregning
    const bd = scoreBreakdown(d);
    html += '<div class="lh-view-section-title" style="margin-top:14px">📊 Score-utregning</div>';
    html += '<div style="background:#f8fafc;border:1px solid var(--lh-border);border-radius:8px;padding:12px;margin-top:6px;grid-column:1/-1">';
    html += '<table style="width:100%;font-size:13px;border-collapse:collapse">';
    bd.lines.forEach(line => {
      const dim = (line.points > 0 ? '#1e293b' : '#94a3b8');
      const pts = line.points > 0
        ? `<strong style="color:#16a34a">+${line.points}</strong>`
        : '<span style="color:#94a3b8">0</span>';
      html += `<tr style="border-bottom:1px solid #e2e8f0">
        <td style="padding:6px 4px;color:${dim};width:35%">${escHtml(line.label)}</td>
        <td style="padding:6px 4px;color:${dim}">${escHtml(line.value)}</td>
        <td style="padding:6px 4px;text-align:right;width:60px">${pts}</td>
        <td style="padding:6px 4px;color:#94a3b8;font-size:11px;font-style:italic">${escHtml(line.note)}</td>
      </tr>`;
    });
    html += `<tr>
      <td colspan="2" style="padding:8px 4px;font-weight:600">Sum advisory-score</td>
      <td style="padding:8px 4px;text-align:right;font-weight:700;font-size:15px">
        <span class="lh-badge lh-badge-${scoreCls}">${bd.total}</span>
      </td>
      <td style="padding:8px 4px;color:#94a3b8;font-size:11px">av maks ${bd.max}</td>
    </tr>`;
    html += '</table>';
    if (bd.total !== score) {
      html += `<div style="margin-top:8px;font-size:11px;color:#dc2626">
        ⚠️ Lagret score (${score}) avviker fra beregnet (${bd.total}) — kjør re-skann for å oppdatere.
      </div>`;
    }
    html += '</div>';

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

  // ── Sortering på kolonneoverskrifter ────────────────────────────────────
  document.querySelectorAll('#prospects-table thead th[data-sort-key]').forEach(th => {
    th.style.cursor = 'pointer';
    th.style.userSelect = 'none';
    th.addEventListener('click', function () {
      const key  = this.dataset.sortKey;
      const type = this.dataset.sortType || 'text';
      const def  = this.dataset.sortDefault || 'asc';
      // Toggle: hvis allerede aktiv, snu retning. Hvis ikke, bruk default.
      const wasActive = this.classList.contains('lh-sort-active');
      const wasDesc   = this.classList.contains('lh-sort-desc');
      const newDesc   = wasActive ? !wasDesc : (def === 'desc');

      // Rens alle headers
      document.querySelectorAll('#prospects-table thead th').forEach(h => {
        h.classList.remove('lh-sort-active', 'lh-sort-asc', 'lh-sort-desc');
      });
      this.classList.add('lh-sort-active', newDesc ? 'lh-sort-desc' : 'lh-sort-asc');

      // Hent rader, sort, sett tilbake
      const tbody = document.querySelector('#prospects-table tbody');
      const rows  = Array.from(tbody.querySelectorAll('tr'));
      rows.sort((a, b) => {
        const va = a.dataset['sort' + key.charAt(0).toUpperCase() + key.slice(1)] || '';
        const vb = b.dataset['sort' + key.charAt(0).toUpperCase() + key.slice(1)] || '';
        // Tomme verdier alltid nederst, uavhengig av retning
        if (va === '' && vb === '') return 0;
        if (va === '') return 1;
        if (vb === '') return -1;
        let cmp;
        if (type === 'number') cmp = parseFloat(va) - parseFloat(vb);
        else cmp = va.localeCompare(vb, 'nb');
        return newDesc ? -cmp : cmp;
      });
      rows.forEach(r => tbody.appendChild(r));
    });
  });

  // ── Tøm prospekt-data ───────────────────────────────────────────────────
  document.getElementById('prospects-truncate-btn')?.addEventListener('click', function () {
    const msg = 'Slett ALLE prospekter unntatt de som er lagt til i CRM?\n\n' +
                'Bruk dette ved scoring-modell-justering for å kjøre frisk import.\n' +
                'Konverterte CRM-kontakter beholdes.';
    if (!confirm(msg)) return;
    if (!confirm('Helt sikker? Slett-handlingen kan ikke angres.')) return;
    $.post(Edifice.ajax_url, {
      action: 'edifice_prospect_truncate',
      nonce:  Edifice.nonce,
    }, function (r) {
      if (r.success) {
        alert(`Slettet ${r.data.deleted} prospekter. Last siden på nytt.`);
        location.reload();
      } else {
        alert('Feilet: ' + (r.data || 'ukjent'));
      }
    });
  });
})();
</script>
