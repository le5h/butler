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

    $totpSecret = $config['auth_secret'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['_csrf'] ?? '';
        if (!hash_equals($csrfToken, $submittedToken)) {
            $error = 'Invalid CSRF token. Please try again.';
        } elseif (isset($_POST['totp_verify'])) {
            $pendingSecret = $_POST['pending_secret'] ?? '';
            $code = $_POST['totp_code'] ?? '';
            if ($pendingSecret === '' || $code === '') {
                $error = 'Missing secret or verification code.';
            } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
                $error = 'Code must be exactly 6 digits.';
            } elseif (!verifyTOTP($pendingSecret, $code)) {
                $error = 'Invalid code.';
            } else {
                $config['auth_secret'] = $pendingSecret;
                $written = file_put_contents(
                    $configFile,
                    '<?php' . "\n\nreturn " . var_export($config, true) . ";\n",
                    LOCK_EX
                );
                if ($written === false) {
                    $error = 'Failed to write config file. Check permissions.';
                } else {
                    $message = 'Two-factor authentication is now active.';
                    $totpSecret = $pendingSecret;
                    unset($_SESSION['pending_totp_secret']);
                    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
                    $csrfToken = $_SESSION['settings_csrf'];
                }
            }
        } elseif (isset($_POST['totp_disable'])) {
            $config['auth_secret'] = '';
            $written = file_put_contents(
                $configFile,
                '<?php' . "\n\nreturn " . var_export($config, true) . ";\n",
                LOCK_EX
            );
            if ($written === false) {
                $error = 'Failed to write config file. Check permissions.';
            } else {
                $message = 'Two-factor authentication disabled.';
                $totpSecret = '';
                $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['settings_csrf'];
            }
        } else {
            $storage = $_POST['storage'] ?? 'file';

            if (!preg_match('/^(file|sqlite)$/', $storage)) {
                $error = 'Invalid storage type.';
            } elseif ($config['password'] !== '' && !empty($_POST['new_password']) && !password_verify($_POST['old_password'] ?? '', $config['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $config['storage'] = $storage;
                $config['store_subnet'] = !empty($_POST['store_subnet']);
                $config['geo_lookup'] = !empty($_POST['geo_lookup']);
                $config['collect_referrer'] = !empty($_POST['collect_referrer']);
                $config['collect_lang'] = !empty($_POST['collect_lang']);
                $config['collect_page'] = !empty($_POST['collect_page']);
                $retention = (int)($_POST['retention_days'] ?? 0);
                $config['retention_days'] = max(0, $retention);
                $newPwd = $_POST['new_password'] ?? '';
                if ($newPwd !== '') {
                    $config['password'] = password_hash($newPwd, PASSWORD_BCRYPT);
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
    $storeSubnet = !empty($config['store_subnet']);
    $geoLookup = !empty($config['geo_lookup']);
    $collectReferrer = !empty($config['collect_referrer']);
    $collectLang = !empty($config['collect_lang']);
    $collectPage = !empty($config['collect_page']);
    $retentionDays = (int)($config['retention_days'] ?? 0);

    $totpActive = $totpSecret !== '';
    $totpCanSetup = !$totpActive && $hasPassword;
    if ($totpCanSetup && empty($_SESSION['pending_totp_secret'])) {
        $_SESSION['pending_totp_secret'] = base32_encode(random_bytes(20));
    }
    $pendingSecret = $_SESSION['pending_totp_secret'] ?? '';

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

<h4 class="section-heading">Data collection</h4>

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

<h4 class="section-heading">Privacy</h4>

<div class="form-group">
<label><input type="checkbox" name="store_subnet" value="1" <?=$storeSubnet?'checked':''?>> Store visitor subnet (e.g. 192.168.1.0/24)</label>
</div>

<div class="form-group">
<label><input type="checkbox" name="geo_lookup" value="1" <?=$geoLookup?'checked':''?>> Look up geo location from IP (not stored)</label>
</div>

<h4 class="section-heading">Maintenance</h4>

<div class="form-group">
<label for="retention_days">Auto-cleanup (days, 0 = never)</label>
<input type="number" name="retention_days" id="retention_days" value="<?=$retentionDays?>" min="0" max="3650">
</div>

<h4 class="section-heading">Admin access</h4>

<?php if ($hasPassword): ?>
<div class="form-group">
<label for="old_password">Current password</label>
<input type="password" name="old_password" id="old_password" placeholder="Required to change password">
</div>
<?php endif; ?>
<div class="form-group">
<label for="new_password"><?=$hasPassword?'New password (leave blank to keep current)':'Set access password'?></label>
<input type="password" name="new_password" id="new_password" placeholder="<?=$hasPassword?'Enter new password':'Enter password'?>">
</div>

<button type="submit" class="btn">Save</button>
</form>

<hr class="hr-text">
<h3 class="mb-8">Two-factor authentication</h3>

<?php if ($totpActive): ?>
<p class="text-muted mb-8">Two-factor authentication is <strong class="text-green">active</strong>.</p>
<div class="secret select-all"><?=htmlspecialchars($totpSecret)?></div>
<form method="post" class="mt-12">
<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">
<button type="submit" name="totp_disable" class="btn btn-danger">Disable TOTP</button>
</form>

<?php elseif ($totpCanSetup):
    $issuer = rawurlencode('local-stats');
    $label = rawurlencode('admin');
    $s = rawurlencode($pendingSecret);
    $otpauth = "otpauth://totp/$issuer:$label?secret=$s&issuer=$issuer";
?>
<p class="text-muted mb-8">Scan this URI with your authenticator app, then enter the 6-digit code below to verify.</p>
<div class="secret select-all"><?=htmlspecialchars($pendingSecret)?></div>
<p class="text-muted mt-12">URI for QR generator:</p>
<div class="secret secret-sm select-all"><?=htmlspecialchars($otpauth)?></div>
<form method="post" class="mt-16">
<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">
<input type="hidden" name="pending_secret" value="<?=htmlspecialchars($pendingSecret)?>">
<div class="form-group">
<label for="totp_code">Verification code</label>
<input type="text" name="totp_code" id="totp_code" placeholder="000000" pattern="[0-9]{6}" inputmode="numeric" autofocus>
</div>
<button type="submit" name="totp_verify" class="btn">Verify &amp; enable</button>
</form>

<?php elseif (!$hasPassword): ?>
<p class="text-muted">Set a password first to enable two-factor authentication.</p>
<?php endif; ?>

</div>

</div>

<?php
    renderFooter();
}