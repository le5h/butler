<?php

require_once __DIR__ . '/auth.php';

function writeConfig(string $file, array $cfg): bool {
    return file_put_contents($file, '<?php' . "\n\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX) !== false;
}

function renderSettingsForm(string $message, string $error, string $csrfToken, array $config, bool $totpActive, bool $totpCanSetup, string $pendingSecret, string $totpSecret, string $otpauth): void {
    $currentStorage = $config['storage'] ?? 'file';
    $storeSubnet = !empty($config['store_subnet']);
    $geoLookup = !empty($config['geo_lookup']);
    $collectReferrer = !empty($config['collect_referrer']);
    $collectLang = !empty($config['collect_lang']);
    $collectPage = !empty($config['collect_page']);
    $collectTimezone = !empty($config['collect_timezone']);
    $collectOs = !empty($config['collect_os'] ?? true);
    $collectSession = !empty($config['collect_session'] ?? true);
    $retentionDays = (int)($config['retention_days'] ?? 0);
    $qualityMinDur = (int)($config['quality_min_duration'] ?? 10);
    $qualityMinInt = (int)($config['quality_min_interactions'] ?? 1);
    $exportLimit = (int)($config['export_limit'] ?? 10000);
    $hasPassword = $config['password'] !== '';

    require __DIR__ . '/view/settings.php';
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
    $config['collect_session'] = !empty($_POST['collect_session']);
    $config['quality_min_duration'] = max(0, (int)($_POST['quality_min_duration'] ?? 10));
    $config['quality_min_interactions'] = max(0, (int)($_POST['quality_min_interactions'] ?? 1));
    $config['retention_days'] = max(0, (int)($_POST['retention_days'] ?? 0));
    $config['export_limit'] = max(100, (int)($_POST['export_limit'] ?? 10000));

    if (!writeConfig($configFile, $config)) return 'Failed to write config file. Check permissions.';

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
    if (!writeConfig($configFile, $config)) return 'Failed to write config file. Check permissions.';

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
                if (!writeConfig($configFile, $config)) {
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
            if (!writeConfig($configFile, $config)) {
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

    require_once __DIR__ . '/layout.php';

    header('Content-Type: text/html; charset=utf-8');
    
    renderHead('Settings', 'settings');

    renderSettingsForm($message, $error, $csrfToken, $config, $totpActive, $totpCanSetup, $pendingSecret, $totpSecret, $otpauth);

    renderFooter(true);
}
