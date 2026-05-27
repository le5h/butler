<?php
define('BUTLER_APP', true);

$configFile = __DIR__ . '/config.php';
$configExample = __DIR__ . '/config.example.php';

if (!file_exists($configFile) && file_exists($configExample)) {
    copy($configExample, $configFile);
}

$config = file_exists($configFile) ? require $configFile : [];
$config = array_merge([
    'password' => '',
    'storage' => 'file',
    'auth_secret' => '',
    'store_subnet' => false,
    'geo_lookup' => false,
    'collect_referrer' => true,
    'collect_lang' => true,
    'collect_page' => true,
    'collect_timezone' => false,
    'collect_os' => true,
    'retention_days' => 365,
    'rate_limit' => 120,
    'quality_min_duration' => 10,
    'quality_min_interactions' => 1,
    'export_limit' => 10000,
], $config);

function route($path): bool {
    return isset($_GET[$path]);
}

if (route('logout')) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    session_destroy();
    header('Location: ?view');
    return;
}

if (route('js')) {
    $self = $_SERVER['SCRIPT_NAME'];
    $configToCode = [
        'collect_referrer' => "referrer:document.referrer",
        'collect_lang' => "lang:navigator.language",
        'collect_page' => "page:location.pathname",
        'collect_timezone' => "timezone:Intl.DateTimeFormat().resolvedOptions().timeZone",
    ];
    $entries = [];
    foreach ($configToCode as $key => $code) {
        if (!empty($config[$key])) $entries[] = $code;
    }
    $dataObj = '{' . implode(',', $entries) . '}';
    header('Content-Type: application/javascript; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo <<<JS
window.__butler={id:null,ints:0};
(function(){
var b=window.__butler;b.start=Date.now(),b.lastScroll=0;
function inc(){b.ints++}
document.addEventListener('click',inc);document.addEventListener('keydown',inc);
document.addEventListener('scroll',function(){let n=Date.now();n-b.lastScroll>300&&(b.ints++,b.lastScroll=n)},{passive:true});
b.base=document.currentScript?.src?.split('?')[0]||'$self';
b.data=$dataObj;
var api=(m,d)=>fetch(b.base+'?api='+m,{method:'POST',body:JSON.stringify(d),headers:{'Content-Type':'application/json'},keepalive:1}).then(r=>r.json());
api('new',b.data).then(d=>b.id=d.id).catch(()=>b.err='new failed');
function send(){if(!b.id||send.s)return;send.s=1;let sec=((Date.now()-b.start)/1e3).toFixed(1),data={id:b.id,duration:sec,interactions:b.ints};
api('update',data).catch(function(){})}
document.addEventListener('visibilitychange',function(){document.hidden?send():send.s=0},{passive:true});
window.addEventListener('pagehide',send,{passive:true});
})();
JS;
    return;
}

if (route('api')) {
    require_once __DIR__ . '/lib/storage.php';
    require_once __DIR__ . '/lib/geo.php';
    require_once __DIR__ . '/lib/ratelimit.php';

    function getClientIp(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    $ip = getClientIp();
    if (!checkRateLimit($ip, $config['rate_limit'] ?? 120)) {
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
        $data = $config['collect_os'] ? ['os' => detectOS($_SERVER['HTTP_USER_AGENT'] ?? '')] : [];
        if ($config['collect_lang']) $data['lang'] = $input['lang'] ?? '';
        if ($config['collect_referrer']) $data['referrer'] = $input['referrer'] ?? '';
        if ($config['collect_page']) {
            $raw = $input['page'] ?? '';
            $raw = explode('?', $raw)[0];
            $raw = explode('#', $raw)[0];
            $data['page'] = substr(strip_tags($raw), 0, 500);
        }
        if ($config['collect_timezone']) $data['timezone'] = $input['timezone'] ?? '';
        if ($config['store_subnet']) $data['ip'] = subnetAddress($ip);
        if ($config['geo_lookup']) $data['geo'] = geoLookup($ip);
        $id = $storage->newVisit($data);
        echo json_encode(['id' => $id]);
        return;
    }
    if ($method === 'update') {
        $id = $input['id'] ?? '';
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

if (route('settings')) {
    require_once __DIR__ . '/lib/auth.php';
    require_once __DIR__ . '/lib/settings.php';
    serveSettings();
    return;
}

if (route('view')) {
    require_once __DIR__ . '/lib/storage.php';
    require_once __DIR__ . '/lib/auth.php';
    require_once __DIR__ . '/lib/view.php';
    serveView();
    return;
}

if (route('test')) {
    require_once __DIR__ . '/lib/test.php';
    serveTest();
    return;
}

if($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/lib/common.php';
    renderHead('Intro');
    require __DIR__ . '/lib/view/landing.php';
    renderFooter();
}
