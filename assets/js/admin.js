/* Edifice — Admin JS v1.1 */
(function ($) {
  'use strict';

  /* ── Toast ─────────────────────────────────────────────────────────────── */
  const toast = (msg, type = 'success') => {
    let el = document.getElementById('lh-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'lh-toast';
      el.className = 'lh-toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.className = 'lh-toast ' + type + ' show';
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 3000);
  };

  /* ── Modal helpers ─────────────────────────────────────────────────────── */
  window.lhOpenModal = (id) => {
    const m = document.getElementById(id);
    if (m) {
      m.classList.add('open');
      m.dispatchEvent(new Event('lh:opened'));
    }
  };
  window.lhCloseModal = (id) => {
    const m = document.getElementById(id);
    if (m) {
      m.classList.remove('open');
      m.querySelectorAll('form').forEach(f => f.reset());
    }
  };

  // Close on Escape key
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') {
      $('.lh-modal-overlay.open').each(function () {
        lhCloseModal(this.id);
      });
    }
  });

  // Close on backdrop click
  $(document).on('click', '.lh-modal-overlay', function (e) {
    if (e.target === this) lhCloseModal(this.id);
  });
  $(document).on('click', '.lh-modal-close', function () {
    lhCloseModal($(this).closest('.lh-modal-overlay').attr('id'));
  });

  /* ── AJAX helper ────────────────────────────────────────────────────────── */
  window.lhAjax = (action, data, cb) => {
    $.post(Edifice.ajax_url, { action, nonce: Edifice.nonce, ...data }, function (r) {
      if (r.success) {
        toast(r.data?.msg || 'Lagret ✓', 'success');
        cb && cb(r.data);
      } else {
        toast(r.data || 'Noe gikk galt', 'error');
      }
    });
  };

  /* ── Delete confirm ─────────────────────────────────────────────────────── */
  $(document).on('click', '.lh-delete-btn', function () {
    const action = $(this).data('action');
    const id     = $(this).data('id');
    if (!confirm('Slette denne oppføringen?')) return;
    lhAjax(action, { id }, () => location.reload());
  });

  /* ── Generic form submit ─────────────────────────────────────────────────── */
  $(document).on('submit', '.lh-ajax-form', function (e) {
    e.preventDefault();
    const $btn = $(this).find('[type=submit]');
    $btn.prop('disabled', true);
    const data = {};
    $(this).serializeArray().forEach(f => data[f.name] = f.value);
    $(this).find('input[type=checkbox]').each(function () {
      data[this.name] = this.checked ? 1 : 0;
    });
    lhAjax(data.ajax_action, data, () => {
      $btn.prop('disabled', false);
      location.reload();
    });
    $btn.prop('disabled', false);
  });

  /* ── Brreg live search ───────────────────────────────────────────────────── */
  let brregTimer;
  $(document).on('input', '.lh-brreg-search', function () {
    clearTimeout(brregTimer);
    const q   = $(this).val().trim();
    const $res = $(this).siblings('.lh-brreg-results');
    if (q.length < 2) { $res.hide(); return; }
    brregTimer = setTimeout(() => {
      $.post(Edifice.ajax_url, {
        action: 'edifice_brreg_lookup',
        nonce:  Edifice.nonce,
        query:  q,
      }, function (r) {
        if (!r.success) return;
        const items = Array.isArray(r.data) ? r.data : [r.data];
        $res.html(items.slice(0, 8).map(e =>
          `<div class="lh-brreg-item" data-org='${JSON.stringify(e)}'>
             <strong>${e.navn || ''}</strong>
             <span>${e.organisasjonsnummer || ''} · ${e.organisasjonsform?.beskrivelse || ''}</span>
           </div>`
        ).join('')).show();
      });
    }, 300);
  });

  $(document).on('click', '.lh-brreg-item', function () {
    const e      = $(this).data('org');
    const $modal = $(this).closest('.lh-modal-body');
    $modal.find('[name=name]').val(e.navn || '');
    $modal.find('[name=org_nr]').val(e.organisasjonsnummer || '');
    const adr    = e.forretningsadresse || e.postadresse || {};
    const addrStr = [(adr.adresse || []).join(' '), (adr.postnummer || '') + ' ' + (adr.poststed || '')].filter(Boolean).join(', ');
    $modal.find('[name=address]').val(addrStr);
    $modal.find('[name=brreg_data]').val(JSON.stringify(e));
    $(this).closest('.lh-brreg-results').hide();
  });

  /* ── Edit pre-fill ───────────────────────────────────────────────────────── */
  $(document).on('click', '.lh-edit-btn', function () {
    const data   = $(this).data('record');
    const target = $(this).data('modal');
    const $modal = $('#' + target);
    $modal.find('form')[0]?.reset();
    Object.entries(data).forEach(([k, v]) => {
      const $el = $modal.find(`[name="${k}"]`);
      if ($el.is('select'))                $el.val(v);
      else if ($el.is('input[type=checkbox]')) $el.prop('checked', !!v);
      else                                 $el.val(v);
    });
    // CRM type toggle
    const typeSelect = $modal.find('#crm-type-select');
    if (typeSelect.length) {
      const isPerson = typeSelect.val() === 'person';
      $modal.find('#crm-company-row').toggle(isPerson);
      $modal.find('#crm-orgnr-row').toggle(!isPerson);
    }
    lhOpenModal(target);
  });

  /* ── Number formatting ───────────────────────────────────────────────────── */
  window.lhFmtNOK = (v) => new Intl.NumberFormat('nb-NO', {
    style: 'currency', currency: 'NOK', maximumFractionDigits: 0,
  }).format(v || 0);

  /* ════════════════════════════════════════════════════════════════════════
     VIEW MODAL HELPERS
  ════════════════════════════════════════════════════════════════════════ */

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function viewField(label, value) {
    if (value === null || value === undefined || value === '') return '';
    return `<div class="lh-view-field">
      <div class="lh-view-label">${label}</div>
      <div class="lh-view-value">${value}</div>
    </div>`;
  }

  function fmtDate(d) {
    if (!d) return '';
    try { return new Date(d).toLocaleDateString('nb-NO', { day: '2-digit', month: '2-digit', year: 'numeric' }); }
    catch (e) { return d; }
  }

  function fmtEmailDate(dateStr) {
    if (!dateStr) return '';
    try {
      const d    = new Date(dateStr);
      const diff = (Date.now() - d) / 86400000;
      if (diff < 1) return d.toLocaleTimeString('nb-NO', { hour: '2-digit', minute: '2-digit' });
      if (diff < 7) return d.toLocaleDateString('nb-NO', { weekday: 'short', day: 'numeric', month: 'short' });
      return d.toLocaleDateString('nb-NO', { day: '2-digit', month: '2-digit', year: '2-digit' });
    } catch (e) { return dateStr; }
  }

  /* ── CRM VIEW MODAL ──────────────────────────────────────────────────────── */

  const crmStatusBadge = {
    active:   ['Aktiv',   'lh-badge-green'],
    lead:     ['Lead',    'lh-badge-blue'],
    inactive: ['Inaktiv', 'lh-badge-gray'],
  };

  $(document).on('click', '.lh-view-crm-btn', function () {
    const d         = $(this).data('record');
    const isCompany = d.type === 'company';

    // Header type badge
    $('#view-crm-type-badge')
      .text(isCompany ? '🏢 Selskap' : '👤 Person')
      .attr('class', 'lh-badge lh-badge-gray');
    $('#view-crm-name').text(d.name);

    // Core fields
    let fields = '';
    if (d.company_name) fields += viewField('Tilknyttet selskap', escHtml(d.company_name));
    if (d.org_nr)       fields += viewField('Org.nr', escHtml(d.org_nr));
    if (d.category)     fields += viewField('Kategori', escHtml(d.category));
    const [sLabel, sCls] = crmStatusBadge[d.status] || ['?', 'lh-badge-gray'];
    fields += viewField('Status', `<span class="lh-badge ${sCls}">${sLabel}</span>`);
    if (d.email) fields += viewField('E-post',   `<a href="mailto:${escHtml(d.email)}">${escHtml(d.email)}</a>`);
    if (d.phone) fields += viewField('Telefon',  `<a href="tel:${escHtml(d.phone)}">${escHtml(d.phone)}</a>`);
    if (d.address)     fields += viewField('Adresse',   escHtml(d.address));
    if (d.created_at)  fields += viewField('Opprettet', fmtDate(d.created_at));
    $('#view-crm-fields').html(fields);

    // Notes
    if (d.notes) {
      $('#view-crm-notes').text(d.notes);
      $('#view-crm-notes-wrap').show();
    } else {
      $('#view-crm-notes-wrap').hide();
    }

    // Company → show persons list
    if (isCompany) {
      $('#view-crm-persons-section').show();
      $('#view-crm-gmail-section').hide();
      $('#view-crm-persons-list').html('<span style="color:var(--lh-muted);font-size:13px">Laster…</span>');
      $.post(Edifice.ajax_url, {
        action:     'edifice_crm_get_persons',
        nonce:      Edifice.nonce,
        company_id: d.id,
      }, function (r) {
        if (!r.success || !r.data || !r.data.length) {
          $('#view-crm-persons-list').html(
            '<div style="color:var(--lh-muted);font-size:13px;padding:8px 0">Ingen kontaktpersoner registrert.</div>'
          );
          return;
        }
        const rows = r.data.map(p =>
          `<div class="lh-person-row">
            <span style="font-size:16px">👤</span>
            <span class="name">${escHtml(p.name)}</span>
            ${p.email ? `<span class="meta"><a href="mailto:${escHtml(p.email)}" style="color:var(--lh-accent)">${escHtml(p.email)}</a></span>` : ''}
            ${p.phone ? `<span class="meta">${escHtml(p.phone)}</span>` : ''}
          </div>`
        ).join('');
        $('#view-crm-persons-list').html(rows);
      });
    } else {
      // Person → show Gmail emails
      $('#view-crm-persons-section').hide();
      if (d.email) {
        $('#view-crm-gmail-section').show();
        loadGmailEmails(d.email);
      } else {
        $('#view-crm-gmail-section').hide();
      }
    }

    // "Rediger" button wires back to edit modal
    $('#view-crm-edit-btn').off('click').on('click', function () {
      lhCloseModal('modal-crm-view');
      const $m = $('#modal-crm');
      $m.find('form')[0]?.reset();
      Object.entries(d).forEach(([k, v]) => {
        const $el = $m.find(`[name="${k}"]`);
        if ($el.is('select'))                $el.val(v);
        else if ($el.is('input[type=checkbox]')) $el.prop('checked', !!v);
        else                                 $el.val(v);
      });
      const isPerson = d.type === 'person';
      $m.find('#crm-company-row').toggle(isPerson);
      $m.find('#crm-orgnr-row').toggle(!isPerson);
      lhOpenModal('modal-crm');
    });

    lhOpenModal('modal-crm-view');
  });

  /* ── Gmail loader ────────────────────────────────────────────────────────── */
  function loadGmailEmails(email) {
    const $list   = $('#view-crm-gmail-list');
    const $status = $('#view-gmail-status');

    if (!Edifice.gmail_enabled) {
      $list.html(
        `<div class="lh-gmail-connect">
          ⚠️ Gmail er ikke koblet til.
          <a href="${Edifice.settings_url}" class="lh-btn lh-btn-secondary lh-btn-sm" style="margin-left:auto">
            Koble til →
          </a>
        </div>`
      );
      $status.text('Ikke koblet').attr('class', 'lh-badge lh-badge-yellow');
      return;
    }

    $list.html('<div class="lh-gmail-empty">Laster e-poster…</div>');
    $status.text('Laster…').attr('class', 'lh-badge lh-badge-gray');

    $.post(Edifice.ajax_url, {
      action: 'edifice_gmail_get_emails',
      nonce:  Edifice.nonce,
      email:  email,
    }, function (r) {
      if (!r.success) {
        // r.data may be a string (legacy) or object {message, detail, query}
        const errMsg = (r.data && r.data.message) ? r.data.message : (r.data || 'Feil ved henting av e-poster.');
        const errQry = (r.data && r.data.query) ? r.data.query : '';
        const detailJson = (r.data && r.data.detail)
          ? `<pre style="font-size:11px;background:#fef2f2;padding:8px;border-radius:6px;margin-top:6px;overflow:auto">${escHtml(JSON.stringify(r.data.detail, null, 2))}</pre>`
          : '';
        $list.html(`<div class="lh-gmail-empty" style="text-align:left">
          <strong>${escHtml(errMsg)}</strong>
          ${errQry ? `<div style="font-size:11px;color:var(--lh-muted);margin-top:4px">Søk: <code>${escHtml(errQry)}</code></div>` : ''}
          ${detailJson}
        </div>`);
        $status.text('Feil').attr('class', 'lh-badge lh-badge-red');
        return;
      }
      // r.data shape: { emails: [...], query, count, estimate }
      const emails = (r.data && r.data.emails) || [];
      const query  = (r.data && r.data.query)  || '';
      if (!emails.length) {
        $list.html(`<div class="lh-gmail-empty" style="text-align:left">
          Ingen e-poster funnet.
          <div style="font-size:11px;color:var(--lh-muted);margin-top:4px">Søk: <code>${escHtml(query)}</code></div>
        </div>`);
        $status.text('0 e-poster').attr('class', 'lh-badge lh-badge-gray');
        return;
      }
      const html = emails.map(m => {
        const isSent  = !!m.sent;
        const dirCls  = isSent ? 'sent' : 'received';
        const dirIcon = isSent ? '↗' : '↙';
        const contact = isSent
          ? (m.to   || '').replace(/<[^>]+>/g, '').trim()
          : (m.from || '').replace(/<[^>]+>/g, '').trim();
        const gmailUrl = `https://mail.google.com/mail/u/0/#inbox/${m.id}`;
        return `<a href="${gmailUrl}" target="_blank" rel="noopener" class="lh-gmail-email" style="text-decoration:none;color:inherit">
          <div class="lh-gmail-dir ${dirCls}">${dirIcon}</div>
          <div style="flex:1;min-width:0;overflow:hidden">
            <div class="lh-gmail-subject">${escHtml(m.subject)}</div>
            <div class="lh-gmail-meta">${escHtml(contact)} · ${fmtEmailDate(m.date)}</div>
          </div>
        </a>`;
      }).join('');
      $list.html(html);
      $status.text(emails.length + (emails.length === 1 ? ' e-post' : ' e-poster'))
             .attr('class', 'lh-badge lh-badge-green');
    });
  }

  /* ── PROJECT VIEW MODAL ──────────────────────────────────────────────────── */

  const projStatusMap = {
    'active':    ['Aktiv',      'lh-badge-green'],
    'on-hold':   ['På vent',    'lh-badge-yellow'],
    'completed': ['Fullført',   'lh-badge-blue'],
    'cancelled': ['Kansellert', 'lh-badge-gray'],
  };

  $(document).on('click', '.lh-view-project-btn', function () {
    const d = $(this).data('record');

    const [sLabel, sCls] = projStatusMap[d.status] || ['?', 'lh-badge-gray'];
    $('#view-proj-status-badge').text(sLabel).attr('class', `lh-badge ${sCls}`);
    $('#view-proj-name').text(d.name);

    let fields = '';
    if (d.contact_name) fields += viewField('Klient',    escHtml(d.contact_name));
    fields += viewField('Status', `<span class="lh-badge ${sCls}">${sLabel}</span>`);
    if (d.start_date) fields += viewField('Startdato', fmtDate(d.start_date));
    if (d.end_date)   fields += viewField('Frist',     fmtDate(d.end_date));
    if (d.budget)     fields += viewField('Budsjett',
      new Intl.NumberFormat('nb-NO').format(d.budget) + ' kr');
    if (d.created_at) fields += viewField('Opprettet', fmtDate(d.created_at));
    $('#view-proj-fields').html(fields);

    if (d.description) {
      $('#view-proj-desc').text(d.description);
      $('#view-proj-desc-wrap').show();
    } else {
      $('#view-proj-desc-wrap').hide();
    }

    $('#view-proj-edit-btn').off('click').on('click', function () {
      lhCloseModal('modal-project-view');
      const $m = $('#modal-project');
      $m.find('form')[0]?.reset();
      Object.entries(d).forEach(([k, v]) => {
        const $el = $m.find(`[name="${k}"]`);
        if ($el.is('select')) $el.val(v);
        else                  $el.val(v);
      });
      lhOpenModal('modal-project');
    });

    lhOpenModal('modal-project-view');
  });

})(jQuery);

/* ── Favicon override ────────────────────────────────────────────────────── */
/* Runs last (footer), so it replaces any favicon WordPress may have set      */
(function () {
  if (typeof Edifice === 'undefined' || !Edifice.plugin_url) return;
  var url = Edifice.plugin_url + 'assets/images/favicon.svg?v=' + Date.now();
  document.querySelectorAll('link[rel*="icon"]').forEach(function (el) {
    el.parentNode && el.parentNode.removeChild(el);
  });
  var link = document.createElement('link');
  link.rel  = 'icon';
  link.type = 'image/svg+xml';
  link.href = url;
  document.head.appendChild(link);
}());

