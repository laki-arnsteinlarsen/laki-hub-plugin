<?php
defined('ABSPATH') || exit;

$totals   = Edifice_Products_Digital::get_totals();
$products = Edifice_Products_Digital::get_all_products();
$listings = Edifice_Products_Digital::get_all_listings();

$fmt_usd = fn($v) => '$' . number_format((float)$v, 2);
$fmt_nok = fn($v) => 'kr ' . number_format((float)$v, 0, ',', ' ');

// Group listings by product_id for the detail rows
$listings_by_product = [];
foreach ($listings as $l) {
    $listings_by_product[(int)$l['product_id']][] = $l;
}

$platform_icons = [
    'KDP'       => '📚',
    'Gumroad'   => '🛒',
    'PromptBase'=> '🤖',
    'Upwork'    => '💼',
    'Etsy'      => '🏪',
];

$status_labels = [
    'live'           => ['label' => 'Live',            'class' => 'lh-badge--active'],
    'pending_review' => ['label' => 'Pending Review',  'class' => 'lh-badge--pending'],
    'scheduled'      => ['label' => 'Scheduled',       'class' => 'lh-badge--pending'],
    'draft'          => ['label' => 'Draft',            'class' => 'lh-badge--inactive'],
    'rejected'       => ['label' => 'Rejected',         'class' => 'lh-badge--overdue'],
];
?>
<div class="lh-wrap">

  <div class="lh-header">
    <h1>💰 Produkter &amp; Passiv inntekt</h1>
    <button class="lh-btn lh-btn--primary" id="btn-add-product">+ Nytt produkt</button>
  </div>

  <!-- Stats -->
  <div class="lh-stats">
    <div class="lh-stat">
      <span class="lh-stat__label">Denne måneden</span>
      <span class="lh-stat__value"><?= $fmt_usd($totals['month']) ?></span>
    </div>
    <div class="lh-stat">
      <span class="lh-stat__label">YTD</span>
      <span class="lh-stat__value"><?= $fmt_usd($totals['ytd']) ?></span>
    </div>
    <div class="lh-stat">
      <span class="lh-stat__label">All time</span>
      <span class="lh-stat__value"><?= $fmt_usd($totals['all_time']) ?></span>
    </div>
    <div class="lh-stat">
      <span class="lh-stat__label">Live listings</span>
      <span class="lh-stat__value"><?= (int)$totals['active_listings'] ?></span>
    </div>
  </div>

  <!-- Products table -->
  <div class="lh-card">
    <table class="lh-table lh-table--products" id="products-table">
      <thead>
        <tr>
          <th style="width:28px"></th>
          <th>Produkt</th>
          <th>Brand</th>
          <th>Type</th>
          <th>Listings</th>
          <th>Totalt salg</th>
          <th>Total inntekt</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr><td colspan="9" style="text-align:center;color:#888;padding:32px">Ingen produkter ennå. Klikk «+ Nytt produkt» for å komme i gang.</td></tr>
        <?php else: ?>
          <?php foreach ($products as $p):
            $pid    = (int) $p['id'];
            $plists = $listings_by_product[$pid] ?? [];
            $sl     = $status_labels[$p['status']] ?? ['label' => $p['status'], 'class' => 'lh-badge--inactive'];
          ?>
          <!-- Product row -->
          <tr class="product-row" data-pid="<?= $pid ?>">
            <td class="expand-cell">
              <?php if (!empty($plists)): ?>
                <span class="expand-toggle">▶</span>
              <?php endif; ?>
            </td>
            <td><strong><?= esc_html($p['name']) ?></strong></td>
            <td><?= esc_html($p['brand']) ?></td>
            <td><?= esc_html($p['type']) ?></td>
            <td><?= (int)$p['listing_count'] ?></td>
            <td><?= (int)$p['sales_total'] ?></td>
            <td><?= $fmt_usd($p['revenue_total']) ?></td>
            <td><span class="lh-badge <?= $sl['class'] ?>"><?= $sl['label'] ?></span></td>
            <td class="lh-actions">
              <button class="lh-btn lh-btn--sm btn-add-listing" data-pid="<?= $pid ?>" data-pname="<?= esc_attr($p['name']) ?>">+ Listing</button>
              <button class="lh-btn lh-btn--sm lh-btn--edit btn-edit-product"
                data-id="<?= $pid ?>"
                data-name="<?= esc_attr($p['name']) ?>"
                data-type="<?= esc_attr($p['type']) ?>"
                data-brand="<?= esc_attr($p['brand']) ?>"
                data-status="<?= esc_attr($p['status']) ?>"
                data-description="<?= esc_attr($p['description'] ?? '') ?>">Rediger</button>
              <button class="lh-btn lh-btn--sm lh-btn--danger btn-delete-product" data-id="<?= $pid ?>">Slett</button>
            </td>
          </tr>
          <!-- Listings detail (collapsed) -->
          <?php if (!empty($plists)): ?>
          <tr class="listings-detail-row" data-pid="<?= $pid ?>" style="display:none">
            <td colspan="9" style="padding:0 0 12px 36px;background:#f9fafb">
              <table class="lh-table lh-table--sm" style="margin:8px 0;background:white">
                <thead>
                  <tr>
                    <th>Plattform</th>
                    <th>Pris</th>
                    <th>Status</th>
                    <th>Salg</th>
                    <th>Inntekt</th>
                    <th>Sist synk.</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($plists as $l):
                    $icon = $platform_icons[$l['platform']] ?? '🔗';
                    $ls   = $status_labels[$l['listing_status']] ?? ['label' => $l['listing_status'], 'class' => 'lh-badge--inactive'];
                  ?>
                  <tr>
                    <td>
                      <?= $icon ?> <?= esc_html($l['platform']) ?>
                      <?php if ($l['listing_url']): ?>
                        <a href="<?= esc_url($l['listing_url']) ?>" target="_blank" style="margin-left:6px;font-size:11px;color:#666">↗</a>
                      <?php endif; ?>
                    </td>
                    <td><?= strtoupper($l['currency']) ?> <?= number_format((float)$l['price'], 2) ?></td>
                    <td><span class="lh-badge <?= $ls['class'] ?>"><?= $ls['label'] ?></span></td>
                    <td><?= (int)$l['sales_total'] ?></td>
                    <td><?= $fmt_usd($l['revenue_total']) ?></td>
                    <td><?= $l['last_synced'] ? esc_html(substr($l['last_synced'],0,10)) : '—' ?></td>
                    <td class="lh-actions">
                      <button class="lh-btn lh-btn--sm btn-add-revenue"
                        data-lid="<?= (int)$l['id'] ?>"
                        data-lname="<?= esc_attr($l['platform'] . ' — ' . $p['name']) ?>">+ Inntekt</button>
                      <button class="lh-btn lh-btn--sm lh-btn--edit btn-edit-listing"
                        data-id="<?= (int)$l['id'] ?>"
                        data-product_id="<?= $pid ?>"
                        data-platform="<?= esc_attr($l['platform']) ?>"
                        data-listing_url="<?= esc_attr($l['listing_url'] ?? '') ?>"
                        data-price="<?= esc_attr($l['price']) ?>"
                        data-currency="<?= esc_attr($l['currency']) ?>"
                        data-listing_status="<?= esc_attr($l['listing_status']) ?>"
                        data-notes="<?= esc_attr($l['notes'] ?? '') ?>">Rediger</button>
                      <button class="lh-btn lh-btn--sm lh-btn--danger btn-delete-listing" data-id="<?= (int)$l['id'] ?>">Slett</button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Modal: Product ───────────────────────────────────────────────────── -->
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

