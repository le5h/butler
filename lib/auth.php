<?php

function checkAuth(string $page = 'view'): bool
{
    global $config;
    if (empty($config['password'])) return true;

    $pwd = $_GET['pwd'] ?? $_POST['pwd'] ?? '';
    if (password_verify($pwd, $config['password'])) return true;

    header('Content-Type: text/html; charset=utf-8');
    http_response_code(401);
    ?>
<!DOCTYPE html>
<html><head><title>Auth required</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background:#f5f5f5}
form{background:#fff;padding:32px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
input{padding:10px;border:1px solid #ddd;border-radius:4px;font-size:1rem;width:100%;margin-bottom:12px}
button{padding:10px 20px;background:#0066cc;color:#fff;border:none;border-radius:4px;cursor:pointer}
h2{margin-bottom:16px;font-size:1.2rem}</style></head><body>
<form method="get">
<h2>Password required</h2>
<input type="hidden" name="<?=htmlspecialchars($page)?>" value="">
<input type="password" name="pwd" placeholder="Enter password" autofocus>
<button type="submit">Submit</button>
</form>
</body></html>
<?php
    return false;
}
