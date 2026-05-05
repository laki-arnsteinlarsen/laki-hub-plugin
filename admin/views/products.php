<?php
defined('ABSPATH') || exit;

$totals   = Edifice_Products_Digital::get_totals();
$products = Edifice_Products_Digital::get_all_products();

$fmt = fn($v, $cur='USD') => ($cur === 'NOK' ? 'kr ' : '$') . number_format((float)$v, 2);

$platform_icons = [
    'KDP'        => '📚',
    'Gumroad'    => '🛒',
    'PromptBase' => '🤖',
    'Upwork'     => '💼',
    'Etsy'       => '🏪',
];

$status_cfg = [
    'live'           => ['Live',           'lh-badge--active'],
    'pending_review' => ['Pending Review', 'lh-badge--pending'],
    'scheduled'      => ['Scheduled',      'lh-badge--pending'],
    'draft'          => ['Draft',          'lh-badge--inactive'],
    'rejected'       => ['Rejected',       'lh-badge--overdue'],
    'active'         => ['Aktiv',          'lh-badge--active'],
    'draft'          => ['Utkast',         'lh-badge--inactive'],
    'retired'        => ['Avsluttet',      'lh-badge--overdue'],
];

$type_labels = [
    'ebook'       => 'E-bok',
    'prompt-pack' => 'Prompt Pack',
    'template'    => 'Template',
    'report'      => 'Report',
    'course'      => 'Kurs',
    'service'     => 'Tjeneste',
];
?>

<!-- ═══════════════════════════════════════════════════════════
     VIEW: PRODUCT LIST  (default)
     ═══════════════════════════════════════════════════════════ -->
<div id="products-list-view">
  <div class="lh-wrap">

    <div class="lh-header">
      <h1>🛍️ Produkter &amp; Passiv inntekt</h1>
      <button class="lh-btn lh-btn--primary" id="btn-add-product">+ Nytt produkt</button>
    </div>

    <!-- Stats -->
    <div class="lh-stats">
      <div class="lh-stat">
        <span class="lh-stat__label">Denne måneden</span>
        <span class="lh-stat__value">$<?= number_format((float)$totals['month'], 2) ?></span>
      </div>
      <div class="lh-stat">
        <span class="lh-stat__label">YTD</span>
        <span class="lh-stat__value">$<?= number_format((float)$totals['ytd'], 2) ?></span>
      </div>
      <div class="lh-stat">
        <span class="lh-stat__label">All time</span>
        <span class="lh-stat__value">$<?= number_format((float)$totals['all_time'], 2) ?></span>
      </div>
      <div class="lh-stat">
        <span class="lh-stat__label">Live listings</span>
        <span class="lh-stat__value"><?= (int)$totals['active_listings'] ?></span>
      </div>
    </div>

    <!-- Product cards grid -->
    <?php if (empty($products)): ?>
      <div class="lh-card" style="padding:40px;text-align:center;color:#888">
        Ingen produkter ennå. Klikk «+ Nytt produkt» for å komme i gang.
      </div>
    <?php else: ?>
    <div class="products-grid">
      <?php foreach ($products as $p):
        $pid    = (int)$p['id'];
        $sc     = $status_cfg[$p['status']] ?? ['Aktiv', 'lh-badge--active'];
        $tlabel = $type_labels[$p['type']] ?? $p['type'];
        $rev    = (float)$p['revenue_total'];
        $lcount = (int)$p['listing_count'];
      ?>
      <div class="product-card" data-pid="<?= $pid ?>">
        <div class="product-card__header">
          <div class="product-card__brand"><?= esc_html($p['brand']) ?></div>
          <span class="lh-badge <?= $sc[1] ?>"><?= $sc[0] ?></span>
        </div>
        <div class="product-card__name"><?= esc_html($p['name']) ?></div>
        <div class="product-card__meta">
          <span class="product-card__type"><?= esc_html($tlabel) ?></span>
          <span class="product-card__listings"><?= $lcount ?> kanal<?= $lcount === 1 ? '' : 'er' ?></span>
        </div>
        <div class="product-card__revenue">
          <span class="product-card__rev-value">$<?= number_format($rev, 2) ?></span>
          <span class="product-card__rev-label">totalt</span>
        </div>
        <div class="product-card__actions">
          <button class="lh-btn lh-btn--primary lh-btn--sm btn-view-product" data-pid="<?= $pid ?>">
            Se kanaler →
          </button>
          <button class="lh-btn lh-btn--sm lh-btn--edit btn-edit-product"
            data-id="<?= $pid ?>"
            data-name="<?= esc_attr($p['name']) ?>"
            data-type="<?= esc_attr($p['type']) ?>"
            data-brand="<?= esc_attr($p['brand']) ?>"
            data-status="<?= esc_attr($p['status']) ?>"
            data-description="<?= esc_attr($p['description'] ?? '') ?>">Rediger</button>
          <button class="lh-btn lh-btn--sm lh-btn--danger btn-delete-product" data-id="<?= $pid ?>">Slett</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     VIEW: PRODUCT DETAIL
     ═══════════════════════════════════════════════════════════ -->
