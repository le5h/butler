<?php

function checkAuth(string $page = 'view'): bool {
    global $config;
    if (empty($config['password'])) return true;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($_SESSION['auth_ok']) && !empty($_SESSION['auth_totp'])) {
        return true;
    }

    if (!empty($_SESSION['auth_ok']) && empty($config['auth_secret'])) {
        $_SESSION['auth_totp'] = true;
        return true;
    }

    if (!empty($_SESSION['auth_ok']) && !empty($config['auth_secret'])) {
        $totp = $_POST['totp'] ?? '';
        if ($totp !== '' && verifyTOTP($config['auth_secret'], $totp)) {
            $_SESSION['auth_totp'] = true;
            return true;
        }
    }

    $pwd = $_POST['pwd'] ?? '';
    if ($pwd !== '' && password_verify($pwd, $config['password'])) {
        $_SESSION['auth_ok'] = true;
        session_regenerate_id(true);
        if (empty($config['auth_secret'])) {
            $_SESSION['auth_totp'] = true;
            return true;
        }
        $totp = $_POST['totp'] ?? '';
        if ($totp !== '' && verifyTOTP($config['auth_secret'], $totp)) {
            $_SESSION['auth_totp'] = true;
            return true;
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    http_response_code(401);
    $needsTotp = !empty($_SESSION['auth_ok']) && !empty($config['auth_secret']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auth required - Butler</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
<form method="post" class="auth-form">
<div class="auth-brand">Butler</div>
<?php if ($needsTotp): ?>
<h2>Two-factor auth</h2>
<p class="text-muted">Enter the 6-digit code from your authenticator app to continue.</p>
<input type="text" name="totp" placeholder="000000" autofocus pattern="[0-9]{6}" inputmode="numeric">
<?php else: ?>
<h2>Welcome back</h2>
<p class="text-muted">Enter your password to access your analytics.</p>
<input type="hidden" name="<?=htmlspecialchars($page)?>" value="">
<input type="password" name="pwd" placeholder="Enter password" autofocus>
<?php endif; ?>
<button type="submit" class="btn">Sign in</button>
</form>
</body>
</html>
<?php
    return false;
}

function verifyTOTP($secret, $code) {
    $decoded = base32_decode($secret);
    if ($decoded === '') return false;
    $counter = (int)floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(generateHOTP($decoded, $counter + $i), $code)) {
            return true;
        }
    }
    return false;
}

function generateHOTP($key, $counter) {
    $data = pack('J', $counter);
    $hash = hash_hmac('sha1', $data, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $trunc = ((ord($hash[$offset]) & 0x7f) << 24) |
             ((ord($hash[$offset + 1]) & 0xff) << 16) |
             ((ord($hash[$offset + 2]) & 0xff) << 8) |
             (ord($hash[$offset + 3]) & 0xff);
    return str_pad($trunc % 1000000, 6, '0', STR_PAD_LEFT);
}

function base32_decode($str) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $str = strtoupper(str_replace('=', '', $str));
    $bits = '';
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $val = strpos($alphabet, $str[$i]);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $result = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $result .= chr(bindec(substr($bits, $i, 8)));
    }
    return $result;
}

function base32_encode($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $len = strlen($input);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($input[$i])), 8, '0', STR_PAD_LEFT);
    }
    $output = '';
    for ($i = 0; $i + 5 <= strlen($bits); $i += 5) {
        $output .= $alphabet[bindec(substr($bits, $i, 5))];
    }
    $remainder = strlen($bits) % 5;
    if ($remainder > 0) {
        $chunk = str_pad(substr($bits, -$remainder), 5, '0');
        $output .= $alphabet[bindec($chunk)];
    }
    return $output;
}