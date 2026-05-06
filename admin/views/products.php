<?php
if (!defined('ABSPATH')) exit;

$totals   = Edifice_Products_Digital::get_totals();
$channels = Edifice_Products_Digital::get_channels_summary();

// Channel display config — order + icons + labels
$channel_config = [
    'PromptBase' => ['icon' => '🤖', 'label' => 'PromptBase', 'color' => '#6366f1'],
    'Gumroad'    => ['icon' => '🛒', 'label' => 'Gumroad',    'color' => '#f59e0b'],
    'KDP'        => ['icon' => '📚', 'label' => 'KDP',        'color' => '#10b981'],
    'Upwork'     => ['icon' => '💼', 'label' => 'Upwork',     'color' => '#3b82f6'],
];

?>

<style>
/* ── Channel cards ── */
.edi-channel-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 18px;
    margin-bottom: 32px;
}
.edi-channel-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px 22px 18px;
    cursor: pointer;
    transition: box-shadow .15s, transform .1s;
    position: relative;
    overflow: hidden;
}
.edi-channel-card:hover {
    box-shadow: 0 6px 18px rgba(0,0,0,.1);
    transform: translateY(-2px);
}
.edi-channel-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--ch-color, #64748b);
}
.edi-channel-card .ch-icon  { font-size: 28px; line-height: 1; margin-bottom: 10px; }
.edi-channel-card .ch-label { font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
.edi-channel-card .ch-stat-row { display: flex; justify-content: space-between; margin-top: 14px; gap: 8px; }
.edi-channel-card .ch-stat { flex: 1; }
.edi-channel-card .ch-stat .val { font-size: 20px; font-weight: 700; color: #1e293b; line-height: 1.1; }
.edi-channel-card .ch-stat .lbl { font-size: 11px; color: #94a3b8; margin-top: 2px; }
.edi-channel-card .ch-listings-badge {
    display: inline-block;
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    margin-top: 12px;
}

/* ── Summary bar ── */
.edi-summary-bar {
    display: flex;
    gap: 24px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 24px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}
.edi-summary-bar .sb-item { display: flex; flex-direction: column; }
.edi-summary-bar .sb-val  { font-size: 22px; font-weight: 700; color: #1e293b; }
.edi-summary-bar .sb-lbl  { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-top: 2px; }
.edi-summary-bar .sb-divider { width: 1px; background: #e2e8f0; align-self: stretch; }

/* ── Drill-down view ── */
#ch-detail-view { display: none; }
.edi-back-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: none; border: none; cursor: pointer;
    color: #3b82f6; font-size: 14px; font-weight: 600;
    padding: 0; margin-bottom: 20px;
}
.edi-back-btn:hover { text-decoration: underline; }
.edi-detail-header {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 22px;
}
.edi-detail-header .dh-icon  { font-size: 36px; }
.edi-detail-header .dh-title { font-size: 22px; font-weight: 700; color: #1e293b; }
.edi-detail-header .dh-sub   { font-size: 13px; color: #64748b; margin-top: 3px; }

/* Listings table */
.edi-table-wrap { overflow-x: auto; }
.edi-table {
    width: 100%; border-collapse: collapse;
    font-size: 13px;
}
.edi-table th {
    text-align: left; padding: 10px 14px;
    background: #f8fafc; color: #64748b;
    font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    border-bottom: 1px solid #e2e8f0;
}
.edi-table td {
    padding: 11px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    color: #1e293b;
}
.edi-table tr:last-child td { border-bottom: none; }
.edi-table tr:hover td { background: #f8fafc; }
.edi-status-badge {
    display: inline-block;
    padding: 2px 8px; border-radius: 20px;
    font-size: 11px; font-weight: 600;
}
.status-live    { background: #dcfce7; color: #166534; }
.status-pending { background: #fef9c3; color: #854d0e; }
.status-other   { background: #f1f5f9; color: #475569; }
.edi-link { color: #3b82f6; text-decoration: none; }
.edi-link:hover { text-decoration: underline; }

/* Add product/listing buttons */
.edi-action-bar {
    display: flex; gap: 10px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}
.edi-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 7px; font-size: 13px;
    font-weight: 600; cursor: pointer; border: none; text-decoration: none;
    transition: opacity .15s;
}
.edi-btn:hover { opacity: .85; }
.edi-btn-primary { background: #3b82f6; color: #fff; }
.edi-btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

/* Loading spinner */
.edi-spinner { display: none; color: #64748b; font-size: 13px; padding: 30px 0; text-align: center; }

/* Empty state */
.edi-empty { text-align: center; padding: 48px 0; color: #94a3b8; font-size: 14px; }

/* Modals (reused from original) */
.edi-modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.45); z-index:9998;
    align-items:center; justify-content:center;
}
.edi-modal-overlay.active { display:flex; }
.edi-modal {
    background:#fff; border-radius:10px; padding:28px 32px;
    width:560px; max-width:95vw; max-height:90vh; overflow-y:auto;
    position:relative; z-index:9999;
}
.edi-modal h3 { margin:0 0 20px; font-size:17px; color:#1e293b; }
.edi-modal label { display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px; }
.edi-modal input, .edi-modal select, .edi-modal textarea {
    width:100%; padding:8px 10px; border:1px solid #e2e8f0;
    border-radius:6px; font-size:13px; margin-bottom:14px; box-sizing:border-box;
}
.edi-modal textarea { height:80px; resize:vertical; }
.edi-modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:6px; }
.edi-modal-close {
    position:absolute; top:14px; right:18px;
    background:none; border:none; font-size:20px; cursor:pointer; color:#94a3b8;
}
.edi-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

/* Revenue section inside detail */
.edi-revenue-section {
    margin-top: 32px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px 22px;
}
.edi-revenue-section h3 { margin: 0 0 16px; font-size: 15px; color: #1e293b; }
</style>

<div class="wrap" style="max-width:1100px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <h1 style="margin:0; font-size:22px; color:#1e293b;">🛍️ Produkter &amp; Kanaler</h1>
        <div class="edi-action-bar" style="margin:0;">
            <button class="edi-btn edi-btn-secondary" id="btn-add-product">+ Nytt produkt</button>
            <button class="edi-btn edi-btn-primary"   id="btn-add-listing">+ Ny listing</button>
            <button class="edi-btn edi-btn-secondary" id="btn-sync-gumroad" title="Synk Gumroad-inntekter">🔄 Gumroad-sync</button>
        </div>
    </div>

    <!-- Summary bar -->
    <div class="edi-summary-bar">
        <div class="sb-item">
            <span class="sb-val">$<?= number_format((float)$totals['all_time'], 2) ?></span>
            <span class="sb-lbl">Total inntekt</span>
        </div>
        <div class="sb-divider"></div>
        <div class="sb-item">
            <span class="sb-val">$<?= number_format((float)$totals['month'], 2) ?></span>
            <span class="sb-lbl">Denne måneden</span>
        </div>
        <div class="sb-divider"></div>
        <div class="sb-item">
            <span class="sb-val">$<?= number_format((float)$totals['ytd'], 2) ?></span>
            <span class="sb-lbl">Hittil i år</span>
        </div>
        <div class="sb-divider"></div>
        <div class="sb-item">
            <span class="sb-val"><?= (int)$totals['active_listings'] ?></span>
            <span class="sb-lbl">Aktive listings</span>
        </div>
    </div>

    <!-- Channel grid (top-level view) -->
    <div id="ch-list-view">
        <div class="edi-channel-grid">
            <?php foreach ($channel_config as $platform => $cfg):
                $ch    = $channels[$platform] ?? null;
                $count = $ch ? (int)$ch['listing_count'] : 0;
                $month = $ch ? (float)$ch['month_revenue'] : 0;
                $total = $ch ? (float)$ch['total_revenue']  : 0;
            ?>
            <div class="edi-channel-card"
                 style="--ch-color: <?= esc_attr($cfg['color']) ?>;"
                 data-platform="<?= esc_attr($platform) ?>"
                 onclick="ediOpenChannel('<?= esc_js($platform) ?>', '<?= esc_js($cfg['icon']) ?>')">
                <div class="ch-icon"><?= $cfg['icon'] ?></div>
                <div class="ch-label"><?= esc_html($cfg['label']) ?></div>
                <div class="ch-stat-row">
                    <div class="ch-stat">
                        <div class="val">$<?= number_format($month, 2) ?></div>
                        <div class="lbl">Denne mnd</div>
                    </div>
                    <div class="ch-stat">
                        <div class="val">$<?= number_format($total, 2) ?></div>
                        <div class="lbl">Totalt</div>
                    </div>
                </div>
                <span class="ch-listings-badge"><?= $count ?> listing<?= $count !== 1 ? 's' : '' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Channel drill-down view -->
    <div id="ch-detail-view">
        <button class="edi-back-btn" onclick="ediBackToChannels()">← Alle kanaler</button>
        <div class="edi-detail-header">
            <div class="dh-icon" id="ch-detail-icon"></div>
            <div>
                <div class="dh-title" id="ch-detail-title"></div>
                <div class="dh-sub"   id="ch-detail-sub"></div>
            </div>
        </div>

        <div class="edi-spinner" id="ch-spinner">Laster listings…</div>

        <div id="ch-detail-content"></div>
    </div>
</div>

<!-- ── Modal: Add/Edit product ── -->
<div class="edi-modal-overlay" id="modal-product">
    <div class="edi-modal">
        <button class="edi-modal-close" onclick="ediCloseModal('modal-product')">×</button>
        <h3 id="modal-product-title">Nytt produkt</h3>
        <input type="hidden" id="prod-id" value="">
        <label>Produktnavn</label>
        <input type="text" id="prod-name" placeholder="f.eks. The Direction Gap">
        <div class="edi-row-2">
            <div>
                <label>Brand / nisje</label>
                <input type="text" id="prod-brand" placeholder="f.eks. Direction Gap">
            </div>
            <div>
                <label>Type</label>
                <select id="prod-type">
                    <option value="ebook">E-bok</option>
                    <option value="prompt_pack">Prompt Pack</option>
                    <option value="agent_skill">Agent Skill</option>
                    <option value="template">Template</option>
                    <option value="course">Kurs</option>
                    <option value="other">Annet</option>
                </select>
            </div>
        </div>
        <label>Beskrivelse</label>
        <textarea id="prod-description" placeholder="Kort beskrivelse…"></textarea>
        <label>Status</label>
        <select id="prod-status">
            <option value="active">Aktiv</option>
            <option value="draft">Utkast</option>
            <option value="archived">Arkivert</option>
        </select>
        <div class="edi-modal-actions">
            <button class="edi-btn edi-btn-secondary" onclick="ediCloseModal('modal-product')">Avbryt</button>
            <button class="edi-btn edi-btn-primary" onclick="ediSaveProduct()">Lagre</button>
        </div>
    </div>
</div>

<!-- ── Modal: Add/Edit listing ── -->
<div class="edi-modal-overlay" id="modal-listing">
    <div class="edi-modal">
        <button class="edi-modal-close" onclick="ediCloseModal('modal-listing')">×</button>
        <h3 id="modal-listing-title">Ny listing</h3>
        <input type="hidden" id="lst-id" value="">
        <label>Produkt</label>
        <select id="lst-product-id">
            <option value="">Velg produkt…</option>
            <?php
            $all_products = Edifice_Products_Digital::get_all_products();
            foreach ($all_products as $prod):
            ?>
            <option value="<?= (int)$prod['id'] ?>"><?= esc_html($prod['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="edi-row-2">
            <div>
                <label>Plattform / kanal</label>
                <select id="lst-platform">
                    <option value="PromptBase">PromptBase</option>
                    <option value="Gumroad">Gumroad</option>
                    <option value="KDP">KDP</option>
                    <option value="Upwork">Upwork</option>
                    <option value="Etsy">Etsy</option>
                    <option value="Other">Annet</option>
                </select>
            </div>
            <div>
                <label>Status</label>
                <select id="lst-status">
                    <option value="live">Live</option>
                    <option value="pending_review">Pending review</option>
                    <option value="draft">Utkast</option>
                    <option value="archived">Arkivert</option>
                </select>
            </div>
        </div>
        <label>Listing URL</label>
        <input type="url" id="lst-url" placeholder="https://…">
        <div class="edi-row-2">
            <div>
                <label>Pris (USD)</label>
                <input type="number" id="lst-price" step="0.01" min="0" placeholder="0.00">
            </div>
            <div>
                <label>Valuta</label>
                <select id="lst-currency">
                    <option value="USD">USD</option>
                    <option value="NOK">NOK</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>
        </div>
        <label>Notater</label>
        <textarea id="lst-notes" placeholder="Valgfritt…"></textarea>
        <div class="edi-modal-actions">
            <button class="edi-btn edi-btn-secondary" onclick="ediCloseModal('modal-listing')">Avbryt</button>
            <button class="edi-btn edi-btn-primary" onclick="ediSaveListing()">Lagre</button>
        </div>
    </div>
</div>

<!-- ── Modal: Add revenue entry ── -->
<div class="edi-modal-overlay" id="modal-revenue">
    <div class="edi-modal">
        <button class="edi-modal-close" onclick="ediCloseModal('modal-revenue')">×</button>
        <h3>Legg til inntektsoppføring</h3>
        <input type="hidden" id="rev-listing-id" value="">
        <div class="edi-row-2">
            <div>
                <label>Dato</label>
                <input type="date" id="rev-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label>Inntekt (USD)</label>
                <input type="number" id="rev-revenue" step="0.01" min="0" placeholder="0.00">
            </div>
        </div>
        <div class="edi-row-2">
            <div>
                <label>Antall salg</label>
                <input type="number" id="rev-sales" min="0" placeholder="0">
            </div>
            <div>
                <label>Valuta</label>
                <select id="rev-currency">
                    <option value="USD">USD</option>
                    <option value="NOK">NOK</option>
                </select>
            </div>
        </div>
        <label>Notater</label>
        <textarea id="rev-notes" placeholder="Valgfritt…"></textarea>
        <div class="edi-modal-actions">
            <button class="edi-btn edi-btn-secondary" onclick="ediCloseModal('modal-revenue')">Avbryt</button>
            <button class="edi-btn edi-btn-primary" onclick="ediSaveRevenue()">Lagre</button>
        </div>
    </div>
</div>

<script>
(function($) {
    const NONCE = Edifice.nonce;
    let currentPlatform = null;

    // ── AJAX helper ──
    function ajax(action, data, cb) {
        $.ajax({
            url: Edifice.ajax_url,
            type: 'POST',
            data: Object.assign({ action, nonce: NONCE }, data),
            dataType: 'json',
            success: function(res) {
                if (res && res.success) { cb(res.data); }
                else {
                    document.getElementById('ch-spinner').style.display = 'none';
                    const msg = res?.data?.message || action;
                    document.getElementById('ch-detail-content').innerHTML =
                        '<div class="edi-empty">⚠️ Feil: ' + msg + '</div>';
                }
            },
            error: function(xhr) {
                document.getElementById('ch-spinner').style.display = 'none';
                document.getElementById('ch-detail-content').innerHTML =
                    '<div class="edi-empty">⚠️ AJAX-feil (' + xhr.status + '): ' +
                    xhr.responseText.substring(0, 300) + '</div>';
            }
        });
    }

    // ── Channel navigation ──
    window.ediOpenChannel = function(platform, icon) {
        currentPlatform = platform;
        document.getElementById('ch-list-view').style.display   = 'none';
        document.getElementById('ch-detail-view').style.display = 'block';
        document.getElementById('ch-detail-icon').textContent   = icon;
        document.getElementById('ch-detail-title').textContent  = platform;
        document.getElementById('ch-detail-content').innerHTML  = '';
        document.getElementById('ch-spinner').style.display     = 'block';
        document.getElementById('ch-detail-sub').textContent    = 'Laster…';

        ajax('edifice_listings_for_channel', { platform }, function(data) {
            document.getElementById('ch-spinner').style.display = 'none';
            renderChannelListings(platform, data.listings || []);
        });
    };

    window.ediBackToChannels = function() {
        currentPlatform = null;
        document.getElementById('ch-detail-view').style.display = 'none';
        document.getElementById('ch-list-view').style.display   = 'block';
    };

    function renderChannelListings(platform, listings) {
        const sub = document.getElementById('ch-detail-sub');
        sub.textContent = listings.length + ' listing' + (listings.length !== 1 ? 's' : '');

        if (!listings.length) {
            document.getElementById('ch-detail-content').innerHTML =
                '<div class="edi-empty">Ingen listings registrert for ' + platform + ' ennå.</div>';
            return;
        }

        const statusBadge = (s) => {
            const cls = s === 'live' ? 'status-live' : (s === 'pending_review' ? 'status-pending' : 'status-other');
            const label = { live: 'Live', pending_review: 'Pending', draft: 'Utkast', archived: 'Arkivert' }[s] || s;
            return `<span class="edi-status-badge ${cls}">${label}</span>`;
        };

        let html = `
        <div class="edi-table-wrap">
        <table class="edi-table">
            <thead>
                <tr>
                    <th>Produkt</th>
                    <th>Tittel / URL</th>
                    <th>Pris</th>
                    <th>Status</th>
                    <th>Mnd inntekt</th>
                    <th>Total</th>
                    <th>Sist synk</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>`;

        listings.forEach(l => {
            const monthRev = parseFloat(l.month_revenue || 0).toFixed(2);
            const totalRev = parseFloat(l.revenue_total || 0).toFixed(2);
            const urlCell  = l.listing_url
                ? `<a class="edi-link" href="${l.listing_url}" target="_blank">${l.listing_url.replace(/^https?:\/\//, '').substring(0, 42)}…</a>`
                : '<span style="color:#94a3b8">—</span>';

            html += `
            <tr>
                <td><strong>${escHtml(l.product_name || '—')}</strong><br>
                    <span style="font-size:11px;color:#94a3b8">${escHtml(l.product_brand || '')}</span></td>
                <td>${urlCell}</td>
                <td>$${parseFloat(l.price || 0).toFixed(2)}</td>
                <td>${statusBadge(l.listing_status)}</td>
                <td>$${monthRev}</td>
                <td>$${totalRev}</td>
                <td style="font-size:11px;color:#94a3b8">${l.last_synced || '—'}</td>
                <td>
                    <button class="edi-btn edi-btn-secondary" style="padding:4px 10px;font-size:11px;"
                        onclick="ediEditListing(${JSON.stringify(l).split('"').join('&quot;')})">✏️</button>
                    <button class="edi-btn edi-btn-secondary" style="padding:4px 10px;font-size:11px;"
                        onclick="ediAddRevenue(${l.id})">＋$</button>
                </td>
            </tr>`;
        });

        html += `</tbody></table></div>`;
        document.getElementById('ch-detail-content').innerHTML = html;
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Modals ──
    window.ediCloseModal = function(id) {
        document.getElementById(id).classList.remove('active');
    };
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }

    // Product modal
    document.getElementById('btn-add-product').addEventListener('click', function() {
        document.getElementById('modal-product-title').textContent = 'Nytt produkt';
        document.getElementById('prod-id').value          = '';
        document.getElementById('prod-name').value        = '';
        document.getElementById('prod-brand').value       = '';
        document.getElementById('prod-description').value = '';
        document.getElementById('prod-type').value        = 'ebook';
        document.getElementById('prod-status').value      = 'active';
        openModal('modal-product');
    });

    window.ediSaveProduct = function() {
        ajax('edifice_product_save', {
            id:          document.getElementById('prod-id').value,
            name:        document.getElementById('prod-name').value,
            brand:       document.getElementById('prod-brand').value,
            description: document.getElementById('prod-description').value,
            product_type:document.getElementById('prod-type').value,
            status:      document.getElementById('prod-status').value,
        }, function() { ediCloseModal('modal-product'); location.reload(); });
    };

    // Listing modal — new
    document.getElementById('btn-add-listing').addEventListener('click', function() {
        document.getElementById('modal-listing-title').textContent = 'Ny listing';
        document.getElementById('lst-id').value       = '';
        document.getElementById('lst-url').value      = '';
        document.getElementById('lst-price').value    = '';
        document.getElementById('lst-notes').value    = '';
        document.getElementById('lst-platform').value = currentPlatform || 'PromptBase';
        document.getElementById('lst-status').value   = 'live';
        openModal('modal-listing');
    });

    // Listing modal — edit
    window.ediEditListing = function(l) {
        document.getElementById('modal-listing-title').textContent  = 'Rediger listing';
        document.getElementById('lst-id').value                     = l.id;
        document.getElementById('lst-product-id').value             = l.product_id;
        document.getElementById('lst-platform').value               = l.platform;
        document.getElementById('lst-status').value                 = l.listing_status;
        document.getElementById('lst-url').value                    = l.listing_url || '';
        document.getElementById('lst-price').value                  = l.price || '';
        document.getElementById('lst-currency').value               = l.currency || 'USD';
        document.getElementById('lst-notes').value                  = l.notes || '';
        openModal('modal-listing');
    };

    window.ediSaveListing = function() {
        ajax('edifice_listing_save', {
            id:             document.getElementById('lst-id').value,
            product_id:     document.getElementById('lst-product-id').value,
            platform:       document.getElementById('lst-platform').value,
            listing_status: document.getElementById('lst-status').value,
            listing_url:    document.getElementById('lst-url').value,
            price:          document.getElementById('lst-price').value,
            currency:       document.getElementById('lst-currency').value,
            notes:          document.getElementById('lst-notes').value,
        }, function() {
            ediCloseModal('modal-listing');
            if (currentPlatform) {
                // Refresh current channel view
                const icon = document.getElementById('ch-detail-icon').textContent;
                ediOpenChannel(currentPlatform, icon);
            } else {
                location.reload();
            }
        });
    };

    // Revenue modal
    window.ediAddRevenue = function(listingId) {
        document.getElementById('rev-listing-id').value = listingId;
        document.getElementById('rev-revenue').value    = '';
        document.getElementById('rev-sales').value      = '';
        document.getElementById('rev-notes').value      = '';
        document.getElementById('rev-date').value       = new Date().toISOString().split('T')[0];
        openModal('modal-revenue');
    };

    window.ediSaveRevenue = function() {
        ajax('edifice_product_revenue_save', {
            listing_id:  document.getElementById('rev-listing-id').value,
            date:        document.getElementById('rev-date').value,
            revenue:     document.getElementById('rev-revenue').value,
            sales_count: document.getElementById('rev-sales').value,
            currency:    document.getElementById('rev-currency').value,
            notes:       document.getElementById('rev-notes').value,
        }, function() {
            ediCloseModal('modal-revenue');
            if (currentPlatform) {
                const icon = document.getElementById('ch-detail-icon').textContent;
                ediOpenChannel(currentPlatform, icon);
            } else {
                location.reload();
            }
        });
    };

    // Gumroad sync
    document.getElementById('btn-sync-gumroad').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.textContent = '⏳ Synker…';
        ajax('edifice_sync_gumroad', {}, function(data) {
            btn.disabled = false;
            btn.textContent = '🔄 Gumroad-sync';
            alert('✅ Gumroad-sync fullført: ' + JSON.stringify(data));
            location.reload();
        });
    });

    // Close modals on overlay click
    document.querySelectorAll('.edi-modal-overlay').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === el) el.classList.remove('active');
        });
    });

})(jQuery);
</script>
