<?php

$configFile = __DIR__ . '/config.php';
$configExample = __DIR__ . '/config.example.php';
if (!file_exists($configFile) && file_exists($configExample)) {
    copy($configExample, $configFile);
}
$config = file_exists($configFile) ? require $configFile : [];
$config = array_merge([
    'password' => '', 'storage' => 'file', 'auth_secret' => '',
    'store_subnet' => false, 'geo_lookup' => false,
    'collect_referrer' => true, 'collect_lang' => true, 'collect_page' => true,
    'retention_days' => 0,
], $config);

$routeJs = isset($_GET['js']);
$routeApi = isset($_GET['api']);
$routeView = isset($_GET['view']);
$routeSettings = isset($_GET['settings']);
$routeLogout = isset($_GET['logout']);
$routeTest = isset($_GET['test']);

if ($routeLogout) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    session_destroy();
    header('Location: ?view');
    return;
}

if ($routeJs) {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $self = $_SERVER['SCRIPT_NAME'];
    $collect = json_encode([
        'referrer' => (bool)$config['collect_referrer'],
        'lang' => (bool)$config['collect_lang'],
        'page' => (bool)$config['collect_page'],
    ]);
    echo <<<JS
(function(){let id=null,start=Date.now(),ints=0,collect=$collect,base,lastScroll=0;
function inc(){ints++}
document.addEventListener('click',inc);
document.addEventListener('keydown',inc);
document.addEventListener('scroll',function(){let n=Date.now();if(n-lastScroll>300){ints++;lastScroll=n}});
base=document.currentScript&&document.currentScript.src?document.currentScript.src.split('?')[0]:'$self';
let data={};
if(collect.referrer)data.referrer=document.referrer;
if(collect.lang)data.lang=navigator.language;
if(collect.page)data.page=location.pathname;
function api(m,d){return fetch(base+'?api='+m,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)}).then(function(r){return r.json()})}
api('new',data).then(function(d){id=d.id})['catch'](function(){});
function send(){if(!id||send.s)return;send.s=1;let sec=((Date.now()-start)/1e3).toFixed(1);try{(navigator.sendBeacon||function(url,body){fetch(url,{method:'POST',body:body,keepalive:!0})})(base+'?api=update',JSON.stringify({id:id,duration:sec,interactions:ints}))}catch(e){}}
document.addEventListener('visibilitychange',function(){document.visibilityState==='hidden'?send():send.s=0});
window.addEventListener('beforeunload',send);})();
JS;
    return;
}

if ($routeApi) {
    require_once __DIR__ . '/lib/storage.php';
    require_once __DIR__ . '/lib/geo.php';

    function getClientIp(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    function checkRateLimit(string $ip): bool {
        $file = __DIR__ . '/data/ratelimit/' . md5($ip) . '_' . date('YmdH') . '.txt';
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0777, true);
        $count = (int)@file_get_contents($file);
        if ($count >= 120) return false;
        file_put_contents($file, $count + 1, LOCK_EX);
        return true;
    }

    $ip = getClientIp();
    if (!checkRateLimit($ip)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(429);
        echo json_encode(['error' => 'too many requests']);
        return;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        return;
    }
    $method = $_GET['api'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($method === 'new') {
        $storage = createStorage($config);
        $data = ['os' => detectOS($_SERVER['HTTP_USER_AGENT'] ?? '')];
        if ($config['collect_lang']) $data['lang'] = $input['lang'] ?? '';
        if ($config['collect_referrer']) $data['referrer'] = $input['referrer'] ?? '';
        if ($config['collect_page']) $data['page'] = $input['page'] ?? '';
        if ($config['store_subnet']) $data['ip'] = subnetAddress($ip);
        if ($config['geo_lookup']) $data['geo'] = geoLookup($ip);
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
        echo json_encode(['ok' => $storage->updateVisit($id, $data)]);
        return;
    }
    http_response_code(400);
    echo json_encode(['error' => 'unknown method']);
    return;
}

if ($routeView) {
    require_once __DIR__ . '/lib/storage.php';
    require_once __DIR__ . '/lib/auth.php';
    require_once __DIR__ . '/lib/view.php';
    serveView();
    return;
}

if ($routeSettings) {
    require_once __DIR__ . '/lib/auth.php';
    require_once __DIR__ . '/lib/settings.php';
    serveSettings();
    return;
}

if ($routeTest) {
    require_once __DIR__ . '/lib/test.php';
    serveTest();
    return;
}

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/lib/common.php';
renderHead('');
?>
<div class="landing">
<h1>Butler</h1>
<p class="tagline">your privacy-first analytics</p>
<div class="landing-actions">
<a href="?settings" class="btn">Get started</a>
<a href="?view" class="btn-outline">View dashboard</a>
</div>
</div>
<?php
renderFooter();
