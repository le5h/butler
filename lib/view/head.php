<?php if (!defined('BUTLER_APP')) exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($title)?> - Butler</title>
<link rel="stylesheet" href="style.css">
</head>
<body<?=$bodyClass ? ' class="' . $bodyClass . '"' : ''?>>
<?php if ($active !== null): ?>
<div class="container">
<div class="top">
<div class="top-brand">
<h1>Butler</h1>
<span class="top-subtitle">your privacy-first analytics</span>
</div>
<?=$navLinks?>
</div>
<?php endif; ?>
