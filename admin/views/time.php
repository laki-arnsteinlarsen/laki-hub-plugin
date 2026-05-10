<?php defined('ABSPATH') || exit;

[$from, $to, $period_label] = LakiHub_Time::get_period_range('month');
$stats      = LakiHub_Time::get_period_stats($from, $to);
$by_client  = LakiHub_Time::get_by_client($from, $to);
$by_project = LakiHub_Time::get_by_project($from, $to);
$entries    = LakiHub_Time::get_all(['from' => $from, 'to' => $to]);
$contacts   = LakiHub_CRM::get_all();
$projects   = LakiHub_Projects::get_all();
$export_url = admin_url('admin-ajax.php') . '?action=laki_time_export&nonce=' . wp_create_nonce('laki_hub_nonce') . '&from=' . $from . '&to=' . $to;
?>
<div class="lh-wrap">
  <div class="lh-header">
    <div><h1>Timeføring</h1><div class="lh-subtitle" id="time-subtitle"><?= esc_html($period_label) ?></div></div>
    <button class="lh-btn lh-btn-primary" onclick="lhOpenModal('modal-time')">+ Logg manuelt</button>
  </div>

  <!-- AKTIV TIMER -->
  <div class="lh-card lh-timer-card">
    <div id="timer-idle">
      <p class="lh-timer-label">⏱ Start timer</p>
      <div class="lh-timer-start-row">
        <select id="timer-contact" class="lh-input">
          <option value="">— Klient —</option>
          <?php foreach ($contacts as $c): ?><option value="<?= $c['id'] ?>"><?= esc_html($c['name']) ?></option><?php endforeach; ?>
        </select>
        <select id="timer-project" class="lh-input">
          <option value="">— Prosjekt —</option>
          <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= esc_html($p['name']) ?></option><?php endforeach; ?>
        </select>
        <input type="text" id="timer-description" class="lh-input lh-input-grow" placeholder="Hva jobber du med?">
        <button class="lh-btn lh-btn-primary" id="btn-start-timer">&#9654; Start</button>
      </div>
    </div>
    <div id="timer-running" style="display:none">
      <div class="lh-timer-running-row">
        <span class="lh-timer-dot"></span>
        <span id="timer-running-label" class="lh-timer-running-label">Pågår…</span>
        <span id="timer-clock" class="lh-timer-clock">00:00:00</span>
        <button class="lh-btn lh-btn-danger" id="btn-stop-timer">&#9209; Stopp</button>
      </div>
    </div>
  </div>

  <!-- PERIODEFILTER -->
  <div class="lh-period-bar">
    <div class="lh-period-tabs">
      <button class="lh-period-tab active" data-period="month">Denne måneden</button>
      <button class="lh-period-tab" data-period="week">Denne uken</button>
      <button class="lh-period-tab" data-period="last_month">Forrige måned</button>
      <button class="lh-period-tab" data-period="year">I år</button>
    </div>
    <a href="<?= esc_url($export_url) ?>" id="time-export-btn" class="lh-btn lh-btn-secondary" target="_blank">&#8595; Timeliste (CSV)</a>
  </div>

  <!-- STATS -->
  <div class="lh-stats" id="time-stats-grid">
    <div class="lh-stat"><div class="lh-stat-label">Totalt</div><div class="lh-stat-value" id="time-stat-total"><?= number_format($stats['total_hours'],1,',','') ?> t</div></div>
    <div class="lh-stat"><div class="lh-stat-label">Fakturerbart</div><div class="lh-stat-value green" id="time-stat-bill"><?= number_format($stats['billable_hours'],1,',','') ?> t</div></div>
    <div class="lh-stat"><div class="lh-stat-label">Ikke-fakturerbart</div><div class="lh-stat-value" id="time-stat-nonbill"><?= number_format($stats['unbillable_hours'],1,',','') ?> t</div></div>
    <div class="lh-stat"><div class="lh-stat-label">Estimert verdi</div><div class="lh-stat-value green" id="time-stat-value"><?= number_format($stats['billable_value'],0,',',' ') ?> kr</div></div>
  </div>

  <!-- SAMMENDRAG: per klient + per prosjekt -->
  <div class="lh-dash-grid">
    <div class="lh-card">
      <div class="lh-card-head"><h2>Per klient</h2></div>
      <div class="lh-card-body" style="padding:0">
        <table class="lh-table">
          <thead><tr><th>Klient</th><th>Timer</th><th>Fakturerbart</th><th class="amount">Verdi</th></tr></thead>
          <tbody id="time-by-client-body">
            <?php foreach ($by_client as $r): ?>
            <tr>
              <td><?= esc_html($r['contact_name'] ?? '—') ?></td>
              <td><strong><?= number_format($r['total_hours'],1,',','') ?></strong> t</td>
              <td><?= number_format($r['billable_hours'],1,',','') ?> t</td>
              <td class="amount"><?= number_format($r['billable_value'],0,',',' ') ?> kr</td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$by_client): ?><tr><td colspan="4" style="text-align:center;color:var(--lh-muted)">Ingen data</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="lh-card">
      <div class="lh-card-head"><h2>Per prosjekt</h2></div>
      <div class="lh-card-body" style="padding:0">
        <table class="lh-table">
          <thead><tr><th>Prosjekt</th><th>Timer</th><th>Fakturerbart</th></tr></thead>
          <tbody id="time-by-project-body">
            <?php foreach ($by_project as $r): ?>
            <tr>
              <td><?= esc_html($r['project_name'] ?? '—') ?></td>
              <td><strong><?= number_format($r['total_hours'],1,',','') ?></strong> t</td>
              <td><?= number_format($r['billable_hours'],1,',','') ?> t</td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$by_project): ?><tr><td colspan="3" style="text-align:center;color:var(--lh-muted)">Ingen data</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ALLE OPPFØRINGER -->
  <div class="lh-card">
    <div class="lh-card-head"><h2>Alle timer</h2></div>
    <div class="lh-table-wrap">
      <table class="lh-table">
        <thead><tr><th>Dato</th><th>Klient</th><th>Prosjekt</th><th>Beskrivelse</th><th>Timer</th><th>Fakturerbart</th><th></th></tr></thead>
        <tbody id="time-entries-body">
          <?php foreach ($entries as $e): ?>
          <tr>
            <td style="white-space:nowrap"><?= date_i18n('d.m.Y', strtotime($e['date'])) ?></td>
            <td><?= esc_html($e['contact_name'] ?? '—') ?></td>
            <td><?= esc_html($e['project_name']  ?? '—') ?></td>
            <td><?= esc_html($e['description']   ?? '') ?></td>
            <td><strong><?= number_format($e['hours'],1,',','.') ?></strong> t</td>
            <td><?= $e['billable'] ? '<span class="lh-badge lh-badge-green">Ja</span>' : '<span class="lh-badge lh-badge-gray">Nei</span>' ?></td>
            <td class="actions">
              <button class="lh-btn lh-btn-secondary lh-btn-sm lh-edit-btn" data-modal="modal-time" data-record="<?= esc_attr(json_encode($e)) ?>">Rediger</button>
              <button class="lh-btn lh-btn-danger lh-btn-sm lh-delete-btn" data-action="laki_time_delete" data-id="<?= $e['id'] ?>">Slett</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$entries): ?><tr><td colspan="7" style="text-align:center;color:var(--lh-muted);padding:20px">Ingen timer registrert ennå.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="lh-modal-overlay" id="modal-time">
  <div class="lh-modal">
    <div class="lh-modal-head"><h3>Logg timer</h3><button class="lh-modal-close">x</button></div>
    <div class="lh-modal-body">
      <form class="lh-ajax-form">
        <input type="hidden" name="ajax_action" value="laki_time_save">
        <input type="hidden" name="id" value="">
        <div class="lh-form-grid">
          <div class="lh-form-row"><label>Dato *</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
          <div class="lh-form-row"><label>Timer *</label><input type="number" name="hours" step="0.25" min="0.25" max="24" placeholder="1.5" required></div>
        </div>
        <div class="lh-form-grid">
          <div class="lh-form-row">
            <label>Klient</label>
            <select name="contact_id">
              <option value="">— Velg —</option>
              <?php foreach ($contacts as $c): ?><option value="<?= $c['id'] ?>"><?= esc_html($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="lh-form-row">
            <label>Prosjekt</label>
            <select name="project_id">
              <option value="">— Velg —</option>
              <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= esc_html($p['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="lh-form-row"><label>Beskrivelse</label><input type="text" name="description" placeholder="Hva jobbet du med?"></div>
        <div class="lh-form-grid">
          <div class="lh-form-row"><label>Timepris (NOK)</label><input type="number" name="hourly_rate" step="50" placeholder="1500"></div>
          <div class="lh-form-row" style="display:flex;align-items:flex-end;padding-bottom:4px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="billable" checked style="width:auto"> Fakturerbart</label>
          </div>
        </div>
        <div class="lh-modal-foot" style="padding:0;margin-top:8px">
          <button type="submit" class="lh-btn lh-btn-primary">Lagre</button>
          <button type="button" class="lh-btn lh-btn-secondary lh-modal-close">Avbryt</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  var _iv=null, _startedAt=null;
  function fmtClock(s){return[Math.floor(s/3600),Math.floor((s%3600)/60),s%60].map(function(v){return String(v).padStart(2,'0')}).join(':');}
  function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
  function fmt(n){return parseFloat(n||0).toFixed(1).replace('.',',');}
  function nokFmt(n){return Math.round(n).toLocaleString('nb-NO');}

  function showRunning(label,startedAt){
    _startedAt=startedAt;
    document.getElementById('timer-idle').style.display='none';
    document.getElementById('timer-running').style.display='';
    document.getElementById('timer-running-label').textContent=label||'Pågår…';
    clearInterval(_iv);
    _iv=setInterval(function(){document.getElementById('timer-clock').textContent=fmtClock(Math.floor(Date.now()/1000)-_startedAt);},1000);
  }
  function showIdle(){clearInterval(_iv);document.getElementById('timer-idle').style.display='';document.getElementById('timer-running').style.display='none';}

  lhAjax('laki_time_active',{},function(res){if(res.success&&res.data){var t=res.data;showRunning([t.description,t.contact_name].filter(Boolean).join(' · '),t.started_at);}});

  document.getElementById('btn-start-timer').addEventListener('click',function(){
    var sel=document.getElementById('timer-contact');
    lhAjax('laki_time_start',{description:document.getElementById('timer-description').value.trim(),contact_id:sel.value,project_id:document.getElementById('timer-project').value},function(res){
      if(res.success){var lbl=[document.getElementById('timer-description').value,sel.options[sel.selectedIndex]&&sel.options[sel.selectedIndex].text!='— Klient —'?sel.options[sel.selectedIndex].text:''].filter(Boolean).join(' · ');showRunning(lbl,res.data.started_at);toast('Timer startet!','success');}
      else{toast((res.data&&res.data.message)||'Feil','error');}
    });
  });

  document.getElementById('btn-stop-timer').addEventListener('click',function(){
    lhAjax('laki_time_stop',{},function(res){
      if(res.success){showIdle();toast('Stoppet — '+res.data.hours+' timer logget.','success');loadPeriod(currentPeriod);}
      else{toast('Feil ved stopp','error');}
    });
  });

  var currentPeriod='month';
  function loadPeriod(p){
    lhAjax('laki_time_period_data',{period:p},function(res){
      if(!res.success)return;
      var d=res.data;
      document.getElementById('time-subtitle').textContent=d.label;
      var a=document.getElementById('time-export-btn');
      a.href=a.href.replace(/from=[^&]+/,'from='+d.from).replace(/to=[^&]+/,'to='+d.to);
      document.getElementById('time-stat-total').textContent=fmt(d.stats.total_hours)+' t';
      document.getElementById('time-stat-bill').textContent=fmt(d.stats.billable_hours)+' t';
      document.getElementById('time-stat-nonbill').textContent=fmt(d.stats.unbillable_hours)+' t';
      document.getElementById('time-stat-value').textContent=nokFmt(d.stats.billable_value)+' kr';
      document.getElementById('time-by-client-body').innerHTML=d.by_client.map(function(r){return'<tr><td>'+esc(r.contact_name||'—')+'</td><td><strong>'+fmt(r.total_hours)+'</strong> t</td><td>'+fmt(r.billable_hours)+' t</td><td class="amount">'+nokFmt(r.billable_value)+' kr</td></tr>';}).join('')||'<tr><td colspan="4" style="text-align:center;color:var(--lh-muted)">Ingen data</td></tr>';
      document.getElementById('time-by-project-body').innerHTML=d.by_project.map(function(r){return'<tr><td>'+esc(r.project_name||'—')+'</td><td><strong>'+fmt(r.total_hours)+'</strong> t</td><td>'+fmt(r.billable_hours)+' t</td></tr>';}).join('')||'<tr><td colspan="3" style="text-align:center;color:var(--lh-muted)">Ingen data</td></tr>';
      document.getElementById('time-entries-body').innerHTML=d.entries.map(function(e){var badge=e.billable==1?'<span class="lh-badge lh-badge-green">Ja</span>':'<span class="lh-badge lh-badge-gray">Nei</span>';return'<tr><td style="white-space:nowrap">'+esc(e.date)+'</td><td>'+esc(e.contact_name||'—')+'</td><td>'+esc(e.project_name||'—')+'</td><td>'+esc(e.description||'')+'</td><td><strong>'+fmt(e.hours)+'</strong> t</td><td>'+badge+'</td><td class="actions"><button class="lh-btn lh-btn-secondary lh-btn-sm lh-edit-btn" data-modal="modal-time" data-record=\''+JSON.stringify(e).replace(/\'/g,"&#39;")+'\'>Rediger</button><button class="lh-btn lh-btn-danger lh-btn-sm lh-delete-btn" data-action="laki_time_delete" data-id="'+e.id+'">Slett</button></td></tr>';}).join('')||'<tr><td colspan="7" style="text-align:center;color:var(--lh-muted);padding:20px">Ingen timer registrert.</td></tr>';
    });
  }

  document.querySelectorAll('.lh-period-tab').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('.lh-period-tab').forEach(function(x){x.classList.remove('active');});this.classList.add('active');currentPeriod=this.dataset.period;loadPeriod(currentPeriod);});});
})();
</script>
