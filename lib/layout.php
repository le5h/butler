<?php

function renderHead(string $title): void {
    require __DIR__ . '/view/head.php';
}

function renderTop(string $active): void {
    $links = [
        'stats' => ['Dashboard', '?stats'],
        'settings' => ['Settings', '?settings'],
        'test' => ['Test', '?test'],
        'logout' => ['Logout', '?logout'],
    ];
    $visible = array_filter($links, fn($k) => $k !== $active, ARRAY_FILTER_USE_KEY);
    $navLinks = array_map(fn($l, $u) => '<a href="' . $u . '">' . $l . '</a>', array_column($visible, 0), array_column($visible, 1));
    
    require __DIR__ . '/view/top.php';
}

function renderFooter(): void {
    require __DIR__ . '/view/footer.php';
}

function renderChartJs(): void {
    require __DIR__ . '/view/chartjs.php';
}