<div id="products-detail-view" style="display:none">
  <div class="lh-wrap">

    <div class="lh-header">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="lh-btn lh-btn--secondary" id="btn-back-products">← Tilbake</button>
        <h1 id="detail-product-name" style="margin:0"></h1>
        <span id="detail-product-brand" class="lh-badge lh-badge--inactive" style="font-size:12px"></span>
      </div>
      <button class="lh-btn lh-btn--primary" id="btn-add-listing-detail">+ Legg til kanal</button>
    </div>

    <!-- Product stats -->
    <div class="lh-stats" id="detail-stats">
      <div class="lh-stat">
        <span class="lh-stat__label">Totalt salg</span>
        <span class="lh-stat__value" id="detail-stat-sales">0</span>
      </div>
      <div class="lh-stat">
        <span class="lh-stat__label">Total inntekt</span>
        <span class="lh-stat__value" id="detail-stat-revenue">$0.00</span>
      </div>
      <div class="lh-stat">
        <span class="lh-stat__label">Aktive kanaler</span>
        <span class="lh-stat__value" id="detail-stat-channels">0</span>
      </div>
    </div>

    <!-- Channels / listings table -->
    <div class="lh-card">
      <table class="lh-table" id="detail-listings-table">
        <thead>
          <tr>
            <th>Kanal / Plattform</th>
            <th>Pris</th>
            <th>Status</th>
            <th>Salg</th>
            <th>Inntekt</th>
            <th>Sist synkronisert</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="detail-listings-body">
          <tr><td colspan="7" style="text-align:center;padding:32px;color:#888">Laster...</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Description -->
    <div id="detail-description" style="margin-top:16px;color:#666;font-size:13px;font-style:italic"></div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODALS
     ═══════════════════════════════════════════════════════════ -->

