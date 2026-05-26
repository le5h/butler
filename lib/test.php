<?php

function renderTestPage(): void {
?>
<div class="container-narrow">

<div class="card">
<h4 class="section-heading">Live status</h4>
<div id="status" class="test-status">
  <div class="test-row"><span class="test-label">Tracker</span><span class="test-val test-ok" id="s-tracker">loaded</span></div>
  <div class="test-row"><span class="test-label">Visit ID</span><span class="test-val mono" id="s-id">waiting...</span></div>
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

<script src="?js"></script>
<script>
(function(){var s=document.getElementById('s-id'),c=document.getElementById('s-clicks'),e=document.getElementById('s-elapsed'),u=document.getElementById('s-update'),r=document.getElementById('s-response'),start=Date.now();
function tick(){var b=window.__butler;if(b){if(b.id){s.textContent=b.id;u.textContent='ready'}c.textContent=b.ints}e.textContent=((Date.now()-start)/1e3).toFixed(1)+'s'}
setInterval(tick,200);
document.getElementById('btn-update').addEventListener('click',function(){var b=window.__butler;if(!b||!b.id){u.textContent='no ID yet';return}
u.textContent='sending...';fetch('<?=$_SERVER['SCRIPT_NAME']?>?api=update',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:b.id,duration:((Date.now()-start)/1e3).toFixed(1),interactions:b.ints})}).then(function(r){return r.json()}).then(function(d){u.textContent=d.ok?'sent OK':'failed';r.textContent=JSON.stringify(d,null,2)}).catch(function(err){u.textContent='error';r.textContent=String(err)})})})();</script>
<?php
}

function serveTest() {
    require_once __DIR__ . '/common.php';
    header('Content-Type: text/html; charset=utf-8');
    renderHead('Test');
    renderTop('test');
    renderTestPage();
    renderFooter();
}
