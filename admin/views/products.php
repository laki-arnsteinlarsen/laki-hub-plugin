<?php
if (!defined('ABSPATH')) exit;

$totals   = Edifice_Products_Digital::get_totals();
$channels = Edifice_Products_Digital::get_channels_summary();

// Channel display config — order + icons + labels + accent color
$channel_config = [
    'PromptBase' => ['icon' => '🤖', 'label' => 'PromptBase', 'color' => '#6366f1'],
    'Gumroad'    => ['icon' => '🛒', 'label' => 'Gumroad',    'color' => '#f59e0b'],
    'Etsy'       => ['icon' => '🎨', 'label' => 'Etsy',       'color' => '#F1641E'],
    'KDP'        => ['icon' => '📚', 'label' => 'KDP',        'color' => '#10b981'],
    'Upwork'     => ['icon' => '💼', 'label' => 'Upwork',     'color' => '#3b82f6'],
];

?>

<style>
/* ── Channel cards (kanal-spesifikk aksent på topp) ── */
.lh-channel-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.lh-channel-card {
    background: var(--lh-surface);
    border: 1px solid var(--lh-border);
    border-radius: var(--lh-radius);
    box-shadow: var(--lh-shadow);
    padding: 20px 22px 18px;
    cursor: pointer;
    transition: box-shadow var(--transition), transform var(--transition);
    position: relative;
    overflow: hidden;
}
.lh-channel-card:hover {
    box-shadow: 0 6px 18px rgba(30,58,95,.12);
    transform: translateY(-2px);
}
.lh-channel-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--ch-color, var(--gold-500));
}
.lh-channel-card .ch-icon  { font-size: 28px; line-height: 1; margin-bottom: 10px; }
.lh-channel-card .ch-label {
    font-size: 11px;
    color: var(--lh-muted);
    text-transform: uppercase;
    letter-spacing: .8px;
    font-weight: 600;
}
.lh-channel-card .ch-stat-row { display: flex; justify-content: space-between; margin-top: 14px; gap: 8px; }
.lh-channel-card .ch-stat { flex: 1; }
.lh-channel-card .ch-stat .val {
    font-family: var(--font-heading);
    font-size: 20px;
    font-weight: 700;
    color: var(--navy-800);
    line-height: 1.1;
}
.lh-channel-card .ch-stat .lbl { font-size: 11px; color: var(--lh-muted); margin-top: 2px; }
.lh-channel-card .ch-listings-badge {
    display: inline-block;
    background: var(--neutral-100);
    color: var(--lh-muted);
    font-size: 11px;
    font-weight: 600;
    padding: 2px 9px;
    border-radius: 999px;
    margin-top: 12px;
}

/* ── Drill-down: tilbake-knapp og header ── */
#ch-detail-view { display: none; }
.lh-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--navy-800);
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 600;
    padding: 0;
    margin-bottom: 18px;
}
.lh-back-btn:hover { text-decoration: underline; }
.lh-detail-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 22px;
}
.lh-detail-header .dh-icon { font-size: 36px; }
.lh-detail-header .dh-title {
    font-family: var(--font-heading);
    font-size: 22px;
    font-weight: 700;
    color: var(--navy-800);
}
.lh-detail-header .dh-sub { font-size: 13px; color: var(--lh-muted); margin-top: 3px; }

/* Loader + tom-state inne i drill-down */
.lh-spinner { text-align: center; padding: 30px 0; color: var(--lh-muted); font-size: 13px; }
</style>

