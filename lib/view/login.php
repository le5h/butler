<?php if (!defined('BUTLER_APP')) exit; ?>
<form method="post" class="auth-form">
<div class="auth-brand">Butler</div>
<?php if ($needsTotp): ?>
<h2>Two-factor auth</h2>
<p class="text-muted">Enter the 6-digit code from your authenticator app to continue.</p>
<input type="text" name="totp" placeholder="000000" autofocus pattern="[0-9]{6}" inputmode="numeric">
<?php else: ?>
<h2>Welcome back</h2>
<p class="text-muted">Enter your password to access your analytics.</p>
<input type="hidden" name="<?=htmlspecialchars($page)?>" value="">
<input type="password" name="pwd" placeholder="Enter password" autofocus>
<?php endif; ?>
<button type="submit" class="btn">Sign in</button>
</form>
