<?php

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$config = array_merge(['password' => '', 'storage' => 'file', 'auth_secret' => ''], $config);

require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/geo.php';

$routeJs = isset($_GET['js']);
$routeApi = isset($_GET['api']);
$routeView = isset($_GET['view']);
$routeSetup = isset($_GET['setup']);

if ($routeApi) {
    handleApi();
} elseif ($routeView) {
    serveView();
} elseif ($routeSetup) {
    serveSetup();
} elseif ($routeJs) {
    serveJs();
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>local-stats</h1><p>Use <a href="?setup">?setup</a> to configure or <a href="?view">?view</a> to see stats.</p>';
}

function serveJs()
{
    header('Content-Type: application/javascript; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $self = $_SERVER['SCRIPT_NAME'];
    echo <<<JS
(function(){var i=null,s=Date.now(),c=0;
function t(){c++}
document.addEventListener('click',t);
document.addEventListener('keydown',t);
var b=document.currentScript&&document.currentScript.src?document.currentScript.src.split('?')[0]:'$self';
fetch(b+'?api&method=new',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({referrer:document.referrer,screen:screen.width+'x'+screen.height,lang:navigator.language,page:location.pathname})}).then(function(r){return r.json()}).then(function(d){i=d.id})['catch'](function(){});
function l(){if(!i)return;var e=((Date.now()-s)/1e3).toFixed(1);var d={id:i,duration:e,interactions:c};try{(navigator.sendBeacon||function(u,d){fetch(u,{method:'POST',body:d,keepalive:!0})})(b+'?api&method=update',JSON.stringify(d))}catch(e){}}
document.addEventListener('visibilitychange',function(){document.visibilityState==='hidden'&&l()});
window.addEventListener('beforeunload',l);})();
JS;
}

function handleApi()
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        return;
    }

    global $config;
    $method = $_GET['method'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($method === 'new') {
        $storage = createStorage($config);
        $data = [
            'lang' => $input['lang'] ?? $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'os' => detectOS($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referrer' => $input['referrer'] ?? '',
            'screen' => $input['screen'] ?? '',
            'page' => $input['page'] ?? '',
        ];
        $id = $storage->newVisit($data);
        echo json_encode(['id' => $id]);
        return;
    }

    if ($method === 'update') {
        $id = $input['id'] ?? $_GET['id'] ?? '';
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'missing id']);
            return;
        }
        $storage = createStorage($config);
        $data = [];
        if (isset($input['duration'])) $data['duration'] = (float)$input['duration'];
        if (isset($input['interactions'])) $data['interactions'] = (int)$input['interactions'];
        $ok = $storage->updateVisit($id, $data);
        echo json_encode(['ok' => $ok]);
        return;
    }

    http_response_code(400);
    echo json_encode(['error' => 'unknown method']);
}

