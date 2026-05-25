<?php

$configFile = __DIR__ . '/config.php';
$configExample = __DIR__ . '/config.example.php';
if (!file_exists($configFile) && file_exists($configExample)) {
    copy($configExample, $configFile);
}
$config = file_exists($configFile) ? require $configFile : [];
$config = array_merge([
    'password' => '', 'storage' => 'file', 'auth_secret' => '',
    'store_ip' => false, 'geo_lookup' => false,
    'collect_referrer' => true, 'collect_lang' => true, 'collect_page' => true,
    'retention_days' => 0,
], $config);

require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/geo.php';

$routeJs = isset($_GET['js']);
$routeApi = isset($_GET['api']);
$routeView = isset($_GET['view']);
$routeSettings = isset($_GET['settings']);
$routeLogout = isset($_GET['logout']);

function getClientIp(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function checkRateLimit(string $ip): bool
{
    $dir = __DIR__ . '/data';
    $rateLimitDir = $dir . '/ratelimit';
    if (!is_dir($rateLimitDir)) {
        @mkdir($rateLimitDir, 0777, true);
    }

    $hash = md5($ip);
    $hour = date('YmdH');
    $file = $rateLimitDir . '/' . $hash . '_' . $hour . '.txt';
    $maxRequests = 120;

    $count = file_exists($file) ? (int)file_get_contents($file) : 0;
    if ($count >= $maxRequests) return false;

    file_put_contents($file, $count + 1, LOCK_EX);

    if (mt_rand(1, 10) === 1) {
        $cutoff = time() - 86400;
        $oldFiles = glob($rateLimitDir . '/*.txt');
        foreach ($oldFiles as $of) {
            $mtime = filemtime($of);
            if ($mtime !== false && $mtime < $cutoff) {
                @unlink($of);
            }
        }
    }

    return true;
}

if ($routeLogout) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    session_destroy();
    header('Location: ?view');
    return;
}

if ($routeApi) {
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
    $method = $_GET['method'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($method === 'new') {
        $storage = createStorage($config);
        $data = [
            'os' => detectOS($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];
        if ($config['collect_lang']) $data['lang'] = $input['lang'] ?? '';
        if ($config['collect_referrer']) $data['referrer'] = $input['referrer'] ?? '';
        if ($config['collect_page']) $data['page'] = $input['page'] ?? '';
        if ($config['store_ip']) {
            $data['ip'] = $ip;
            if ($config['geo_lookup'] && $ip) {
                $data['geo'] = geoLookup($ip);
            }
        }
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
    if ($config['retention_days'] > 0) {
        $storage = createStorage($config);
        $storage->cleanup($config['retention_days']);
    }
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
(function(){var i=null,s=Date.now(),c=0,S=$collect;
function t(){c++}
document.addEventListener('click',t);
document.addEventListener('keydown',t);
var b=document.currentScript&&document.currentScript.src?document.currentScript.src.split('?')[0]:'$self';
var d={};S.referrer&&(d.referrer=document.referrer);S.lang&&(d.lang=navigator.language);S.page&&(d.page=location.pathname);
fetch(b+'?api&method=new',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)}).then(function(r){return r.json()}).then(function(d){i=d.id})['catch'](function(){});
function l(){if(!i)return;var e=((Date.now()-s)/1e3).toFixed(1);var d={id:i,duration:e,interactions:c};try{(navigator.sendBeacon||function(u,d){fetch(u,{method:'POST',body:d,keepalive:!0})})(b+'?api&method=update',JSON.stringify(d))}catch(e){}}
document.addEventListener('visibilitychange',function(){document.visibilityState==='hidden'&&l()});
window.addEventListener('beforeunload',l);})();
JS;
    return;
}

header('Content-Type: text/html; charset=utf-8');
echo '<h1>local-stats</h1><p>Use <a href="?settings">?settings</a> to configure or <a href="?view">?view</a> to see stats.</p>';
