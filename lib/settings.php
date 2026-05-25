<?php

function serveSettings()
{
    global $config, $configFile;
    if (!empty($config['password']) && !checkAuth('settings')) return;

    $message = '';
    $error = '';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['settings_csrf'])) {
        $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['settings_csrf'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['_csrf'] ?? '';
        if (!hash_equals($csrfToken, $submittedToken)) {
            $error = 'Invalid CSRF token. Please try again.';
        } else {
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
                $retention = (int)($_POST['retention_days'] ?? 0);
                $config['retention_days'] = max(0, $retention);
                if ($pwd !== '') {
                    $config['password'] = password_hash($pwd, PASSWORD_BCRYPT);
                }
                if (empty($config['auth_secret'])) {
                    $config['auth_secret'] = base32_encode(random_bytes(20));
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
                    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
                    $csrfToken = $_SESSION['settings_csrf'];
                }
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
    $retentionDays = (int)($config['retention_days'] ?? 0);
    $secret = $config['auth_secret'];

    require_once __DIR__ . '/common.php';
    header('Content-Type: text/html; charset=utf-8');
    renderHead('Settings');
    renderTop('settings');
    ?>

<div class="container-narrow">

<?php if ($message): ?><div class="msg ok"><?=htmlspecialchars($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="msg err"><?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="card">
<form method="post">
<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">

<div class="form-group">
<label for="storage">Storage backend</label>
<select name="storage" id="storage">
<option value="file" <?=$currentStorage==='file'?'selected':''?>>File (date.txt)</option>
<option value="sqlite" <?=$currentStorage==='sqlite'?'selected':''?>>SQLite</option>
</select>
</div>

<div class="form-group form-group-inline mt-4">
<label><input type="checkbox" name="collect_page" value="1" <?=$collectPage?'checked':''?>> Collect page URL</label>
</div>
<div class="form-group form-group-inline mt-4">
<label><input type="checkbox" name="collect_referrer" value="1" <?=$collectReferrer?'checked':''?>> Collect referrer URL</label>
</div>
<div class="form-group form-group-inline mt-4">
<label><input type="checkbox" name="collect_lang" value="1" <?=$collectLang?'checked':''?>> Collect browser language</label>
</div>

<hr>

<div class="form-group">
<label><input type="checkbox" name="store_ip" value="1" <?=$storeIp?'checked':''?>> Store visitor IP address</label>
</div>

<div class="form-group">
<label><input type="checkbox" name="geo_lookup" value="1" <?=$geoLookup?'checked':''?>> Look up geo location from IP (requires IP storage)</label>
</div>

<hr>

<div class="form-group">
<label for="retention_days">Auto-cleanup (days, 0 = never)</label>
<input type="number" name="retention_days" id="retention_days" value="<?=$retentionDays?>" min="0" max="3650">
</div>

<hr>

<div class="form-group">
<label for="password"><?=$hasPassword?'New password (leave blank to keep current)':'Set access password'?></label>
<input type="password" name="password" id="password" placeholder="<?=$hasPassword?'Enter new password':'Enter password'?>">
</div>

<button type="submit" class="btn">Save</button>
</form>

<?php if ($secret): ?>
<hr class="hr-text">
<h3 style="font-size:1rem" class="mb-8">Authenticator App (TOTP)</h3>
<p class="text-muted mb-8">Secret key — enter this manually in your authenticator app (e.g. Google Authenticator, Authy):</p>
<div class="secret select-all"><?=htmlspecialchars($secret)?></div>
<?php
$issuer = rawurlencode('local-stats');
$label = rawurlencode('admin');
$s = rawurlencode($secret);
$otpauth = "otpauth://totp/$issuer:$label?secret=$s&issuer=$issuer";
?>
<p class="text-muted mt-12">Or use this URI with any QR generator you trust:</p>
<div class="secret secret-sm select-all"><?=htmlspecialchars($otpauth)?></div>
<?php endif; ?>
</div>

</div>

<?php
    renderFooter();
}