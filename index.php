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
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        return;
    }
    $method = $_GET['method'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($method === 'new') {
        $storage = createStorage($config);
        $id = $storage->newVisit([
            'lang' => $input['lang'] ?? $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'os' => detectOS($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referrer' => $input['referrer'] ?? '',
            'screen' => $input['screen'] ?? '',
            'page' => $input['page'] ?? '',
        ]);
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
    return;
}

header('Content-Type: text/html; charset=utf-8');
echo '<h1>local-stats</h1><p>Use <a href="?setup">?setup</a> to configure or <a href="?view">?view</a> to see stats.</p>';
