<?php

function renderTestPage(): void {
    require __DIR__ . '/view/test.php';
}

function serveTest() {
    require_once __DIR__ . '/layout.php';

    header('Content-Type: text/html; charset=utf-8');
    
    renderHead('Test');
    renderTop('test');
    renderTestPage();
    renderFooter();
}
