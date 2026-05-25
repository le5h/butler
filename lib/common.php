<?php

function renderHead(string $title): void {
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($title)?> - Butler</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php
}

function renderTop(string $active): void {
    $links = [
        'view' => ['View', '?view'],
        'settings' => ['Settings', '?settings'],
        'test' => ['Test', '?test'],
        'logout' => ['Logout', '?logout'],
    ];
    $visible = array_filter($links, fn($k) => $k !== $active, ARRAY_FILTER_USE_KEY);
    $navLinks = array_map(fn($l, $u) => '<a href="' . $u . '">' . $l . '</a>', array_column($visible, 0), array_column($visible, 1));
?>
<div class="container">
<div class="top">
<div class="top-brand">
<h1>Butler</h1>
<span class="top-subtitle">your privacy-first analytics</span>
</div>
<?=implode('<span class="spacer">&middot;</span>', $navLinks)?>
</div>
<?php
}

function renderFooter(): void {
?>
<footer class="site-footer">Butler — your self-hosted analytics companion</footer>
</div>
</body>
</html>
<?php
}

function renderChartJs(): void {
?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC" crossorigin="anonymous"></script><?php
}