<?php

function checkRateLimit(string $ip, int $max = 120): bool {
    $dir = __DIR__ . '/../data/ratelimit';
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return true;
    }
    $file = $dir . '/' . md5($ip) . '.txt';
    $fh = fopen($file, 'c+');
    if (!$fh) return true;
    flock($fh, LOCK_EX);
    $raw = trim(fread($fh, filesize($file) ?: 1) ?: '');
    $parts = explode('|', $raw, 2);
    $now = date('YmdH');
    $count = isset($parts[0]) && $parts[0] === $now ? (int)($parts[1] ?? 0) : 0;
    if ($count >= $max) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return false;
    }
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, $now . '|' . ($count + 1));
    flock($fh, LOCK_UN);
    fclose($fh);
    return true;
}
