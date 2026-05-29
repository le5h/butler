<?php

function renderHead(string $title, ?string $active = null, string $bodyClass = ''): void {
    $links = [
        'stats' => ['Dashboard', '?stats'],
        'settings' => ['Settings', '?settings'],
        'test' => ['Test', '?test'],
        'logout' => ['Logout', '?logout'],
    ];
    $visible = $active !== null
        ? array_filter($links, fn($k) => $k !== $active, ARRAY_FILTER_USE_KEY)
        : [];
    $navLinks = $active !== null
        ? implode('<span class="spacer">&middot;</span>', array_map(fn($l, $u) => '<a href="' . $u . '">' . $l . '</a>', array_column($visible, 0), array_column($visible, 1)))
        : '';
    require __DIR__ . '/view/head.php';
}

function renderFooter(bool $hasContainer = false): void {
    require __DIR__ . '/view/footer.php';
}
