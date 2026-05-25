<?php

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$config = array_merge([
    'password' => '', 'storage' => 'file', 'auth_secret' => '',
    'store_ip' => false, 'geo_lookup' => false,
    'collect_referrer' => true, 'collect_lang' => true, 'collect_page' => true,
], $config);

require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/geo.php';

$routeJs = isset($_GET['js']);
$routeApi = isset($_GET['api']);
$routeView = isset($_GET['view']);
$routeSetup = isset($_GET['setup']);
$routeSettings = isset($_GET['settings']);

if ($routeSettings) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode([
        'collect' => [
            'referrer' => (bool)$config['collect_referrer'],
            'lang' => (bool)$config['collect_lang'],
            'page' => (bool)$config['collect_page'],
        ]
    ]);
    return;
}

if ($routeApi) {
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
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
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
    require_once __DIR__ . '/lib/auth.php';
    require_once __DIR__ . '/lib/view.php';
    serveView();
    return;
}

if ($routeSetup) {
    require_once __DIR__ . '/lib/auth.php';
    require_once __DIR__ . '/lib/setup.php';
    serveSetup();
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
echo '<h1>local-stats</h1><p>Use <a href="?setup">?setup</a> to configure or <a href="?view">?view</a> to see stats.</p>';
