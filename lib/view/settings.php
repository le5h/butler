<?php if (!defined('BUTLER_APP')) exit; ?>
<div class="container-narrow">

<?php if ($message): ?><div class="msg ok"><?=htmlspecialchars($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="msg err"><?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="card">
<form method="post">

<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">

<h4 class="section-heading">Storage</h4>

<?php $sqliteAvail = extension_loaded('pdo_sqlite'); ?>
<div class="form-group">
<label for="storage">Storage backend</label>
<select name="storage" id="storage">
<option value="file" <?=$currentStorage==='file'?'selected':''?>>File (zero setup)</option>
<option value="sqlite" <?=$currentStorage==='sqlite'?'selected':''?> <?=$sqliteAvail?'':'disabled'?>><?=$sqliteAvail?'SQLite (faster queries)':'SQLite (pdo_sqlite missing)'?></option>
</select>
<?php if (!$sqliteAvail && $currentStorage === 'sqlite'): ?>
<p class="text-muted mt-8">SQLite selected but <code>pdo_sqlite</code> PHP extension is not installed. Falling back to File storage.</p>
<?php endif; ?>
</div>

<div class="form-group">
<label for="retention_days">Auto-cleanup (days, 0 = never)</label>
<input type="number" name="retention_days" id="retention_days" value="<?=$retentionDays?>" min="0" max="3650">
</div>

<div class="form-group">
<label for="export_limit">Max rows for CSV/JSON export</label>
<input type="number" name="export_limit" id="export_limit" value="<?=$exportLimit?>" min="100" max="100000">
</div>

<h4 class="section-heading">What to track</h4>

<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_page" value="1" <?=$collectPage?'checked':''?>> Page URL</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_referrer" value="1" <?=$collectReferrer?'checked':''?>> Referrer URL</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_lang" value="1" <?=$collectLang?'checked':''?>> Browser language</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_timezone" value="1" <?=$collectTimezone?'checked':''?>> Timezone</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="collect_os" value="1" <?=$collectOs?'checked':''?>> Operating system</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="store_subnet" value="1" <?=$storeSubnet?'checked':''?>> Subnet (e.g. 192.168.1.0/24)</label>
</div>
<div class="form-group form-group-inline">
<label><input type="checkbox" name="geo_lookup" value="1" <?=$geoLookup?'checked':''?>> Look up geo location from IP</label>
</div>

<h4 class="section-heading">Presentation</h4>

<div class="form-group">
<label for="quality_min_duration">Min duration (seconds) for engaged visit</label>
<input type="number" name="quality_min_duration" id="quality_min_duration" value="<?=$qualityMinDur?>" min="0" max="3600">
</div>

<div class="form-group">
<label for="quality_min_interactions">Min interactions for engaged visit</label>
<input type="number" name="quality_min_interactions" id="quality_min_interactions" value="<?=$qualityMinInt?>" min="0" max="9999">
</div>

<button type="submit" name="save_settings" class="btn">Save</button>

</form>
</div>

<div class="card">
<form method="post">

<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">

<h4 class="section-heading">Change password</h4>

<?php if ($hasPassword): ?>
<div class="form-group">
<label for="old_password">Current password</label>
<input type="password" name="old_password" id="old_password" placeholder="Required to change password">
</div>
<?php endif; ?>

<div class="form-group">
<label for="new_password"><?=$hasPassword?'New password':'Set access password'?></label>
<input type="password" name="new_password" id="new_password" placeholder="<?=$hasPassword?'Enter new password':'Enter password'?>">
</div>

<button type="submit" name="save_password" class="btn">Change Password</button>

</form>

<h4 class="section-heading">Two-factor authentication</h4>

<?php if ($totpActive): ?>
<p class="text-muted mb-8">Two-factor authentication is <strong class="text-green">active</strong>.</p>
<div class="secret select-all"><?=htmlspecialchars($totpSecret)?></div>
<form method="post" class="mt-12">
<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">
<button type="submit" name="totp_disable" class="btn btn-danger">Disable TOTP</button>
</form>
<?php elseif ($totpCanSetup): ?>
<p class="text-muted mb-8">Scan this URI with your authenticator app, then enter the 6-digit code below to verify.</p>
<div class="secret select-all"><?=htmlspecialchars($pendingSecret)?></div>
<p class="text-muted mt-12">URI for QR generator:</p>
<div class="secret secret-sm select-all"><?=htmlspecialchars($otpauth)?></div>
<form method="post" class="mt-16">
<input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrfToken)?>">
<input type="hidden" name="pending_secret" value="<?=htmlspecialchars($pendingSecret)?>">
<div class="form-group">
<label for="totp_code">Verification code</label>
<input type="text" name="totp_code" id="totp_code" placeholder="000000" pattern="[0-9]{6}" inputmode="numeric" autofocus>
</div>
<button type="submit" name="totp_verify" class="btn">Verify &amp; enable</button>
</form>
<?php elseif (!$hasPassword): ?>
<p class="text-muted">Set a password first to enable two-factor authentication.</p>
<?php endif; ?>

</div>

</div>
