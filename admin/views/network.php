<?php
defined('ABSPATH') || exit;

$grouped = Edifice_Network::get_grouped();
$due     = Edifice_Network::get_due_followups(7);
$stats   = Edifice_Network::get_stats();

$tier_count = function ($tier) use ($stats) {
    $row = $stats['tier_counts'][$tier] ?? null;
    return $row ? (int) $row->n : 0;
};

global $wpdb;
$contact_table = $wpdb->prefix . 'edifice_contacts';
// Hent alle kontakter uten tier — kan kategoriseres
$uncategorized = $wpdb->get_results(
    "SELECT id, name, type, company_id FROM `$contact_table`
     WHERE tier IS NULL ORDER BY name ASC",
    ARRAY_A
);
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div>
      <h1>🤝 Nettverk</h1>
      <div class="lh-subtitle">
        Aktive nettverkskontakter og oppfølgingsrytme · Manuell vedlikehold
      </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <button class="lh-btn lh-btn-primary" id="network-add-btn">+ Legg til kontakt</button>
    </div>
  </div>

  <!-- Stats -->
  <div class="lh-stats" style="margin-bottom:20px">
    <div class="lh-stat">
      <div class="lh-stat-label">Tier 1 (månedlig)</div>
      <div class="lh-stat-value green"><?= $tier_count(1) ?></div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Tier 2 (kvartalsvis)</div>
      <div class="lh-stat-value"><?= $tier_count(2) ?></div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Tier 3 (sjekk status årlig)</div>
      <div class="lh-stat-value yellow"><?= $tier_count(3) ?></div>
    </div>
    <div class="lh-stat">
      <div class="lh-stat-label">Forfaller ≤ 7 dager</div>
      <div class="lh-stat-value <?= $stats['due_week'] > 0 ? 'yellow' : '' ?>">
        <?= $stats['due_week'] ?>
      </div>
    </div>
  </div>

  <?php if (!empty($due)): ?>
  <div class="lh-card" style="margin-bottom:24px;border-left:3px solid #f59e0b">
    <div class="lh-card-head">
      <h2>🔔 Trenger oppfølging</h2>
      <div class="lh-subtitle" style="margin-top:4px">
        Kontakter med planlagt handling de neste 7 dager (eller forfalt)
      </div>
    </div>
    <div class="lh-table-wrap">
      <table class="lh-table">
        <thead>
          <tr>
            <th>Navn</th>
            <th>Tier</th>
            <th>Neste handling</th>
            <th>Frist</th>
            <th>Sist kontakt</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($due as $d):
            $days = Edifice_Network::days_until($d['tier_next_action']);
            $days_label = $days === null
              ? '—'
              : ($days < 0 ? "<span style='color:#dc2626'><strong>".abs($days)." dager forsinket</strong></span>"
                : ($days === 0 ? "<strong>I dag</strong>"
                  : "om $days dager"));
            $tier_info = Edifice_Network::TIERS[$d['tier']] ?? null;
          ?>
            <tr class="lh-clickable-row js-network-edit"
                data-contact-id="<?= (int) $d['id'] ?>">
              <td><strong><?= esc_html($d['name']) ?></strong></td>
              <td><?= $tier_info ? $tier_info['emoji'].' '.esc_html($tier_info['label']) : '' ?></td>
              <td><?= esc_html($d['tier_next_action_note'] ?: '—') ?></td>
              <td><?= Edifice_Network::format_date_norwegian($d['tier_next_action']) ?>
                  <div style="font-size:11px;color:var(--lh-muted)"><?= $days_label ?></div></td>
              <td><?= Edifice_Network::format_date_norwegian($d['tier_last_contact']) ?></td>
              <td class="actions">
                <button class="lh-btn lh-btn-primary lh-btn-sm js-network-log"
                        data-contact-id="<?= (int) $d['id'] ?>"
                        title="Marker som kontaktet i dag — ruller neste handling fra frekvens">
                  ✓ Logg kontakt
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tier-grupper -->
  <?php foreach (Edifice_Network::TIERS as $tier_num => $tier_info):
    $contacts = $grouped[$tier_num] ?? [];
    if (empty($contacts)) continue;
  ?>
    <div class="lh-card" style="margin-bottom:20px">
      <div class="lh-card-head">
        <h2><?= $tier_info['emoji'] ?> <?= esc_html($tier_info['label']) ?>
          <span style="font-weight:normal;color:var(--lh-muted);font-size:14px">
            — <?= esc_html($tier_info['desc']) ?>
            (<?= count($contacts) ?>)
          </span>
        </h2>
      </div>
      <div class="lh-table-wrap">
        <table class="lh-table">
          <thead>
            <tr>
              <th>Navn</th>
              <th>Selskap</th>
              <th>Sist kontakt</th>
              <th>Neste handling</th>
              <th>Frekvens</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($contacts as $c):
              $company_str = '';
              if ($c['type'] === 'person' && !empty($c['company_name'])) {
                $company_str = $c['company_name'];
              } elseif ($c['type'] === 'company') {
                $company_str = '<em>selskap</em>';
              }
            ?>
              <tr class="lh-clickable-row js-network-edit"
                  data-contact-id="<?= (int) $c['id'] ?>">
                <td><strong><?= esc_html($c['name']) ?></strong></td>
                <td><?= $company_str ?: '—' ?></td>
                <td><?= Edifice_Network::format_date_norwegian($c['tier_last_contact']) ?></td>
                <td>
                  <?= esc_html($c['tier_next_action_note'] ?: '—') ?>
                  <?php if ($c['tier_next_action']): ?>
                    <div style="font-size:11px;color:var(--lh-muted)">
                      <?= Edifice_Network::format_date_norwegian($c['tier_next_action']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td><?= esc_html($c['tier_frequency'] ?: '—') ?></td>
                <td class="actions">
                  <button class="lh-btn lh-btn-secondary lh-btn-sm js-network-log"
                          data-contact-id="<?= (int) $c['id'] ?>"
                          title="Logg ny kontakt i dag">✓ Logg</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($stats['total'] === 0): ?>
    <div class="lh-card" style="text-align:center;padding:40px 20px">
      <p style="font-size:16px;color:var(--lh-muted)">
        Ingen nettverkskontakter ennå. Klikk <strong>+ Legg til kontakt</strong> for å koble en eksisterende CRM-kontakt
        til nettverkssystemet, eller opprett en ny kontakt i CRM først og kom hit tilbake.
      </p>
    </div>
  <?php endif; ?>
</div>

<!-- Modal: Rediger nettverkskontakt -->
<div class="lh-modal-overlay" id="network-modal">
  <div class="lh-modal">
    <div class="lh-modal-head">
      <h3 id="network-modal-title">Rediger nettverkskontakt</h3>
      <button class="lh-modal-close">&times;</button>
    </div>
    <div class="lh-modal-body">
      <input type="hidden" id="network-modal-contact-id">

      <div class="lh-form-row">
        <label>Kontakt</label>
        <div id="network-modal-contact-name" style="font-weight:600;padding:8px 0"></div>
      </div>

      <div class="lh-form-row">
        <label for="network-modal-tier">Tier <span style="color:#dc2626">*</span></label>
        <select id="network-modal-tier">
          <option value="">— Ikke kategorisert —</option>
          <?php foreach (Edifice_Network::TIERS as $n => $info): ?>
            <option value="<?= $n ?>"><?= $info['emoji'] ?> <?= esc_html($info['label']) ?> — <?= esc_html($info['desc']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="lh-form-row">
        <label for="network-modal-frequency">Frekvens</label>
        <select id="network-modal-frequency">
          <option value="">— Velg —</option>
          <?php foreach (Edifice_Network::FREQUENCIES as $freq): ?>
            <option value="<?= esc_attr($freq) ?>"><?= esc_html($freq) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="lh-form-row" style="display:flex;gap:12px">
        <div style="flex:1">
          <label for="network-modal-last-contact">Sist kontakt</label>
          <input type="date" id="network-modal-last-contact">
        </div>
        <div style="flex:1">
          <label for="network-modal-next-action">Neste handling (dato)</label>
          <input type="date" id="network-modal-next-action">
        </div>
      </div>

      <div class="lh-form-row">
        <label for="network-modal-next-action-note">Neste handling (hva)</label>
        <input type="text" id="network-modal-next-action-note"
               placeholder="F.eks. 'Send oppfølgingsmail om EPBD-notat'">
      </div>

      <div class="lh-form-row">
        <label for="network-modal-relation-note">Relasjonsnotat (statisk bakgrunn — ikke logg)</label>
        <textarea id="network-modal-relation-note" rows="3"
                  placeholder="Hvordan kjenner du dem? Generelle ting om relasjonen."></textarea>
      </div>

      <!-- Interaksjonslogg (strukturert) -->
      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--lh-border)">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
          <strong style="font-size:14px">📋 Interaksjoner</strong>
          <button type="button" class="lh-btn lh-btn-primary lh-btn-sm" id="network-log-interaction-btn"
                  style="margin-left:auto">+ Logg</button>
        </div>
        <div id="network-interactions-list">
          <div style="color:var(--lh-muted);font-size:13px;padding:8px 0">Laster…</div>
        </div>
      </div>
    </div>
    <div class="lh-modal-foot">
      <button class="lh-btn lh-btn-secondary" id="network-modal-clear-btn"
              title="Fjern fra nettverkssystemet (tier slettes, kontakten beholdes i CRM)">
        Fjern fra nettverk
      </button>
      <div style="flex:1"></div>
      <button class="lh-btn lh-btn-secondary lh-modal-close">Avbryt</button>
      <button class="lh-btn lh-btn-primary" id="network-modal-save-btn">Lagre</button>
    </div>
  </div>
</div>

<?php include EDIFICE_DIR . 'admin/views/_interaction-log-modal.php'; ?>

<!-- Modal: Legg til ny nettverkskontakt (søk eksisterende kontakt) -->
<div class="lh-modal-overlay" id="network-add-modal">
  <div class="lh-modal">
    <div class="lh-modal-head">
      <h3>Legg til nettverkskontakt</h3>
      <button class="lh-modal-close">&times;</button>
    </div>
    <div class="lh-modal-body">
      <p style="color:var(--lh-muted);margin-top:0">
        Velg en eksisterende CRM-kontakt og sett tier. Hvis kontakten ikke finnes ennå, opprett den først i
        <a href="<?= admin_url('admin.php?page=edifice-crm') ?>">CRM</a>.
      </p>
      <div class="lh-form-row">
        <label for="network-add-search">Søk etter kontakt</label>
        <input type="text" id="network-add-search" placeholder="Skriv navn …" autocomplete="off">
      </div>
      <div id="network-add-results" style="max-height:300px;overflow-y:auto;border:1px solid var(--lh-border);border-radius:6px;display:none">
        <!-- fylles inn via JS -->
      </div>
    </div>
  </div>
</div>

<!-- Embedded data for JS -->
<script>
window.EdificeNetwork = {
  uncategorized: <?= json_encode(array_map(function($c) {
    return ['id' => (int)$c['id'], 'name' => $c['name'], 'type' => $c['type']];
  }, $uncategorized)) ?>,
  // Map of contact_id => full record (for opening modal without extra AJAX)
  contacts: <?= json_encode(
    array_reduce(
      array_merge(...array_values($grouped)),
      function($acc, $c) {
        $acc[(int)$c['id']] = [
          'id'            => (int)$c['id'],
          'name'          => $c['name'],
          'tier'          => $c['tier'] !== null ? (int)$c['tier'] : null,
          'frequency'     => $c['tier_frequency'],
          'last_contact'  => $c['tier_last_contact'],
          'next_action'   => $c['tier_next_action'],
          'next_action_note' => $c['tier_next_action_note'],
          'relation_note' => $c['tier_relation_note'],
        ];
        return $acc;
      },
      []
    )
  ) ?>,
};
</script>
