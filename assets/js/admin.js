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
  $(document).on('click', '.lh-delete-btn', function (e) {
    e.stopPropagation();
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

    // Brreg har to separate adresse-objekter — bruk begge:
    //   forretningsadresse → besøksadresse (address)
    //   postadresse        → postadresse (postal_address)
    // Hvis bare én er tilgjengelig, fyll besøksadresse med den.
    function brregAddrToString(adr) {
      if (!adr) return '';
      return [(adr.adresse || []).join(' '),
              (adr.postnummer || '') + ' ' + (adr.poststed || '')]
        .map(s => s.trim()).filter(Boolean).join(', ');
    }
    const visit  = brregAddrToString(e.forretningsadresse);
    const postal = brregAddrToString(e.postadresse);
    if (visit) {
      $modal.find('[name=address]').val(visit);
      $modal.find('[name=postal_address]').val(postal && postal !== visit ? postal : '');
    } else if (postal) {
      // Bare postadresse tilgjengelig — fall tilbake til besøksadresse-feltet
      $modal.find('[name=address]').val(postal);
      $modal.find('[name=postal_address]').val('');
    }

    // E-post fra Brreg (offisiell registrert kontakt-epost)
    if (e.epostadresse) {
      $modal.find('[name=email]').val(e.epostadresse);
    }

    // Telefon fra Brreg — preferer telefon, fall tilbake til mobil.
    // Brreg lagrer norske numre uten landskode (f.eks. "21 05 24 00").
    // Strip whitespace + non-digits, prepend +47 hvis ingen prefix.
    const phoneRaw = e.telefon || e.mobil || '';
    if (phoneRaw) {
      let s = String(phoneRaw).trim();
      if (!s.startsWith('+')) {
        s = '+47' + s.replace(/\D/g, '');
      } else {
        s = s.replace(/\s+/g, '');
      }
      const split = splitPhone(s);
      $modal.find('[name=phone_cc]').val(split.cc);
      $modal.find('[name=phone_national]').val(split.national);
    }

    $modal.find('[name=brreg_data]').val(JSON.stringify(e));
    $(this).closest('.lh-brreg-results').hide();
  });

  /* ── Phone helpers (E.164 "+4791234567" ↔ cc + national) ────────────────── */
  // Lengste prefiks vinner. Liste matcher Edifice_CRM::country_codes() i PHP.
  const KNOWN_CCS = ['+372','+370','+371','+358','+354','+353','+351','+420',
                     '+47','+46','+45','+44','+43','+41','+39','+34','+33','+32',
                     '+31','+30','+1','+49','+48','+52','+55','+61','+81','+86','+91'];
  function splitPhone(stored) {
    const raw = String(stored || '').trim();
    if (!raw) return { cc: '+47', national: '' };
    const stripped = raw.replace(/\s+/g, '');
    if (stripped[0] !== '+') return { cc: '+47', national: stripped };
    const sorted = KNOWN_CCS.slice().sort((a, b) => b.length - a.length);
    for (const cc of sorted) {
      if (stripped.startsWith(cc)) return { cc, national: stripped.slice(cc.length) };
    }
    return { cc: '+47', national: stripped.slice(1) };
  }
  // Visningsformatter: norske 8-sifrede nummer som "+47 91 23 45 67",
  // andre land som "+CC nasjonalt-nummer" uten ekstra formatering.
  function formatPhone(stored) {
    if (!stored) return '';
    const { cc, national } = splitPhone(stored);
    if (!national) return cc;
    if (cc === '+47' && /^\d{8}$/.test(national)) {
      return `${cc} ${national.slice(0,2)} ${national.slice(2,4)} ${national.slice(4,6)} ${national.slice(6,8)}`;
    }
    return `${cc} ${national}`;
  }
  // Til tel:-href — strip alle mellomrom (E.164)
  function phoneTelHref(stored) {
    return String(stored || '').replace(/\s+/g, '');
  }

  /* ── Fyll inn skjema fra et record (delt mellom Rediger-knapp og view-modal) */
  function lhFillForm($modal, data) {
    $modal.find('form')[0]?.reset();
    const phoneSplit = splitPhone(data.phone || '');
    const augmented  = Object.assign({}, data, {
      phone_cc:       phoneSplit.cc,
      phone_national: phoneSplit.national,
    });
    Object.entries(augmented).forEach(([k, v]) => {
      const $el = $modal.find(`[name="${k}"]`);
      if (!$el.length) return;
      if ($el.is('select'))                    $el.val(v);
      else if ($el.is('input[type=checkbox]')) $el.prop('checked', !!v);
      else                                     $el.val(v ?? '');
    });
    // Tøm dynamiske lister
    document.getElementById('crm-emails-list')?.replaceChildren();
    document.getElementById('crm-companies-list')?.replaceChildren();
    // Pre-fyll ekstra e-poster
    (data.extra_emails || []).forEach(e => window.crmAddEmailRow?.(e));
    // Pre-fyll selskap-linker (person)
    (data.companies || []).forEach(c => window.crmAddCompanyRow?.({
      company_id: c.company_id, role: c.role || ''
    }));
    // CRM type toggle (også synker postal-address)
    const typeSelect = $modal.find('#crm-type-select');
    if (typeSelect.length) {
      window.crmTypeToggle?.(typeSelect.val() || 'company');
    }
  }

  /* ── Edit pre-fill ───────────────────────────────────────────────────────── */
  $(document).on('click', '.lh-edit-btn', function (e) {
    e.stopPropagation();
    const data   = $(this).data('record');
    const target = $(this).data('modal');
    const $modal = $('#' + target);
    lhFillForm($modal, data);
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
    // Selskaper (person): vis alle linker. Primær først (allerede sortert i SQL).
    if (!isCompany && d.companies && d.companies.length) {
      const companyList = d.companies.map(c => {
        const role = c.role ? ` <span style="color:var(--lh-muted);font-size:12px">(${escHtml(c.role)})</span>` : '';
        return `<div>${escHtml(c.company_name || '—')}${role}</div>`;
      }).join('');
      fields += viewField('Tilknyttet selskap', companyList);
    } else if (d.company_name) {
      // Bakoverkomp: hvis companies-array ikke finnes, fall tilbake til denormalisert felt
      fields += viewField('Tilknyttet selskap', escHtml(d.company_name));
    }
    if (d.org_nr)   fields += viewField('Org.nr', escHtml(d.org_nr));
    if (d.category) fields += viewField('Kategori', escHtml(d.category));
    const [sLabel, sCls] = crmStatusBadge[d.status] || ['?', 'lh-badge-gray'];
    fields += viewField('Status', `<span class="lh-badge ${sCls}">${sLabel}</span>`);

    // E-poster: primær + ekstra (alle med mailto-link)
    const allEmails = [];
    if (d.email) allEmails.push({ email: d.email, label: '' });
    (d.extra_emails || []).forEach(e => allEmails.push({ email: e.email, label: e.label || '' }));
    if (allEmails.length) {
      const emailList = allEmails.map(e => {
        const lbl = e.label ? ` <span style="color:var(--lh-muted);font-size:12px">(${escHtml(e.label)})</span>` : '';
        return `<div><a href="mailto:${escHtml(e.email)}">${escHtml(e.email)}</a>${lbl}</div>`;
      }).join('');
      fields += viewField(allEmails.length > 1 ? 'E-post' : 'E-post', emailList);
    }

    if (d.phone) fields += viewField('Telefon',  `<a href="tel:${escHtml(phoneTelHref(d.phone))}">${escHtml(formatPhone(d.phone))}</a>`);

    // Adresser — begge typer kan ha begge felter; label varierer per type
    const visitLabel = isCompany ? 'Besøksadresse' : 'Hjemmeadresse';
    if (d.address)        fields += viewField(visitLabel,    escHtml(d.address));
    if (d.postal_address) fields += viewField('Postadresse', escHtml(d.postal_address));

    if (d.created_at)  fields += viewField('Opprettet', fmtDate(d.created_at));

    // Sosiale URL-er som kompakte SVG-ikoner med brand-farge på hover
    const SOCIAL_ICONS = {
      linkedin_url: {
        label: 'LinkedIn', brand: '#0a66c2',
        svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>'
      },
      instagram_url: {
        label: 'Instagram', brand: '#e4405f',
        svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>'
      },
      facebook_url: {
        label: 'Facebook', brand: '#1877f2',
        svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'
      },
      x_url: {
        label: 'X', brand: '#000000',
        svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'
      },
      tiktok_url: {
        label: 'TikTok', brand: '#000000',
        svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>'
      },
      custom_url: {
        label: 'Lenke', brand: '#475569',
        svg: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'
      },
    };
    const socialHtml = Object.entries(SOCIAL_ICONS)
      .filter(([key]) => d[key])
      .map(([key, ico]) =>
        `<a class="lh-social-icon" href="${escHtml(d[key])}" target="_blank" rel="noopener" title="${escHtml(ico.label)}" style="--brand:${ico.brand}">${ico.svg}</a>`
      ).join('');
    if (socialHtml) {
      fields += viewField('Lenker', `<div class="lh-social-icons">${socialHtml}</div>`);
    }
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
        const rows = r.data.map((p, idx) =>
          `<div class="lh-person-row lh-clickable" data-person-idx="${idx}">
            <span style="font-size:16px">👤</span>
            <span class="name">${escHtml(p.name)}</span>
            ${p.email ? `<span class="meta">${escHtml(p.email)}</span>` : ''}
            ${p.phone ? `<span class="meta">${escHtml(formatPhone(p.phone))}</span>` : ''}
            <span style="margin-left:auto;color:var(--lh-muted);font-size:13px">→</span>
          </div>`
        ).join('');
        const $list = $('#view-crm-persons-list').html(rows);
        // Persistent reference til personene så drill-down har full record
        $list.data('persons', r.data);
        $list.find('.lh-person-row').off('click.drill').on('click.drill', function () {
          const idx     = parseInt(this.dataset.personIdx, 10);
          const persons = $list.data('persons') || [];
          const person  = persons[idx];
          if (!person) return;
          // Lukk current selskap-modal og åpne person-modal
          lhCloseModal('modal-crm-view');
          // Re-trigger view ved å simulere klikk på en knapp med data
          const $hidden = $('<button class="lh-view-crm-btn" style="display:none"></button>')
            .data('record', person)
            .appendTo('body');
          setTimeout(() => { $hidden.trigger('click').remove(); }, 200);
        });
      });
    } else {
      // Person → show Gmail emails (søker mot ALLE registrerte e-poster)
      $('#view-crm-persons-section').hide();
      const emailsForGmail = [];
      if (d.email) emailsForGmail.push(d.email);
      (d.extra_emails || []).forEach(e => { if (e.email) emailsForGmail.push(e.email); });
      if (emailsForGmail.length) {
        $('#view-crm-gmail-section').show();
        loadGmailEmails(emailsForGmail);
      } else {
        $('#view-crm-gmail-section').hide();
      }
    }

    // "Rediger" button wires back to edit modal
    $('#view-crm-edit-btn').off('click').on('click', function () {
      lhCloseModal('modal-crm-view');
      lhFillForm($('#modal-crm'), d);
      lhOpenModal('modal-crm');
    });

    lhOpenModal('modal-crm-view');
  });

  /* ── Gmail loader ────────────────────────────────────────────────────────── */
  // Aksepterer enten en string (én e-post) eller en array av e-poster.
  function loadGmailEmails(emailOrList) {
    const $list   = $('#view-crm-gmail-list');
    const $status = $('#view-gmail-status');
    const emails  = Array.isArray(emailOrList) ? emailOrList : [emailOrList];
    const email   = emails.join(','); // backend split på komma

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