function serveView()
{
    global $config;
    if (!checkAuth('view')) return;

    $storage = createStorage($config);
    $range = $_GET['range'] ?? 'day';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;

    $total = $storage->getVisitCount($range);
    $stats = $storage->getStats($range);
    $visits = $storage->getVisits($range, $page, $perPage);
    $totalPages = max(1, (int)ceil($total / $perPage));

    $chartData = buildChartData($storage, $range);

    $pwd = urlencode($_GET['pwd'] ?? '');
    $queryBase = "?view&range=$range";
    if ($pwd) $queryBase .= "&pwd=$pwd";

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stats - <?=htmlspecialchars($range)?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#f5f5f5;color:#333;padding:20px}
h1{margin-bottom:16px;font-size:1.5rem}
nav{margin-bottom:20px}
nav a{display:inline-block;padding:8px 16px;margin-right:4px;background:#fff;border:1px solid #ddd;text-decoration:none;color:#333;border-radius:4px;font-size:.9rem}
nav a.active{background:#0066cc;color:#fff;border-color:#0066cc}
.chart-wrap{background:#fff;border-radius:8px;padding:16px;margin-bottom:20px}
.summary{display:flex;gap:16px;margin-bottom:20px}
.summary-card{background:#fff;border-radius:8px;padding:20px;flex:1;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.summary-card .num{font-size:2rem;font-weight:700;color:#0066cc}
.summary-card .label{font-size:.85rem;color:#666;margin-top:4px}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #eee;font-size:.85rem}
th{background:#fafafa;font-weight:600;color:#555}
tr:hover{background:#f8f9ff}
.pagination{margin-top:16px;text-align:center}
.pagination a{display:inline-block;padding:6px 12px;margin:0 2px;background:#fff;border:1px solid #ddd;text-decoration:none;color:#333;border-radius:4px;font-size:.85rem}
.pagination a.active{background:#0066cc;color:#fff;border-color:#0066cc}
.pagination a.disabled{opacity:.4;pointer-events:none}
footer{margin-top:32px;font-size:.8rem;color:#999;text-align:center}
</style>
</head>
<body>

<h1>local-stats</h1>

<nav>
<?php foreach (['day','week','month','all'] as $r):
    $active = $r === $range ? ' active' : ''; ?>
<a href="?view&range=<?=$r?><?=$pwd?"&pwd=$pwd":''?>" class="<?=$active?>"><?=ucfirst($r)?></a>
<?php endforeach; ?>
</nav>

<div class="chart-wrap">
<canvas id="chart" height="250"></canvas>
</div>

<div class="summary">
<div class="summary-card"><div class="num"><?=$stats['total_visits']?></div><div class="label">Total Visits</div></div>
<div class="summary-card"><div class="num"><?=$stats['avg_duration']?>s</div><div class="label">Avg Duration</div></div>
</div>

<table>
<thead><tr>
<th>ID</th><th>Time</th><th>Duration</th><th>Interactions</th><th>Language</th><th>Location</th><th>OS</th><th>Page</th>
</tr></thead>
<tbody>
<?php foreach ($visits as $v):
    $time = isset($v['timestamp']) ? date('Y-m-d H:i', $v['timestamp']) : '-';
    $dur = isset($v['duration']) ? round($v['duration'], 1) . 's' : '-';
    $intr = $v['interactions'] ?? 0;
    $lang = htmlspecialchars($v['lang'] ?? '-');
    $loc = htmlspecialchars(geoLookup($v['ip'] ?? ''));
    $os = htmlspecialchars($v['os'] ?? '-');
    $page = htmlspecialchars($v['page'] ?? '-');
    $id = htmlspecialchars(substr($v['id'] ?? '', 0, 8));
?>
<tr><td title="<?=htmlspecialchars($v['id']??'')?>"><?=$id?></td><td><?=$time?></td><td><?=$dur?></td><td><?=$intr?></td><td><?=$lang?></td><td><?=$loc?></td><td><?=$os?></td><td><?=$page?></td></tr>
<?php endforeach; ?>
<?php if (empty($visits)): ?><tr><td colspan="8" style="text-align:center;color:#999;padding:32px">No visits yet</td></tr><?php endif; ?>
</tbody>
</table>

<div class="pagination">
<a href="<?=$queryBase?>&page=1" class="<?=$page<=1?'disabled':''?>">&laquo;</a>
<?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
<a href="<?=$queryBase?>&page=<?=$i?>" class="<?=$i===$page?'active':''?>"><?=$i?></a>
<?php endfor; ?>
<a href="<?=$queryBase?>&page=<?=$totalPages?>" class="<?=$page>=$totalPages?'disabled':''?>">&raquo;</a>
</div>

<footer>local-stats &mdash; self-hosted analytics</footer>

<script>
var chartData = <?=json_encode($chartData)?>;
var ctx = document.getElementById('chart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: chartData.labels,
        datasets: [{
            label: 'Visits',
            data: chartData.counts,
            backgroundColor: 'rgba(0,102,204,0.6)',
            borderColor: 'rgba(0,102,204,1)',
            borderWidth: 1,
            order: 1
        }, {
            label: 'Avg Duration (s)',
            data: chartData.durations,
            backgroundColor: 'rgba(255,159,64,0.6)',
            borderColor: 'rgba(255,159,64,1)',
            borderWidth: 1,
            order: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Visits' } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Seconds' } }
        }
    }
});
</script>

</body>
</html>
<?php
}

function buildChartData($storage, string $range): array
{
    $visits = $storage->getVisits($range, 1, 999999);

    if ($range === 'day') {
        $buckets = array_fill(0, 24, ['count' => 0, 'dur' => 0]);
        foreach ($visits as $v) {
            $h = (int)date('G', $v['timestamp']);
            $buckets[$h]['count']++;
            if (!empty($v['duration'])) $buckets[$h]['dur'] += (float)$v['duration'];
        }
        $labels = [];
        $counts = [];
        $durations = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $counts[] = $buckets[$h]['count'];
            $durations[] = $buckets[$h]['count'] > 0 ? round($buckets[$h]['dur'] / $buckets[$h]['count'], 1) : 0;
        }
        return ['labels' => $labels, 'counts' => $counts, 'durations' => $durations];
    }

    $buckets = [];
    foreach ($visits as $v) {
        $date = date('Y-m-d', $v['timestamp']);
        if (!isset($buckets[$date])) $buckets[$date] = ['count' => 0, 'dur' => 0];
        $buckets[$date]['count']++;
        if (!empty($v['duration'])) $buckets[$date]['dur'] += (float)$v['duration'];
    }

    if ($range === 'all') {
        $dates = array_keys($buckets);
        sort($dates);
        $labels = $counts = $durations = [];
        foreach ($dates as $date) {
            $labels[] = date('M j', strtotime($date));
            $b = $buckets[$date];
            $counts[] = $b['count'];
            $durations[] = $b['count'] > 0 ? round($b['dur'] / $b['count'], 1) : 0;
        }
        return ['labels' => $labels, 'counts' => $counts, 'durations' => $durations];
    }

    $end = new DateTime();
    $start = clone $end;
    $days = match ($range) {
        'week' => 6,
        'month' => 29,
    };
    $start->modify("-{$days} days");

    $labels = $counts = $durations = [];
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    foreach ($period as $d) {
        $date = $d->format('Y-m-d');
        $labels[] = $d->format('M j');
        $b = $buckets[$date] ?? ['count' => 0, 'dur' => 0];
        $counts[] = $b['count'];
        $durations[] = $b['count'] > 0 ? round($b['dur'] / $b['count'], 1) : 0;
    }
    $labels[] = $end->format('M j');
    $date = $end->format('Y-m-d');
    $b = $buckets[$date] ?? ['count' => 0, 'dur' => 0];
    $counts[] = $b['count'];
    $durations[] = $b['count'] > 0 ? round($b['dur'] / $b['count'], 1) : 0;

    return ['labels' => $labels, 'counts' => $counts, 'durations' => $durations];
}

function serveSetup()
{
    global $config, $configFile;
    if (!empty($config['password']) && !checkAuth('setup')) return;

    $message = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pwd = $_POST['password'] ?? '';
        $storage = $_POST['storage'] ?? 'file';
        $secret = $config['auth_secret'];

        if (!preg_match('/^(file|sqlite)$/', $storage)) {
            $error = 'Invalid storage type.';
        } else {
            $config['storage'] = $storage;
            if ($pwd !== '') {
                $config['password'] = password_hash($pwd, PASSWORD_BCRYPT);
            }
            if (empty($config['auth_secret'])) {
                $config['auth_secret'] = strtoupper(bin2hex(random_bytes(10)));
            }
            $written = file_put_contents(
                $configFile,
                '<?php' . "\n\nreturn " . var_export($config, true) . ";\n",
                LOCK_EX
            );
            if ($written === false) {
                $error = 'Failed to write config file. Check permissions.';
            } else {
                $message = 'Configuration saved.';
            }
        }
    }

    $hasPassword = $config['password'] !== '';
    $currentStorage = $config['storage'];
    $secret = $config['auth_secret'];

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup - local-stats</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#f5f5f5;color:#333;padding:40px;max-width:600px;margin:0 auto}
h1{margin-bottom:24px;font-size:1.5rem}
.card{background:#fff;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.form-group{margin-bottom:16px}
label{display:block;font-weight:600;margin-bottom:4px;font-size:.9rem}
select,input[type=password]{width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:1rem}
.btn{background:#0066cc;color:#fff;border:none;padding:12px 24px;border-radius:4px;font-size:1rem;cursor:pointer}
.btn:hover{background:#0052a3}
.msg{padding:12px;border-radius:4px;margin-bottom:16px;font-size:.9rem}
.msg.ok{background:#d4edda;color:#155724}
.msg.err{background:#f8d7da;color:#721c24}
.secret{font-family:monospace;background:#f0f0f0;padding:8px;border-radius:4px;word-break:break-all;margin:8px 0;font-size:.9rem}
footer{margin-top:32px;font-size:.8rem;color:#999;text-align:center}
a{color:#0066cc}
</style>
</head>
<body>

<h1>Setup</h1>

<div class="card">
<?php if ($message): ?><div class="msg ok"><?=htmlspecialchars($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="msg err"><?=htmlspecialchars($error)?></div><?php endif; ?>

<form method="post">
<div class="form-group">
<label for="storage">Storage backend</label>
<select name="storage" id="storage">
<option value="file" <?=$currentStorage==='file'?'selected':''?>>File (date.txt)</option>
<option value="sqlite" <?=$currentStorage==='sqlite'?'selected':''?>>SQLite</option>
</select>
</div>

<div class="form-group">
<label for="password"><?=$hasPassword?'New password (leave blank to keep current)':'Set access password'?></label>
<input type="password" name="password" id="password" placeholder="<?=$hasPassword?'Enter new password':'Enter password'?>">
</div>

<button type="submit" class="btn">Save</button>
</form>

<?php if ($secret): ?>
<hr style="margin:20px 0;border:none;border-top:1px solid #eee">
<h3 style="font-size:1rem;margin-bottom:8px">Auth Secret</h3>
<p style="font-size:.85rem;color:#666;margin-bottom:8px">Use this secret with authenticator apps (TOTP):</p>
<div class="secret"><?=htmlspecialchars($secret)?></div>
<?php
$issuer = rawurlencode('local-stats');
$label = rawurlencode('admin');
$s = rawurlencode($secret);
$otpauth = "otpauth://totp/$issuer:$label?secret=$s&issuer=$issuer";
?>
<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?=rawurlencode($otpauth)?>" alt="QR Code for authenticator app" style="max-width:200px;border-radius:4px">
<?php endif; ?>
</div>

<footer><a href="?view">View stats</a> &middot; local-stats</footer>

</body>
</html>
<?php
}

function checkAuth(string $page = 'view'): bool
{
    global $config;
    if (empty($config['password'])) return true;

    $pwd = $_GET['pwd'] ?? $_POST['pwd'] ?? '';
    if (password_verify($pwd, $config['password'])) return true;

    header('Content-Type: text/html; charset=utf-8');
    http_response_code(401);
    ?>
<!DOCTYPE html>
<html><head><title>Auth required</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background:#f5f5f5}
form{background:#fff;padding:32px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
input{padding:10px;border:1px solid #ddd;border-radius:4px;font-size:1rem;width:100%;margin-bottom:12px}
button{padding:10px 20px;background:#0066cc;color:#fff;border:none;border-radius:4px;cursor:pointer}
h2{margin-bottom:16px;font-size:1.2rem}</style></head><body>
<form method="get">
<h2>Password required</h2>
<input type="hidden" name="<?=htmlspecialchars($page)?>" value="">
<input type="password" name="pwd" placeholder="Enter password" autofocus>
<button type="submit">Submit</button>
</form>
</body></html>
<?php
    return false;
}