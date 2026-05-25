<?php

function serveSettings()
{
    global $config, $configFile;
    if (!empty($config['password']) && !checkAuth('settings')) return;

    $message = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pwd = $_POST['password'] ?? '';
        $storage = $_POST['storage'] ?? 'file';

        if (!preg_match('/^(file|sqlite)$/', $storage)) {
            $error = 'Invalid storage type.';
        } else {
            $config['storage'] = $storage;
            $config['store_ip'] = !empty($_POST['store_ip']);
            $config['geo_lookup'] = !empty($_POST['geo_lookup']);
            $config['collect_referrer'] = !empty($_POST['collect_referrer']);
            $config['collect_lang'] = !empty($_POST['collect_lang']);
            $config['collect_page'] = !empty($_POST['collect_page']);
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
    $storeIp = !empty($config['store_ip']);
    $geoLookup = !empty($config['geo_lookup']);
    $collectReferrer = !empty($config['collect_referrer']);
    $collectLang = !empty($config['collect_lang']);
    $collectPage = !empty($config['collect_page']);
    $secret = $config['auth_secret'];

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings - local-stats</title>
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

<h1>Settings</h1>

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

<div class="form-group" style="margin-top:4px">
<label style="font-weight:400;font-size:.85rem"><input type="checkbox" name="collect_page" value="1" <?=$collectPage?'checked':''?>> Collect page URL</label>
</div>
<div class="form-group" style="margin-top:4px">
<label style="font-weight:400;font-size:.85rem"><input type="checkbox" name="collect_referrer" value="1" <?=$collectReferrer?'checked':''?>> Collect referrer URL</label>
</div>
<div class="form-group" style="margin-top:4px">
<label style="font-weight:400;font-size:.85rem"><input type="checkbox" name="collect_lang" value="1" <?=$collectLang?'checked':''?>> Collect browser language</label>
</div>

<hr style="margin:16px 0;border:none;border-top:1px solid #eee">

<div class="form-group">
<label><input type="checkbox" name="store_ip" value="1" <?=$storeIp?'checked':''?>> Store visitor IP address</label>
</div>

<div class="form-group">
<label><input type="checkbox" name="geo_lookup" value="1" <?=$geoLookup?'checked':''?>> Look up geo location from IP (requires IP storage)</label>
</div>

<div class="form-group">
<label for="password"><?=$hasPassword?'New password (leave blank to keep current)':'Set access password'?></label>
<input type="password" name="password" id="password" placeholder="<?=$hasPassword?'Enter new password':'Enter password'?>">
</div>

<button type="submit" class="btn">Save</button>
</form>

<?php if ($secret): ?>
<hr style="margin:20px 0;border:none;border-top:1px solid #eee">
<h3 style="font-size:1rem;margin-bottom:8px">Authenticator App (TOTP)</h3>
<p style="font-size:.85rem;color:#666;margin-bottom:8px">Secret key — enter this manually in your authenticator app:</p>
<div class="secret" style="user-select:all"><?=htmlspecialchars($secret)?></div>
<?php
$issuer = rawurlencode('local-stats');
$label = rawurlencode('admin');
$s = rawurlencode($secret);
$otpauth = "otpauth://totp/$issuer:$label?secret=$s&issuer=$issuer";
?>
<p style="font-size:.85rem;color:#666;margin-top:12px">Or use this URI with any QR generator you trust:</p>
<div class="secret" style="font-size:.8rem;word-break:break-all;user-select:all"><?=htmlspecialchars($otpauth)?></div>
<?php endif; ?>
</div>

<footer><a href="?view">View stats</a> &middot; local-stats</footer>

</body>
</html>
<?php
}
