<?php

function renderSettingsForm(string $message, string $error, string $csrfToken, array $config, bool $totpActive, bool $totpCanSetup, string $pendingSecret, string $totpSecret, string $otpauth): void {
    $currentStorage = $config['storage'] ?? 'file';
    $storeSubnet = !empty($config['store_subnet']);
    $geoLookup = !empty($config['geo_lookup']);
    $collectReferrer = !empty($config['collect_referrer']);
    $collectLang = !empty($config['collect_lang']);
    $collectPage = !empty($config['collect_page']);
    $collectTimezone = !empty($config['collect_timezone']);
    $collectOs = !empty($config['collect_os'] ?? true);
    $retentionDays = (int)($config['retention_days'] ?? 0);
    $qualityMinDur = (int)($config['quality_min_duration'] ?? 10);
    $qualityMinInt = (int)($config['quality_min_interactions'] ?? 1);
    $hasPassword = $config['password'] !== '';
?>
<div class="container-narrow">

<?php if ($message): ?><div class="msg ok"><?=htmlspecialchars($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="msg err"><?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="card">
<form method="post">

<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">

<h4 class="section-heading">Storage backend</h4>

<div class="form-group">
<select name="storage">
<option value="file" <?=$currentStorage==='file'?'selected':''?>>File (zero setup)</option>
<option value="sqlite" <?=$currentStorage==='sqlite'?'selected':''?>>SQLite (faster queries)</option>
</select>
</div>

<h4 class="section-heading">What to track</h4>

<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_page" value="1" <?=$collectPage?'checked':''?>> Page URL</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_referrer" value="1" <?=$collectReferrer?'checked':''?>> Referrer URL</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_lang" value="1" <?=$collectLang?'checked':''?>> Browser language</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_timezone" value="1" <?=$collectTimezone?'checked':''?>> Timezone</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_os" value="1" <?=$collectOs?'checked':''?>> Operating system</label>
</div>
<div class="form-group">
<label><input type="checkbox" name="store_subnet" value="1" <?=$storeSubnet?'checked':''?>> Subnet (e.g. 192.168.1.0/24)</label>
</div>
<div class="form-group">
<label><input type="checkbox" name="geo_lookup" value="1" <?=$geoLookup?'checked':''?>> Look up geo location from IP</label>
</div>

<h4 class="section-heading">Auto-cleanup</h4>

<div class="form-group">
<label for="retention_days">Auto-cleanup (days, 0 = never)</label>
<input type="number" name="retention_days" id="retention_days" value="<?=$retentionDays?>" min="0" max="3650">
</div>

<h4 class="section-heading">Quality thresholds</h4>

<div class="form-group">
<label for="quality_min_duration">Min duration (seconds) for engaged visit</label>
<input type="number" name="quality_min_duration" id="quality_min_duration" value="<?=$qualityMinDur?>" min="0" max="3600">
</div>

<div class="form-group">
<label for="quality_min_interactions">Min interactions for engaged visit</label>
<input type="number" name="quality_min_interactions" id="quality_min_interactions" value="<?=$qualityMinInt?>" min="0" max="9999">
</div>

<button type="submit" name="save_settings" class="btn">Save</button>

</form>
</div>

<div class="card">
<form method="post">

<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">

<h4 class="section-heading">Change password</h4>

<?php if ($hasPassword): ?>
<div class="form-group">
<label for="old_password">Current password</label>
<input type="password" name="old_password" id="old_password" placeholder="Required to change password">
</div>
<?php endif; ?>

<div class="form-group">
<label for="new_password"><?=$hasPassword?'New password':'Set access password'?></label>
<input type="password" name="new_password" id="new_password" placeholder="<?=$hasPassword?'Enter new password':'Enter password'?>">
</div>

<button type="submit" name="save_password" class="btn">Change Password</button>

</form>

<h4 class="section-heading">Two-factor authentication</h4>

<?php if ($totpActive): ?>
<p class="text-muted mb-8">Two-factor authentication is <strong class="text-green">active</strong>.</p>
<div class="secret select-all"><?=htmlspecialchars($totpSecret)?></div>
<form method="post" class="mt-12">
<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">
<button type="submit" name="totp_disable" class="btn btn-danger">Disable TOTP</button>
</form>
<?php elseif ($totpCanSetup): ?>
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
}

function saveSettings(): string {
    global $config, $configFile;
    $storage = $_POST['storage'] ?? 'file';
    if (!preg_match('/^(file|sqlite)$/', $storage)) return 'Invalid storage type.';
    $config['storage'] = $storage;
    $config['store_subnet'] = !empty($_POST['store_subnet']);
    $config['geo_lookup'] = !empty($_POST['geo_lookup']);
    $config['collect_referrer'] = !empty($_POST['collect_referrer']);
    $config['collect_lang'] = !empty($_POST['collect_lang']);
    $config['collect_page'] = !empty($_POST['collect_page']);
    $config['collect_timezone'] = !empty($_POST['collect_timezone']);
    $config['collect_os'] = !empty($_POST['collect_os']);
    $config['quality_min_duration'] = max(0, (int)($_POST['quality_min_duration'] ?? 10));
    $config['quality_min_interactions'] = max(0, (int)($_POST['quality_min_interactions'] ?? 1));
    $config['retention_days'] = max(0, (int)($_POST['retention_days'] ?? 0));
    $written = file_put_contents($configFile, '<?php' . "\n\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
    if ($written === false) return 'Failed to write config file. Check permissions.';
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['_settings_message'] = 'Configuration saved.';
    return '';
}

function savePassword(): string {
    global $config, $configFile;
    if ($config['password'] !== '' && !password_verify($_POST['old_password'] ?? '', $config['password'])) {
        return 'Current password is incorrect.';
    }
    $newPwd = $_POST['new_password'] ?? '';
    if ($newPwd === '') return 'New password cannot be empty.';
    $config['password'] = password_hash($newPwd, PASSWORD_BCRYPT);
    $written = file_put_contents($configFile, '<?php' . "\n\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
    if ($written === false) return 'Failed to write config file. Check permissions.';
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['_settings_message'] = 'Password updated.';
    return '';
}

function serveSettings() {
    global $config, $configFile;
    if (!empty($config['password']) && !checkAuth('settings')) return;
    if (isset($_POST['pwd']) || isset($_POST['totp'])) { header('Location: ?settings'); return; }

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
        } elseif (isset($_POST['save_settings'])) {
            $error = saveSettings();
        } elseif (isset($_POST['save_password'])) {
            $error = savePassword();
        }
    }

    if ($message === '' && !empty($_SESSION['_settings_message'])) {
        $message = $_SESSION['_settings_message'];
        unset($_SESSION['_settings_message']);
        $csrfToken = $_SESSION['settings_csrf'];
    }
    if ($error === '' && !empty($_SESSION['_settings_error'])) {
        $error = $_SESSION['_settings_error'];
        unset($_SESSION['_settings_error']);
    }

    $hasPassword = $config['password'] !== '';

    $totpActive = $totpSecret !== '';
    $totpCanSetup = !$totpActive && $hasPassword;
    if ($totpCanSetup && empty($_SESSION['pending_totp_secret'])) {
        $_SESSION['pending_totp_secret'] = base32_encode(random_bytes(20));
    }
    $pendingSecret = $_SESSION['pending_totp_secret'] ?? '';
    $otpauth = $totpCanSetup
        ? 'otpauth://totp/' . rawurlencode('Butler') . ':' . rawurlencode('admin') . '?secret=' . rawurlencode($pendingSecret) . '&issuer=' . rawurlencode('Butler')
        : '';

    require_once __DIR__ . '/common.php';
    header('Content-Type: text/html; charset=utf-8');
    renderHead('Settings');
    renderTop('settings');
    renderSettingsForm($message, $error, $csrfToken, $config, $totpActive, $totpCanSetup, $pendingSecret, $totpSecret, $otpauth);
    renderFooter();
}