<div class="lh-wrap">
    <div class="lh-header">
        <div>
            <h1>Produkter &amp; kanaler</h1>
            <div class="lh-subtitle">Digitale produkter på tvers av PromptBase, Gumroad, Etsy, KDP og Upwork</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="lh-btn lh-btn-secondary lh-btn-sm" id="btn-add-product">+ Nytt produkt</button>
            <button class="lh-btn lh-btn-primary lh-btn-sm"   id="btn-add-listing">+ Ny listing</button>
            <button class="lh-btn lh-btn-ghost lh-btn-sm"     id="btn-import-csv" title="Importer listings fra CSV (Etsy m.fl. uten API)">📥 Importer CSV</button>
            <button class="lh-btn lh-btn-ghost lh-btn-sm"     id="btn-sync-gumroad" title="Synk Gumroad-inntekter">🔄 Gumroad-sync</button>
        </div>
    </div>

    <!-- Stat-rad ── -->
    <div class="lh-stats">
        <div class="lh-stat">
            <div class="lh-stat-label">Total inntekt</div>
            <div class="lh-stat-value">$<?= number_format((float)$totals['all_time'], 2) ?></div>
        </div>
        <div class="lh-stat">
            <div class="lh-stat-label">Denne måneden</div>
            <div class="lh-stat-value green">$<?= number_format((float)$totals['month'], 2) ?></div>
        </div>
        <div class="lh-stat">
            <div class="lh-stat-label">Hittil i år</div>
            <div class="lh-stat-value">$<?= number_format((float)$totals['ytd'], 2) ?></div>
        </div>
        <div class="lh-stat">
            <div class="lh-stat-label">Aktive listings</div>
            <div class="lh-stat-value"><?= (int)$totals['active_listings'] ?></div>
        </div>
        <div class="lh-stat">
            <div class="lh-stat-label">Listings totalt</div>
            <div class="lh-stat-value"><?= (int)$totals['total_listings'] ?></div>
        </div>
    </div>

    <!-- Channel grid (top-level view) -->
    <div id="ch-list-view">
        <div class="lh-channel-grid">
            <?php foreach ($channel_config as $platform => $cfg):
                $ch    = $channels[$platform] ?? null;
                $count = $ch ? (int)$ch['listing_count'] : 0;
                $month = $ch ? (float)$ch['month_revenue'] : 0;
                $total = $ch ? (float)$ch['total_revenue']  : 0;
                $live  = $ch && isset($ch['live_count']) ? (int)$ch['live_count'] : 0;
            ?>
            <div class="lh-channel-card"
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
                <span class="ch-listings-badge"><?= $live ?> aktive / <?= $count ?> totalt</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Channel drill-down view -->
    <div id="ch-detail-view">
        <button class="lh-back-btn" onclick="ediBackToChannels()">← Alle kanaler</button>
        <div class="lh-detail-header">
            <div class="dh-icon" id="ch-detail-icon"></div>
            <div>
                <div class="dh-title" id="ch-detail-title"></div>
                <div class="dh-sub"   id="ch-detail-sub"></div>
            </div>
        </div>

        <div class="lh-spinner" id="ch-spinner" style="display:none">Laster listings…</div>

        <div id="ch-detail-content"></div>
    </div>
</div>

<!-- ── Modal: Add/Edit product ── -->
<div class="lh-modal-overlay" id="modal-product">
    <div class="lh-modal">
        <div class="lh-modal-head">
            <h3 id="modal-product-title">Nytt produkt</h3>
            <button class="lh-modal-close" type="button" onclick="ediCloseModal('modal-product')">×</button>
        </div>
        <div class="lh-modal-body">
            <input type="hidden" id="prod-id" value="">
            <div class="lh-form-row">
                <label>Produktnavn</label>
                <input type="text" id="prod-name" placeholder="f.eks. The Direction Gap">
            </div>
            <div class="lh-form-grid">
                <div class="lh-form-row">
                    <label>Brand / nisje</label>
                    <input type="text" id="prod-brand" placeholder="f.eks. Direction Gap">
                </div>
                <div class="lh-form-row">
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
            <div class="lh-form-row">
                <label>Beskrivelse</label>
                <textarea id="prod-description" placeholder="Kort beskrivelse…"></textarea>
            </div>
            <div class="lh-form-row">
                <label>Status</label>
                <select id="prod-status">
                    <option value="active">Aktiv</option>
                    <option value="draft">Utkast</option>
                    <option value="archived">Arkivert</option>
                </select>
            </div>
        </div>
        <div class="lh-modal-foot">
            <button class="lh-btn lh-btn-secondary" type="button" onclick="ediCloseModal('modal-product')">Avbryt</button>
            <button class="lh-btn lh-btn-primary" type="button" onclick="ediSaveProduct()">Lagre</button>
        </div>
    </div>
</div>

