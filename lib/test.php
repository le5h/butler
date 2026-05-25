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
  <div class="test-row"><span class="test-label">Auto-update</span><span class="test-val" id="s-auto">on tab hide</span></div>
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
  const base = '<?=$scriptName?>';
  const start = Date.now();
  let clicks = 0, visitId = null;
  const el = {
    tracker: document.getElementById('s-tracker'),
    id: document.getElementById('s-id'),
    clicks: document.getElementById('s-clicks'),
    elapsed: document.getElementById('s-elapsed'),
    auto: document.getElementById('s-auto'),
    update: document.getElementById('s-update'),
    response: document.getElementById('s-response'),
  };

  el.tracker.textContent = 'loaded';
  el.tracker.className = 'test-val test-ok';

  document.addEventListener('click', function(){ clicks++; tick(); });
  document.addEventListener('keydown', function(){ clicks++; tick(); });
  let lastScroll=0; document.addEventListener('scroll', function(){ const n=Date.now(); if (n-lastScroll>300) { clicks++; lastScroll=n; tick(); } });

  function tick() {
    el.clicks.textContent = clicks;
    el.elapsed.textContent = ((Date.now() - start) / 1e3).toFixed(1) + 's';
  }

  setInterval(tick, 1000);

  const flags = <?=$collectFlags?>;
  const data = {};
  if (flags.referrer) data.referrer = document.referrer;
  if (flags.lang) data.lang = navigator.language;
  if (flags.page) data.page = location.pathname;

  function api(method, body) {
    return fetch(base + '?api=' + method, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function(r){ return r.json() });
  }

  api('new', data).then(function(d){
    visitId = d.id;
    el.id.textContent = d.id;
    el.update.textContent = 'ready';
    el.update.className = 'test-val test-ok';
  })
  ['catch'](function(){
    el.tracker.textContent = 'new failed';
    el.tracker.className = 'test-val test-err';
  });

  function send() {
    if (!visitId || send.s) return; send.s = 1;
    const sec = ((Date.now() - start) / 1e3).toFixed(1);
    api('update', { id: visitId, duration: sec, interactions: clicks });
  }

  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'hidden') send(); else send.s = 0;
  });
  window.addEventListener('beforeunload', send);

  document.getElementById('btn-update').addEventListener('click', function(){
    if (!visitId) {
      el.update.textContent = 'no visit ID yet';
      el.update.className = 'test-val test-err';
      return;
    }

    const sec = ((Date.now() - start) / 1e3).toFixed(1);

    el.update.textContent = 'sending...';
    el.update.className = 'test-val';

    api('update', { id: visitId, duration: sec, interactions: clicks })
    .then(function(d){
      el.update.textContent = d.ok ? 'sent OK' : 'failed';
      el.update.className = 'test-val ' + (d.ok ? 'test-ok' : 'test-err');
      el.response.textContent = JSON.stringify(d, null, 2);
    })
    ['catch'](function(err){
      el.update.textContent = 'error';
      el.update.className = 'test-val test-err';
      el.response.textContent = String(err);
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