/* Laki Hub — Admin JS */
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
    $.post(LakiHub.ajax_url, { action, nonce: LakiHub.nonce, ...data }, function (r) {
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
  let brregTimer;
  $(document).on('input', '.lh-brreg-search', function () {
    clearTimeout(brregTimer);
    const q   = $(this).val().trim();
    const $res = $(this).siblings('.lh-brreg-results');
    if (q.length < 2) { $res.hide(); return; }
    brregTimer = setTimeout(() => {
      $.post(LakiHub.ajax_url, {
        action: 'laki_brreg_lookup',
        nonce: LakiHub.nonce,
        query: q,
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
    const e   = $(this).data('org');
    const $modal = $(this).closest('.lh-modal-body');
    $modal.find('[name=name]').val(e.navn || '');
    $modal.find('[name=org_nr]').val(e.organisasjonsnummer || '');
    // Address
    const adr = e.forretningsadresse || e.postadresse || {};
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
    Object.entries(data).forEach(([k, v]) => {
      const $el = $modal.find(`[name="${k}"]`);
      if ($el.is('select')) $el.val(v);
      else if ($el.is('input[type=checkbox]')) $el.prop('checked', !!v);
      else $el.val(v);
    });
    lhOpenModal(target);
  });

  /* ── Number formatting ───────────────────────────────────────────────────── */
  window.lhFmtNOK = (v) => new Intl.NumberFormat('nb-NO', { style: 'currency', currency: 'NOK', maximumFractionDigits: 0 }).format(v || 0);

})(jQuery);