<!-- Product modal -->
<div class="lh-modal-overlay" id="product-modal" style="display:none">
  <div class="lh-modal">
    <div class="lh-modal__header">
      <h2 id="product-modal-title">Nytt produkt</h2>
      <button class="lh-modal__close" data-modal="product-modal">&times;</button>
    </div>
    <form id="product-form">
      <input type="hidden" name="id" id="pf-id">
      <div class="lh-form-row">
        <label>Navn *</label>
        <input type="text" name="name" id="pf-name" required>
      </div>
      <div class="lh-form-row lh-form-row--2col">
        <div>
          <label>Type</label>
          <select name="type" id="pf-type">
            <option value="ebook">E-bok</option>
            <option value="prompt-pack">Prompt Pack</option>
            <option value="template">Template</option>
            <option value="report">Report</option>
            <option value="course">Kurs</option>
            <option value="service">Tjeneste</option>
          </select>
        </div>
        <div>
          <label>Brand</label>
          <select name="brand" id="pf-brand">
            <option value="The Direction Gap">The Direction Gap</option>
            <option value="StrategistKit">StrategistKit</option>
            <option value="LAKI">LAKI</option>
          </select>
        </div>
      </div>
      <div class="lh-form-row">
        <label>Status</label>
        <select name="status" id="pf-status">
          <option value="active">Aktiv</option>
          <option value="draft">Utkast</option>
          <option value="retired">Avsluttet</option>
        </select>
      </div>
      <div class="lh-form-row">
        <label>Beskrivelse</label>
        <textarea name="description" id="pf-description" rows="3"></textarea>
      </div>
      <div class="lh-form-actions">
        <button type="button" class="lh-btn lh-btn--secondary" data-modal="product-modal">Avbryt</button>
        <button type="submit" class="lh-btn lh-btn--primary">Lagre</button>
      </div>
    </form>
  </div>
</div>

<!-- Listing modal -->
<div class="lh-modal-overlay" id="listing-modal" style="display:none">
  <div class="lh-modal">
    <div class="lh-modal__header">
      <h2 id="listing-modal-title">Ny kanal</h2>
      <button class="lh-modal__close" data-modal="listing-modal">&times;</button>
    </div>
    <form id="listing-form">
      <input type="hidden" name="id" id="lf-id">
      <input type="hidden" name="product_id" id="lf-product_id">
      <div class="lh-form-row">
        <label>Plattform *</label>
        <select name="platform" id="lf-platform">
          <option value="Gumroad">Gumroad</option>
          <option value="KDP">Amazon KDP</option>
          <option value="PromptBase">PromptBase</option>
          <option value="Upwork">Upwork Project Catalog</option>
          <option value="Etsy">Etsy</option>
        </select>
      </div>
      <div class="lh-form-row">
        <label>URL</label>
        <input type="url" name="listing_url" id="lf-listing_url" placeholder="https://...">
      </div>
      <div class="lh-form-row lh-form-row--2col">
        <div>
          <label>Pris</label>
          <input type="number" name="price" id="lf-price" step="0.01" min="0">
        </div>
        <div>
          <label>Valuta</label>
          <select name="currency" id="lf-currency">
            <option value="USD">USD</option>
            <option value="NOK">NOK</option>
            <option value="EUR">EUR</option>
          </select>
        </div>
      </div>
      <div class="lh-form-row">
        <label>Status</label>
        <select name="listing_status" id="lf-listing_status">
          <option value="live">Live</option>
          <option value="pending_review">Pending Review</option>
          <option value="scheduled">Scheduled</option>
          <option value="draft">Draft</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <div class="lh-form-row">
        <label>Notater</label>
        <textarea name="notes" id="lf-notes" rows="2"></textarea>
      </div>
      <div class="lh-form-actions">
        <button type="button" class="lh-btn lh-btn--secondary" data-modal="listing-modal">Avbryt</button>
        <button type="submit" class="lh-btn lh-btn--primary">Lagre</button>
      </div>
    </form>
  </div>
</div>