<!-- ── Modal: Add/Edit listing ── -->
<div class="lh-modal-overlay" id="modal-listing">
    <div class="lh-modal">
        <div class="lh-modal-head">
            <h3 id="modal-listing-title">Ny listing</h3>
            <button class="lh-modal-close" type="button" onclick="ediCloseModal('modal-listing')">×</button>
        </div>
        <div class="lh-modal-body">
            <input type="hidden" id="lst-id" value="">
            <div class="lh-form-row">
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
            </div>
            <div class="lh-form-grid">
                <div class="lh-form-row">
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
                <div class="lh-form-row">
                    <label>Status</label>
                    <select id="lst-status">
                        <option value="live">Live</option>
                        <option value="pending_review">Pending review</option>
                        <option value="draft">Utkast</option>
                        <option value="archived">Arkivert</option>
                    </select>
                </div>
            </div>
            <div class="lh-form-row">
                <label>Listing URL</label>
                <input type="url" id="lst-url" placeholder="https://…">
            </div>
            <div class="lh-form-grid">
                <div class="lh-form-row">
                    <label>Pris (USD)</label>
                    <input type="number" id="lst-price" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="lh-form-row">
                    <label>Valuta</label>
                    <select id="lst-currency">
                        <option value="USD">USD</option>
                        <option value="NOK">NOK</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>
            </div>
            <div class="lh-form-row">
                <label>Notater</label>
                <textarea id="lst-notes" placeholder="Valgfritt…"></textarea>
            </div>
        </div>
        <div class="lh-modal-foot">
            <button class="lh-btn lh-btn-secondary" type="button" onclick="ediCloseModal('modal-listing')">Avbryt</button>
            <button class="lh-btn lh-btn-primary" type="button" onclick="ediSaveListing()">Lagre</button>
        </div>
    </div>
</div>

<!-- ── Modal: Add revenue entry ── -->
<div class="lh-modal-overlay" id="modal-revenue">
    <div class="lh-modal">
        <div class="lh-modal-head">
            <h3>Legg til inntektsoppføring</h3>
            <button class="lh-modal-close" type="button" onclick="ediCloseModal('modal-revenue')">×</button>
        </div>
        <div class="lh-modal-body">
            <input type="hidden" id="rev-listing-id" value="">
            <div class="lh-form-grid">
                <div class="lh-form-row">
                    <label>Dato</label>
                    <input type="date" id="rev-date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="lh-form-row">
                    <label>Inntekt (USD)</label>
                    <input type="number" id="rev-revenue" step="0.01" min="0" placeholder="0.00">
                </div>
            </div>
            <div class="lh-form-grid">
                <div class="lh-form-row">
                    <label>Antall salg</label>
                    <input type="number" id="rev-sales" min="0" placeholder="0">
                </div>
                <div class="lh-form-row">
                    <label>Valuta</label>
                    <select id="rev-currency">
                        <option value="USD">USD</option>
                        <option value="NOK">NOK</option>
                    </select>
                </div>
            </div>
            <div class="lh-form-row">
                <label>Notater</label>
                <textarea id="rev-notes" placeholder="Valgfritt…"></textarea>
            </div>
        </div>
        <div class="lh-modal-foot">
            <button class="lh-btn lh-btn-secondary" type="button" onclick="ediCloseModal('modal-revenue')">Avbryt</button>
            <button class="lh-btn lh-btn-primary" type="button" onclick="ediSaveRevenue()">Lagre</button>
        </div>
    </div>
</div>

<!-- ── Modal: CSV-import ── -->
<div class="lh-modal-overlay" id="modal-import-csv">
    <div class="lh-modal lh-modal-wide">
        <div class="lh-modal-head">
            <h3>📥 Importer listings fra CSV</h3>
            <button class="lh-modal-close" type="button" onclick="ediCloseModal('modal-import-csv')">×</button>
        </div>
        <div class="lh-modal-body">
            <p style="font-size:13px;color:var(--lh-muted);margin:0 0 16px">
                Brukes når plattformen ikke har API (typisk Etsy). Eksporter listings fra plattformen, lim inn CSV her.
                Påkrevd kolonne: <code>Title</code> (eller Name / Listing Title). Valgfrie: <code>URL</code>, <code>Price</code>, <code>Currency</code>, <code>Status</code>, <code>Notes</code>.
            </p>
            <div class="lh-form-row">
                <label>Plattform</label>
                <select id="import-csv-platform">
                    <option value="Etsy">Etsy</option>
                    <option value="Gumroad">Gumroad</option>
                    <option value="PromptBase">PromptBase</option>
                    <option value="KDP">KDP</option>
                    <option value="Upwork">Upwork</option>
                    <option value="Other">Annet</option>
                </select>
            </div>
            <div class="lh-form-row">
                <label>CSV (med header-rad)</label>
                <textarea id="import-csv-text" rows="10" style="font-family:monospace;font-size:12px"
                          placeholder="Title,URL,Price,Currency