<!-- ── Modal: Listing ──────────────────────────────────────────────────── -->
<div class="lh-modal-overlay" id="listing-modal" style="display:none">
  <div class="lh-modal">
    <div class="lh-modal__header">
      <h2 id="listing-modal-title">Ny listing</h2>
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

<!-- ── Modal: Revenue entry ────────────────────────────────────────────── -->
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

<script>
(function($){

  // ── Helpers ────────────────────────────────────────────────────────────────
  function openModal(id)  { $('#' + id).fadeIn(150); }
  function closeModal(id) { $('#' + id).fadeOut(150); }

  $(document).on('click', '.lh-modal__close, [data-modal]', function(){
    closeModal($(this).data('modal'));
  });
  $(document).on('click', '.lh-modal-overlay', function(e){
    if ($(e.target).hasClass('lh-modal-overlay')) closeModal($(this).attr('id'));
  });

  function ajaxSave(action, formData, onSuccess) {
    formData.action = action;
    formData.nonce  = Edifice.nonce;
    $.post(Edifice.ajax_url, formData)
      .done(function(r){ if (r.success) onSuccess(r); else alert('Feil: ' + JSON.stringify(r.data)); })
      .fail(function()  { alert('AJAX-feil. Prøv igjen.'); });
  }

  // ── Expand/collapse listings ───────────────────────────────────────────────
  $(document).on('click', '.expand-toggle', function(){
    var pid = $(this).closest('.product-row').data('pid');
    var detailRow = $('[data-pid="'+pid+'"].listings-detail-row');
    if (detailRow.is(':visible')) {
      detailRow.hide();
      $(this).text('▶');
    } else {
      detailRow.show();
      $(this).text('▼');
    }
  });

  // ── Product CRUD ──────────────────────────────────────────────────────────
  $('#btn-add-product').on('click', function(){
    $('#product-modal-title').text('Nytt produkt');
    $('#product-form')[0].reset();
    $('#pf-id').val('');
    openModal('product-modal');
  });

  $(document).on('click', '.btn-edit-product', function(){
    var d = $(this).data();
    $('#product-modal-title').text('Rediger produkt');
    $('#pf-id').val(d.id);
    $('#pf-name').val(d.name);
    $('#pf-type').val(d.type);
    $('#pf-brand').val(d.brand);
    $('#pf-status').val(d.status);
    $('#pf-description').val(d.description);
    openModal('product-modal');
  });

  $('#product-form').on('submit', function(e){
    e.preventDefault();
    ajaxSave('edifice_product_save', $(this).serializeObject(), function(){
      location.reload();
    });
  });

  $(document).on('click', '.btn-delete-product', function(){
    if (!confirm('Slette produktet og alle tilknyttede listings og inntektsdata?')) return;
    ajaxSave('edifice_product_delete', { id: $(this).data('id') }, function(){ location.reload(); });
  });

  // ── Listing CRUD ──────────────────────────────────────────────────────────
  $(document).on('click', '.btn-add-listing', function(){
    $('#listing-modal-title').text('Ny listing — ' + $(this).data('pname'));
    $('#listing-form')[0].reset();
    $('#lf-id').val('');
    $('#lf-product_id').val($(this).data('pid'));
    openModal('listing-modal');
  });

  $(document).on('click', '.btn-edit-listing', function(){
    var d = $(this).data();
    $('#listing-modal-title').text('Rediger listing');
    $('#lf-id').val(d.id);
    $('#lf-product_id').val(d.product_id);
    $('#lf-platform').val(d.platform);
    $('#lf-listing_url').val(d.listing_url);
    $('#lf-price').val(d.price);
    $('#lf-currency').val(d.currency);
    $('#lf-listing_status').val(d.listing_status);
    $('#lf-notes').val(d.notes);
    openModal('listing-modal');
  });

  $('#listing-form').on('submit', function(e){
    e.preventDefault();
    ajaxSave('edifice_listing_save', $(this).serializeObject(), function(){
      location.reload();
    });
  });

  $(document).on('click', '.btn-delete-listing', function(){
    if (!confirm('Slette denne listingen og all inntektshistorikk?')) return;
    ajaxSave('edifice_listing_delete', { id: $(this).data('id') }, function(){ location.reload(); });
  });

  // ── Revenue entry ─────────────────────────────────────────────────────────
  $(document).on('click', '.btn-add-revenue', function(){
    $('#revenue-modal-title').text('Inntekt — ' + $(this).data('lname'));
    $('#revenue-form')[0].reset();
    $('#rf-id').val('');
    $('#rf-listing_id').val($(this).data('lid'));
    $('#rf-snapshot_date').val(new Date().toISOString().split('T')[0]);
    openModal('revenue-modal');
  });

  $('#revenue-form').on('submit', function(e){
    e.preventDefault();
    ajaxSave('edifice_product_revenue_save', $(this).serializeObject(), function(){
      location.reload();
    });
  });

  // ── jQuery serializeObject helper ─────────────────────────────────────────
  $.fn.serializeObject = function(){
    var obj = {};
    $.each(this.serializeArray(), function(){ obj[this.name] = this.value; });
    return obj;
  };

})(jQuery);
</script>