<!-- Revenue modal -->
<div class="lh-modal-overlay" id="revenue-modal" style="display:none">
  <div class="lh-modal">
    <div class="lh-modal__header">
      <h2 id="revenue-modal-title">Registrer inntekt</h2>
      <button class="lh-modal__close" data-modal="revenue-modal">&times;</button>
    </div>
    <form id="revenue-form">
      <input type="hidden" name="id" id="rf-id">
      <input type="hidden" name="listing_id" id="rf-listing_id">
      <div class="lh-form-row">
        <label>Dato</label>
        <input type="date" name="snapshot_date" id="rf-snapshot_date" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="lh-form-row lh-form-row--2col">
        <div>
          <label>Inntekt</label>
          <input type="number" name="revenue" id="rf-revenue" step="0.01" min="0" value="0">
        </div>
        <div>
          <label>Valuta</label>
          <select name="currency" id="rf-currency">
            <option value="USD">USD</option>
            <option value="NOK">NOK</option>
            <option value="EUR">EUR</option>
          </select>
        </div>
      </div>
      <div class="lh-form-row">
        <label>Antall salg</label>
        <input type="number" name="sales_count" id="rf-sales_count" min="0" value="0">
      </div>
      <div class="lh-form-row">
        <label>Notater</label>
        <textarea name="notes" id="rf-notes" rows="2"></textarea>
      </div>
      <div class="lh-form-actions">
        <button type="button" class="lh-btn lh-btn--secondary" data-modal="revenue-modal">Avbryt</button>
        <button type="submit" class="lh-btn lh-btn--primary">Lagre</button>
      </div>
    </form>
  </div>
</div>

