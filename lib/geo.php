<?php

function detectOS($ua)
{
    if (strpos($ua, 'Windows') !== false) return 'Windows';
    if (strpos($ua, 'Mac OS') !== false || strpos($ua, 'Macintosh') !== false) return 'macOS';
    if (strpos($ua, 'Linux') !== false) return 'Linux';
    if (strpos($ua, 'Android') !== false) return 'Android';
    if (strpos($ua, 'iOS') !== false || strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) return 'iOS';
    if (strpos($ua, 'Chrome OS') !== false) return 'ChromeOS';
    return 'Unknown';
}

function geoLookup($ip)
{
    if ($ip === '127.0.0.1' || $ip === '::1') return 'localhost';
    $resp = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,regionName,city");
    if ($resp) {
        $data = json_decode($resp, true);
        if (!empty($data['city'])) {
            return $data['city'] . ', ' . $data['regionName'] . ', ' . $data['country'];
        }
        if (!empty($data['country'])) {
            return $data['country'];
        }
    }
    return $ip;
}
