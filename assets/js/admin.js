/* Edifice — Admin JS */
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
  window.lhOpenModal = (id) => document.getElementById(id)?.classList.add('open');
  window.lhCloseModal = (id) => {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('open'); m.querySelectorAll('form').forEach(f => f.reset()); }
  };

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
        // Vis toast kun hvis responsen eksplisitt inneholder en melding
        if (r.data && r.data.msg) toast(r.data.msg, 'success');
        cb && cb(r);          // send hele responsen (success + data) til callback
      } else {
        toast((r.data && r.data.message) || r.data || 'Noe gikk galt', 'error');
        cb && cb(r);
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
    // checkboxes
    $(this).find('input[type=checkbox]').each(function() {
      data[this.name] = this.checked ? 1 : 0;
    });
    lhAjax(data.ajax_action, data, () => {
      $btn.prop('disabled', false);
      location.reload();
    });
    $btn.prop('disabled', false);
  });

  /* ── Brreg live search ───────────────────────────────────────────────────── */
  const brregIsOrgNr = (s) => /^\d[\d\s]{7,10}$/.test(s);

  let brregTimer;
  $(document).on('input', '.lh-brreg-search', function () {
    clearTimeout(brregTimer);
    const q    = $(this).val().trim();
    const $res = $(this).siblings('.lh-brreg-results');
    if (q.length < 2) { $res.hide(); return; }

    const isOrgNr = brregIsOrgNr(q);
    const payload = isOrgNr
      ? { action: 'edifice_brreg_lookup', nonce: Edifice.nonce, org_nr: q.replace(/\s/g, '') }
      : { action: 'edifice_brreg_lookup', nonce: Edifice.nonce, query: q };

    brregTimer = setTimeout(() => {
      $res.html('<div class="lh-brreg-loading">Søker…</div>').show();
      $.post(Edifice.ajax_url, payload, function (r) {
        if (!r.success) { $res.hide(); return; }
        const items = Array.isArray(r.data) ? r.data : [r.data];
        if (!items.length || items[0]?.error) {
          $res.html('<div class="lh-brreg-empty">Ingen treff</div>');
          return;
        }
        $res.html(items.slice(0, 8).map(e => {
          const adr     = e.forretningsadresse || e.postadresse || {};
          const adrLine = [(adr.adresse || []).join(' '), ((adr.postnummer || '') + ' ' + (adr.poststed || '')).trim()].filter(s => s).join(', ');
          const naring  = e.naeringskode1?.beskrivelse || '';
          return `<div class="lh-brreg-item" data-org='${JSON.stringify(e)}'>
            <div class="lh-brreg-item-name">${e.navn || ''}</div>
            <div class="lh-brreg-item-meta">
              <span>${e.organisasjonsnummer || ''}</span>
              <span>${e.organisasjonsform?.beskrivelse || ''}</span>
              ${adrLine ? `<span>${adrLine}</span>` : ''}
              ${naring  ? `<span class="lh-brreg-naring">${naring}</span>` : ''}
            </div>
          </div>`;
        }).join('')).show();
      });
    }, isOrgNr ? 100 : 350);
  });

  $(document).on('click', '.lh-brreg-item', function () {
    const e      = $(this).data('org');
    const $modal = $(this).closest('.lh-modal-body');
    $modal.find('[name=name]').val(e.navn || '');
    $modal.find('[name=org_nr]').val(e.organisasjonsnummer || '');
    // Sett type basert på Brreg organisasjonsform.kode
    const orgKode = e.organisasjonsform?.kode || '';
    const brregTypeMap = {
      // Person / enkeltpersonforetak
      ENK: 'person', PERS: 'person',
      // Forening / lag / innretning
      FLI: 'association', KIRK: 'association', ORGL: 'association',
      ADOS: 'association', IKJP: 'association',
      // Stiftelse
      STI: 'foundation',
      // Offentlig sektor
      KOMM: 'public', FYLK: 'public', STAT: 'public',
      KF: 'public', FKF: 'public', SF: 'public', IKS: 'public',
      // Alt annet er selskap (AS, ASA, ANS, DA, NUF, SA, BA osv.)
    };
    $modal.find('[name=type]').val(brregTypeMap[orgKode] || 'company');
    // Adresse
    const adr     = e.forretningsadresse || e.postadresse || {};
    const addrStr = [(adr.adresse || []).join(' '), ((adr.postnummer || '') + ' ' + (adr.poststed || '')).trim()].filter(s => s).join(', ');
    $modal.find('[name=address]').val(addrStr);
    // Fyll inn kategori fra næringskode hvis feltet er tomt
    const naring = e.naeringskode1?.beskrivelse || '';
    if (naring && !$modal.find('[name=category]').val()) {
      $modal.find('[name=category]').val(naring);
    }
    $modal.find('[name=brreg_data]').val(JSON.stringify(e));
    // Tøm søkefeltet og skjul resultater
    $(this).closest('.lh-brreg-results').hide().prev('.lh-brreg-search').val('');
  });

  // Skjul Brreg-resultater ved klikk utenfor
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.lh-brreg-search, .lh-brreg-results').length) {
      $('.lh-brreg-results').hide();
    }
  });

  /* ── Edit pre-fill ───────────────────────────────────────────────────────── */
  $(document).on('click', '.lh-edit-btn', function () {
    const data   = $(this).data('record');
    const target = $(this).data('modal');
    const $modal = $('#' + target);

    // Nullstill checkboxer før utfylling
    $modal.find('.lh-cat-check').prop('checked', false);

    Object.entries(data).forEach(([k, v]) => {
      if (k === 'category') {
        // v er array fra PHP — huk av matching checkboxer og oppdater hidden input
        const cats = Array.isArray(v) ? v : (v ? JSON.parse(v) : []);
        $modal.find('.lh-cat-check').each(function () {
          $(this).prop('checked', cats.includes(this.value));
        });
        $modal.find('[name=category]').val(JSON.stringify(cats));
        return;
      }
      const $el = $modal.find(`[name="${k}"]`);
      if (!$el.length) return;
      if ($el.is('select'))               $el.val(v);
      else if ($el.is('input[type=checkbox]')) $el.prop('checked', !!v);
      else                                $el.val(v);
    });

    lhOpenModal(target);
  });

  /* ── Number formatting ───────────────────────────────────────────────────── */
  window.lhFmtNOK = (v) => new Intl.NumberFormat('nb-NO', { style: 'currency', currency: 'NOK', maximumFractionDigits: 0 }).format(v || 0);

})(jQuery);
