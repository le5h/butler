<?php

function detectOS($ua) {
    if (strpos($ua, 'Windows') !== false) return 'Windows';
    if (strpos($ua, 'Mac OS') !== false || strpos($ua, 'Macintosh') !== false) return 'macOS';
    if (strpos($ua, 'Linux') !== false) return 'Linux';
    if (strpos($ua, 'Android') !== false) return 'Android';
    if (strpos($ua, 'iOS') !== false || strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) return 'iOS';
    if (strpos($ua, 'Chrome OS') !== false) return 'ChromeOS';
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

function subnetAddress($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        $hextets = array_slice($parts, 0, 4);
        return implode(':', $hextets) . '::/64';
    }
    return '';
}
