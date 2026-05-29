<?php

function getClientIp(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function detectOS($ua) {
    $map = ['Windows' => 'Windows', 'Mac OS' => 'macOS', 'Macintosh' => 'macOS',
            'Linux' => 'Linux', 'Android' => 'Android',
            'iOS' => 'iOS', 'iPhone' => 'iOS', 'iPad' => 'iOS',
            'Chrome OS' => 'ChromeOS'];
    foreach ($map as $needle => $os) {
        if (str_contains($ua, $needle)) return $os;
    }
    return 'Unknown';
}

function geoLookup($ip) {
    static $cache = [];
    if (isset($cache[$ip])) return $cache[$ip];
    if ($ip === '127.0.0.1' || $ip === '::1') return $cache[$ip] = 'localhost';

    $ctx = stream_context_create(['http' => [
        'timeout' => 3,
        'user_agent' => 'Butler/1.0',
    ]]);
    $resp = @file_get_contents("https://ip-api.com/json/{$ip}?fields=country,regionName,city", false, $ctx);
    if ($resp) {
        $data = json_decode($resp, true);
        if (!empty($data['city'])) {
            return $cache[$ip] = $data['city'] . ', ' . $data['regionName'] . ', ' . $data['country'];
        }
        if (!empty($data['country'])) {
            return $cache[$ip] = $data['country'];
        }
    }
    return $cache[$ip] = '';
}

function generateVisitId(array $config, string $ip): string {
    if (!empty($config['collect_session'])) {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $raw = session_id();
    } else {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $al = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $raw = md5($ip . '|' . $ua . '|' . $al);
    }
    $sum = 0;
    for ($i = 0; $i < strlen($raw); $i += 3) {
        $sum += unpack('N', str_pad(substr($raw, $i, 3), 4, "\x00", STR_PAD_LEFT))[1];
    }
    while ($sum > 0xFFFFFF) {
        $t = 0;
        while ($sum > 0) { $t += $sum & 0xFFFFFF; $sum >>= 24; }
        $sum = $t;
    }
    return str_replace('.', '', microtime(true)) . '-' . sprintf('%06x', $sum);
}

function subnetAddress($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $binary = inet_pton($ip);
        return inet_ntop(substr($binary, 0, 8) . str_repeat("\x00", 8)) . '::/64';
    }
    return '';
}
