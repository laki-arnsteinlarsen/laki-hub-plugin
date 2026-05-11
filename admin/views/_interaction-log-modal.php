<?php
defined('ABSPATH') || exit;

/**
 * Delt log-interaksjon-modal. Inkluderes én gang per render.
 * Guard mot dobbel-inklusjon på SPA-en hvor flere views inngår.
 */
if (defined('EDIFICE_INTERACTION_MODAL_RENDERED')) return;
define('EDIFICE_INTERACTION_MODAL_RENDERED', true);
?>
<div class="lh-modal-overlay" id="modal-interaction-log" style="z-index:100001">
  <div class="lh-modal">
    <div class="lh-modal-head">
      <h3>Logg interaksjon</h3>
      <button class="lh-modal-close">×</button>
    </div>
    <div class="lh-modal-body">
      <input type="hidden" id="interaction-contact-id">
      <div class="lh-form-row">
        <label>Kontakt</label>
        <div id="interaction-contact-name" style="font-weight:600;padding:6px 0"></div>
      </div>
      <div class="lh-form-grid">
        <div class="lh-form-row">
          <label for="interaction-dato">Dato <span style="color:#dc2626">*</span></label>
          <input type="date" id="interaction-dato">
        </div>
        <div class="lh-form-row">
          <label for="interaction-tid">Tid (valgfri)</label>
          <input type="time" id="interaction-tid">
        </div>
      </div>
      <div class="lh-form-grid">
        <div class="lh-form-row">
          <label for="interaction-kanal">Kanal <span style="color:#dc2626">*</span></label>
          <select id="interaction-kanal">
            <?php foreach (Edifice_Interactions::KANALER as $k => $label): ?>
              <option value="<?= esc_attr($k) ?>"><?= esc_html($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="lh-form-row">
          <label for="interaction-retning">Retning</label>
          <select id="interaction-retning">
            <?php foreach (Edifice_Interactions::RETNINGER as $k => $label): ?>
              <option value="<?= esc_attr($k) ?>" <?= $k === 'toveis' ? 'selected' : '' ?>><?= esc_html($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="lh-form-row">
        <label for="interaction-sammendrag">Sammendrag <span style="color:#dc2626">*</span></label>
        <input type="text" id="interaction-sammendrag" maxlength="500"
               placeholder="Kort beskrivelse av interaksjonen">
      </div>
      <div class="lh-form-row">
        <label for="interaction-notat">Notat (valgfritt)</label>
        <textarea id="interaction-notat" rows="3"
                  placeholder="Detaljer, sitater, neste steg …"></textarea>
      </div>
    </div>
    <div class="lh-modal-foot">
      <div style="flex:1"></div>
      <button class="lh-btn lh-btn-secondary lh-modal-close">Avbryt</button>
      <button class="lh-btn lh-btn-primary" id="interaction-save-btn">Lagre</button>
    </div>
  </div>
</div>
