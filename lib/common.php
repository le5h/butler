<?php

function renderHead(string $title): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($title)?> - local-stats</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php
}

function renderTop(string $active): void
{
    $links = [
        'view' => ['View', '?view'],
        'settings' => ['Settings', '?settings'],
        'logout' => ['Logout', '?logout'],
    ];
    ?>
<div class="container">
<div class="top">
<h1>local-stats</h1>
<?php foreach ($links as $key => [$label, $url]):
    if ($key === $active) continue; ?>
<a href="<?=$url?>"><?=$label?></a><span class="spacer">&middot;</span>
<?php endforeach; ?>
</div>
<?php
}

function renderFooter(): void
{
    ?>
<footer class="site-footer">local-stats &mdash; self-hosted analytics</footer>
</div>
</body>
</html>
<?php
}

function renderChartJs(): void
{
    ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC" crossorigin="anonymous"></script><?php
}