Foo Product,https://etsy.com/listing/123,29.99,USD
Bar Product,https://etsy.com/listing/456,19.99,USD"></textarea>
            </div>
            <div id="import-csv-result"></div>
        </div>
        <div class="lh-modal-foot">
            <button class="lh-btn lh-btn-secondary" type="button" onclick="ediCloseModal('modal-import-csv')">Avbryt</button>
            <button class="lh-btn lh-btn-primary" type="button" id="btn-run-import-csv">Kjør import</button>
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
                        '<div class="lh-empty"><p>⚠️ Feil: ' + msg + '</p></div>';
                }
            },
            error: function(xhr) {
                document.getElementById('ch-spinner').style.display = 'none';
                document.getElementById('ch-detail-content').innerHTML =
                    '<div class="lh-empty"><p>⚠️ AJAX-feil (' + xhr.status + '): ' +
                    xhr.responseText.substring(0, 300) + '</p></div>';
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
                '<div class="lh-empty"><p>Ingen listings registrert for ' + platform + ' ennå.</p></div>';
            return;
        }

        const statusBadge = (s) => {
            const cls = s === 'live' ? 'lh-badge-green' : (s === 'pending_review' ? 'lh-badge-yellow' : 'lh-badge-gray');
            const label = { live: 'Live', pending_review: 'Pending', draft: 'Utkast', archived: 'Arkivert' }[s] || s;
            return `<span class="lh-badge ${cls}">${label}</span>`;
        };

        let html = `
        <div class="lh-card">
        <div class="lh-table-wrap">
        <table class="lh-table">
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
                ? `<a href="${l.listing_url}" target="_blank" style="color:var(--navy-800)">${l.listing_url.replace(/^https?:\/\//, '').substring(0, 42)}…</a>`
                : '<span style="color:var(--lh-muted)">—</span>';

            html += `
            <tr>
                <td><strong>${escHtml(l.product_name || '—')}</strong><br>
                    <span style="font-size:11px;color:var(--lh-muted)">${escHtml(l.product_brand || '')}</span></td>
                <td>${urlCell}</td>
                <td>$${parseFloat(l.price || 0).toFixed(2)}</td>
                <td>${statusBadge(l.listing_status)}</td>
                <td>$${monthRev}</td>
                <td>$${totalRev}</td>
                <td style="font-size:11px;color:var(--lh-muted)">${l.last_synced || '—'}</td>
                <td class="actions">
                    <button class="lh-btn lh-btn-secondary lh-btn-sm"
                        onclick="ediEditListing(${JSON.stringify(l).split('"').join('&quot;')})">✏️</button>
                    <button class="lh-btn lh-btn-secondary lh-btn-sm"
                        onclick="ediAddRevenue(${l.id})">＋$</button>
                </td>
            </tr>`;
        });

        html += `</tbody></table></div></div>`;
        document.getElementById('ch-detail-content').innerHTML = html;
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Modals ──
    window.ediCloseModal = function(id) {
        document.getElementById(id).classList.remove('open');
    };
    function openModal(id) {
        document.getElementById(id).classList.add('open');
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

    // CSV-import modal
    document.getElementById('btn-import-csv').addEventListener('click', function() {
        openModal('modal-import-csv');
    });
    document.getElementById('btn-run-import-csv')?.addEventListener('click', function() {
        const btn = this;
        const csv = document.getElementById('import-csv-text').value.trim();
        const platform = document.getElementById('import-csv-platform').value;
        if (!csv) { alert('Tom CSV — lim inn data først.'); return; }
        btn.disabled = true;
        btn.textContent = '⏳ Importerer…';
        ajax('edifice_listings_import_csv', { csv: csv, platform: platform }, function(data) {
            btn.disabled = false;
            btn.textContent = 'Kjør import';
            const out = document.getElementById('import-csv-result');
            if (data.ok) {
                const s = data.stats;
                out.innerHTML = `<div style="padding:12px;background:var(--color-success-bg);border:1px solid #bbf7d0;border-radius:8px;font-size:13px;color:var(--color-success-txt)">
                    ✅ Ferdig — ${s.new_listings} nye listings, ${s.new_products} nye produkter, ${s.updated} oppdatert, ${s.errors} feil (${s.rows} rader totalt).
                </div>`;
                setTimeout(() => location.reload(), 2000);
            } else {
                out.innerHTML = `<div style="padding:12px;background:var(--color-error-bg);border:1px solid #fecaca;border-radius:8px;font-size:13px;color:var(--color-error-txt)">❌ ${data.error || 'ukjent feil'}</div>`;
            }
        });
    });

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
    document.querySelectorAll('.lh-modal-overlay').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === el) el.classList.remove('open');
        });
    });

})(jQuery);
</script>