<style>
/* ── Product cards grid ──────────────────────────────────── */
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
  padding: 0;
}
.product-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: box-shadow .15s, transform .15s;
  cursor: default;
}
.product-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,.08);
  transform: translateY(-1px);
}
.product-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.product-card__brand {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: #6b7280;
}
.product-card__name {
  font-size: 15px;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.3;
}
.product-card__meta {
  display: flex;
  gap: 10px;
  font-size: 12px;
  color: #9ca3af;
}
.product-card__type { background: #f3f4f6; padding: 2px 8px; border-radius: 4px; }
.product-card__listings { background: #eff6ff; color: #3b82f6; padding: 2px 8px; border-radius: 4px; }
.product-card__revenue {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin-top: 4px;
  padding-top: 10px;
  border-top: 1px solid #f3f4f6;
}
.product-card__rev-value { font-size: 22px; font-weight: 800; color: #1e293b; }
.product-card__rev-label { font-size: 12px; color: #9ca3af; }
.product-card__actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 4px;
}
/* ── Detail view listing rows ────────────────────────────── */
.listing-row-icon { font-size: 18px; margin-right: 6px; }
.listing-platform-name { font-weight: 600; }
.listing-url-link { font-size: 11px; color: #6b7280; margin-left: 6px; }
</style>

<script>
(function($){

  /* ── Helpers ────────────────────────────────────────────── */
  var PLATFORM_ICONS = {
    'KDP':'📚','Gumroad':'🛒','PromptBase':'🤖','Upwork':'💼','Etsy':'🏪'
  };
  var STATUS_CFG = {
    'live':           {label:'Live',           cls:'lh-badge--active'},
    'pending_review': {label:'Pending Review', cls:'lh-badge--pending'},
    'scheduled':      {label:'Scheduled',      cls:'lh-badge--pending'},
    'draft':          {label:'Draft',          cls:'lh-badge--inactive'},
    'rejected':       {label:'Rejected',       cls:'lh-badge--overdue'}
  };

  function openModal(id)  { $('#'+id).fadeIn(150); }
  function closeModal(id) { $('#'+id).fadeOut(150); }

  $(document).on('click', '.lh-modal__close, [data-modal]', function(){
    closeModal($(this).data('modal'));
  });
  $(document).on('click', '.lh-modal-overlay', function(e){
    if ($(e.target).hasClass('lh-modal-overlay')) closeModal($(this).attr('id'));
  });

  function ajax(action, data, cb) {
    data.action = action;
    data.nonce  = Edifice.nonce;
    $.post(Edifice.ajax_url, data)
      .done(function(r){ if (r.success) cb(r.data); else alert('Feil: ' + JSON.stringify(r.data)); })
      .fail(function()  { alert('AJAX-feil. Prøv igjen.'); });
  }

  $.fn.serializeObject = function(){
    var o={};
    $.each(this.serializeArray(), function(){ o[this.name]=this.value; });
    return o;
  };

  /* ── View switching ─────────────────────────────────────── */
  var currentPid = null;

  function showListView() {
    $('#products-detail-view').hide();
    $('#products-list-view').show();
    currentPid = null;
  }

  function showDetailView(pid) {
    currentPid = pid;
    $('#products-list-view').hide();
    $('#products-detail-view').show();
    loadDetail(pid);
  }

  function loadDetail(pid) {
    // Find product in PHP-rendered data
    var card = $('.product-card[data-pid="'+pid+'"]');
    var name  = card.find('.product-card__name').text();
    var brand = card.find('.product-card__brand').text();
    var desc  = card.data('description') || '';

    $('#detail-product-name').text(name);
    $('#detail-product-brand').text(brand);
    $('#detail-description').text(desc);
    $('#detail-listings-body').html('<tr><td colspan="7" style="text-align:center;padding:24px;color:#888">Laster kanaler...</td></tr>');

    // Fetch listings via AJAX
    ajax('edifice_listings_for_product', { pid: pid }, function(data) {
      renderListings(data.listings || []);
    });
  }

  function renderListings(listings) {
    var $body = $('#detail-listings-body');
    $body.empty();

    if (!listings.length) {
      $body.html('<tr><td colspan="7" style="text-align:center;padding:32px;color:#888">Ingen kanaler ennå. Klikk «+ Legg til kanal».</td></tr>');
      $('#detail-stat-sales').text('0');
      $('#detail-stat-revenue').text('$0.00');
      $('#detail-stat-channels').text('0');
      return;
    }

    var totalSales = 0, totalRev = 0, liveCount = 0;

    listings.forEach(function(l) {
      var icon   = PLATFORM_ICONS[l.platform] || '🔗';
      var sc     = STATUS_CFG[l.listing_status] || {label: l.listing_status, cls: 'lh-badge--inactive'};
      var rev    = parseFloat(l.revenue_total) || 0;
      var sales  = parseInt(l.sales_total) || 0;
      totalSales += sales;
      totalRev   += rev;
      if (l.listing_status === 'live') liveCount++;

      var urlHtml = l.listing_url
        ? '<a href="'+l.listing_url+'" target="_blank" class="listing-url-link">↗ Åpne</a>'
        : '';

      $body.append(
        '<tr>' +
        '<td><span class="listing-row-icon">'+icon+'</span>' +
          '<span class="listing-platform-name">'+l.platform+'</span>' + urlHtml +
          (l.notes ? '<div style="font-size:11px;color:#9ca3af;margin-top:2px">'+$('<span>').text(l.notes).html()+'</div>' : '') +
        '</td>' +
        '<td>'+l.currency+' '+parseFloat(l.price).toFixed(2)+'</td>' +
        '<td><span class="lh-badge '+sc.cls+'">'+sc.label+'</span></td>' +
        '<td>'+sales+'</td>' +
        '<td>$'+rev.toFixed(2)+'</td>' +
        '<td>'+(l.last_synced ? l.last_synced.substr(0,10) : '—')+'</td>' +
        '<td class="lh-actions">' +
          '<button class="lh-btn lh-btn--sm btn-add-revenue-detail" data-lid="'+l.id+'" data-lname="'+l.platform+'">+ Inntekt</button> ' +
          '<button class="lh-btn lh-btn--sm lh-btn--edit btn-edit-listing-detail" '+
            'data-id="'+l.id+'" data-product_id="'+l.product_id+'" '+
            'data-platform="'+l.platform+'" data-listing_url="'+(l.listing_url||'')+'" '+
            'data-price="'+l.price+'" data-currency="'+l.currency+'" '+
            'data-listing_status="'+l.listing_status+'" data-notes="'+(l.notes||'')+'">Rediger</button> ' +
          '<button class="lh-btn lh-btn--sm lh-btn--danger btn-delete-listing-detail" data-id="'+l.id+'">Slett</button>' +
        '</td>' +
        '</tr>'
      );
    });

    $('#detail-stat-sales').text(totalSales);
    $('#detail-stat-revenue').text('$'+totalRev.toFixed(2));
    $('#detail-stat-channels').text(liveCount + ' live');
  }

  /* ── Navigation ─────────────────────────────────────────── */
  $(document).on('click', '.btn-view-product', function(){
    showDetailView($(this).data('pid'));
  });

  $('#btn-back-products').on('click', function(){
    showListView();
  });

  /* ── Product CRUD ───────────────────────────────────────── */
  $('#btn-add-product').on('click', function(){
    $('#product-modal-title').text('Nytt produkt');
    $('#product-form')[0].reset();
    $('#pf-id').val('');
    openModal('product-modal');
  });

  $(document).on('click', '.btn-edit-product', function(e){
    e.stopPropagation();
    var d = $(this).data();
    $('#product-modal-title').text('Rediger produkt');
    $('#pf-id').val(d.id); $('#pf-name').val(d.name); $('#pf-type').val(d.type);
    $('#pf-brand').val(d.brand); $('#pf-status').val(d.status); $('#pf-description').val(d.description);
    openModal('product-modal');
  });

  $('#product-form').on('submit', function(e){
    e.preventDefault();
    ajax('edifice_product_save', $(this).serializeObject(), function(){ location.reload(); });
  });

  $(document).on('click', '.btn-delete-product', function(e){
    e.stopPropagation();
    if (!confirm('Slette produktet og alle tilknyttede kanaler og inntektsdata?')) return;
    ajax('edifice_product_delete', { id: $(this).data('id') }, function(){ location.reload(); });
  });

  /* ── Listing CRUD (from detail view) ────────────────────── */
  $('#btn-add-listing-detail').on('click', function(){
    $('#listing-modal-title').text('Ny kanal');
    $('#listing-form')[0].reset();
    $('#lf-id').val(''); $('#lf-product_id').val(currentPid);
    openModal('listing-modal');
  });

  $(document).on('click', '.btn-edit-listing-detail', function(){
    var d = $(this).data();
    $('#listing-modal-title').text('Rediger kanal');
    $('#lf-id').val(d.id); $('#lf-product_id').val(d.product_id);
    $('#lf-platform').val(d.platform); $('#lf-listing_url').val(d.listing_url);
    $('#lf-price').val(d.price); $('#lf-currency').val(d.currency);
    $('#lf-listing_status').val(d.listing_status); $('#lf-notes').val(d.notes);
    openModal('listing-modal');
  });

  $('#listing-form').on('submit', function(e){
    e.preventDefault();
    ajax('edifice_listing_save', $(this).serializeObject(), function(){
      closeModal('listing-modal');
      if (currentPid) loadDetail(currentPid);
    });
  });

  $(document).on('click', '.btn-delete-listing-detail', function(){
    if (!confirm('Slette denne kanalen og all inntektshistorikk?')) return;
    var lid = $(this).data('id');
    ajax('edifice_listing_delete', { id: lid }, function(){
      if (currentPid) loadDetail(currentPid);
    });
  });

  /* ── Revenue entry ──────────────────────────────────────── */
  $(document).on('click', '.btn-add-revenue-detail', function(){
    $('#revenue-modal-title').text('Inntekt — ' + $(this).data('lname'));
    $('#revenue-form')[0].reset();
    $('#rf-id').val(''); $('#rf-listing_id').val($(this).data('lid'));
    $('#rf-snapshot_date').val(new Date().toISOString().split('T')[0]);
    openModal('revenue-modal');
  });

  $('#revenue-form').on('submit', function(e){
    e.preventDefault();
    ajax('edifice_product_revenue_save', $(this).serializeObject(), function(){
      closeModal('revenue-modal');
      if (currentPid) loadDetail(currentPid);
    });
  });

})(jQuery);
</script>
