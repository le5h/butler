<?php

function renderTestPage(string $scriptName, string $collectFlags): void {
?>
<div class="container-narrow">

<div class="card">
<h4 class="section-heading">Live status</h4>
<div id="status" class="test-status">
  <div class="test-row"><span class="test-label">Tracker</span><span class="test-val" id="s-tracker">waiting...</span></div>
  <div class="test-row"><span class="test-label">Visit ID</span><span class="test-val mono" id="s-id">—</span></div>
  <div class="test-row"><span class="test-label">Interactions</span><span class="test-val" id="s-clicks">0</span></div>
  <div class="test-row"><span class="test-label">Elapsed</span><span class="test-val" id="s-elapsed">0s</span></div>
  <div class="test-row"><span class="test-label">Auto-update</span><span class="test-val" id="s-auto">on leave/close</span></div>
  <div class="test-row"><span class="test-label">Manual update</span><span class="test-val" id="s-update">not sent</span></div>
</div>
</div>

<div class="card">
<h4 class="section-heading">Test actions</h4>
<p class="text-muted mb-8">Use these to generate interactions. All clicks and keypresses are counted.</p>
<div class="test-actions">
<button class="btn" id="btn-1">Click me</button>
<button class="btn" id="btn-2">Also click me</button>
<button class="btn btn-danger" id="btn-3">Don't click me</button>
</div>
<div class="form-group mt-16">
<label for="test-input">Type something here</label>
<input type="text" id="test-input" placeholder="Typing generates keydown events...">
</div>
</div>

<div class="card">
<h4 class="section-heading">Send update now</h4>
<p class="text-muted mb-8">Manually send the update to verify the API. Uses the real visit ID and counts.</p>
<button class="btn" id="btn-update">Send update now</button>
<pre id="s-response" class="test-response"></pre>
</div>

<div class="card">
<h4 class="section-heading">Verify</h4>
<p class="text-muted mb-8">Check the dashboard to see your recorded visit:</p>
<a href="?view&range=day" class="btn">Open dashboard &rarr;</a>
</div>

</div>

<script>
(function(){
  var base = '<?=$scriptName?>';
  var start = Date.now(), clicks = 0, visitId = null;
  var elTracker = document.getElementById('s-tracker');
  var elId = document.getElementById('s-id');
  var elClicks = document.getElementById('s-clicks');
  var elElapsed = document.getElementById('s-elapsed');
  var elAuto = document.getElementById('s-auto');
  var elUpdate = document.getElementById('s-update');
  var elResponse = document.getElementById('s-response');

  elTracker.textContent = 'loaded';
  elTracker.className = 'test-val test-ok';

  document.addEventListener('click', function(){ clicks++; tick(); });
  document.addEventListener('keydown', function(){ clicks++; tick(); });
  var st=0; document.addEventListener('scroll', function(){ var n=Date.now(); if (n-st>300) { clicks++; st=n; tick(); } });

  function tick() {
    elClicks.textContent = clicks;
    elElapsed.textContent = ((Date.now() - start) / 1e3).toFixed(1) + 's';
  }

  setInterval(tick, 1000);

  var flags = <?=$collectFlags?>;
  var d = {};
  if (flags.referrer) d.referrer = document.referrer;
  if (flags.lang) d.lang = navigator.language;
  if (flags.page) d.page = location.pathname;

  fetch(base + '?api=new', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(d)
  })
  .then(function(r){ return r.json() })
  .then(function(data){
    visitId = data.id;
    elId.textContent = data.id;
    elUpdate.textContent = 'ready';
    elUpdate.className = 'test-val test-ok';
  })
  ['catch'](function(err){
    elTracker.textContent = 'new failed';
    elTracker.className = 'test-val test-err';
  });

  function send() {
    if (!visitId) return;
    var duration = ((Date.now() - start) / 1e3).toFixed(1);
    fetch(base + '?api=update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: visitId, duration: duration, interactions: clicks })
    });
  }

  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'hidden') send();
  });
  window.addEventListener('beforeunload', send);

  document.getElementById('btn-update').addEventListener('click', function(){
    if (!visitId) {
      elUpdate.textContent = 'no visit ID yet';
      elUpdate.className = 'test-val test-err';
      return;
    }

    var duration = ((Date.now() - start) / 1e3).toFixed(1);
    var body = JSON.stringify({ id: visitId, duration: duration, interactions: clicks });

    elUpdate.textContent = 'sending...';
    elUpdate.className = 'test-val';

    fetch(base + '?api=update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: body
    })
    .then(function(r){ return r.json() })
    .then(function(d){
      elUpdate.textContent = d.ok ? 'sent OK' : 'failed';
      elUpdate.className = 'test-val ' + (d.ok ? 'test-ok' : 'test-err');
      elResponse.textContent = JSON.stringify(d, null, 2);
    })
    ['catch'](function(err){
      elUpdate.textContent = 'error';
      elUpdate.className = 'test-val test-err';
      elResponse.textContent = String(err);
    });
  });
})();
</script>
<?php
}

function serveTest() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $collectFlags = json_encode([
        'referrer' => (bool)$GLOBALS['config']['collect_referrer'],
        'lang' => (bool)$GLOBALS['config']['collect_lang'],
        'page' => (bool)$GLOBALS['config']['collect_page'],
    ]);
    require_once __DIR__ . '/common.php';
    header('Content-Type: text/html; charset=utf-8');
    renderHead('Test');
    renderTop('test');
    renderTestPage($scriptName, $collectFlags);
    renderFooter();
}