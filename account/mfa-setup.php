<?php
/**
 * Enroll an authenticator app (TOTP). Required for privileged roles.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/totp.php';

$u = require_login();
if ((int) $u['mfa_enabled'] === 1 && empty($_SESSION['mfa_show_codes'])) {
    redirect('account/profile.php');
}
$codes = $_SESSION['mfa_show_codes'] ?? null;
if ($codes && get('done') && is_post() === false) {
    unset($_SESSION['mfa_show_codes']);
    redirect('admin/index.php');
}
if (empty($_SESSION['mfa_new_secret'])) {
    $_SESSION['mfa_new_secret'] = totp_new_secret();
}
$secret = $_SESSION['mfa_new_secret'];
$error = '';
if (is_post() && !$codes) {
    if (totp_verify($secret, post('code')) !== null) {
        [$plain, $hashes] = mfa_recovery_codes();
        tx(function () use ($u, $secret, $hashes) {
            q('DELETE FROM mfa_credentials WHERE user_id = ?', [$u['id']]);
            q_insert('mfa_credentials', ['user_id' => $u['id'], 'method' => 'totp', 'secret_enc' => encrypt_value($secret), 'recovery_codes' => $hashes,
                'confirmed_at' => now(), 'last_used_step' => (int) floor(time() / 30), 'created_at' => now()]);
            q('UPDATE users SET mfa_enabled = 1, updated_at = ? WHERE id = ?', [now(), $u['id']]);
            q('UPDATE user_sessions SET mfa_verified = 1 WHERE id = ?', [$u['session_row_id']]);
        });
        unset($_SESSION['mfa_new_secret']);
        $_SESSION['mfa_show_codes'] = $plain;
        auth_event('mfa_enabled', (int) $u['id'], $u['username']);
        audit_log('mfa_enabled', 'auth', (int) $u['id'], 'Enabled multifactor authentication');
        notify_user((int) $u['id'], 'security', 'Multifactor authentication was turned on for your account.', 'Time: ' . fmt_datetime(now()));
        redirect('account/mfa-setup.php');
    }
    $error = 'That code did not match. Make sure the time on your phone is set automatically, then enter the current code.';
}
auth_start('Set up multifactor authentication', true);
if ($codes): ?>
    <h1>Save your recovery codes</h1>
    <div class="notice notice-success" role="status">Multifactor authentication is now on.</div>
    <p>Each code works once if you lose your phone. Store them somewhere safe and private. They will not be shown again.</p>
    <ul class="codes code-box"><?php foreach ($codes as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
    <p><a class="btn btn-primary" href="<?= e(url('account/mfa-setup.php', ['done' => 1])) ?>">I have saved my codes</a></p>
<?php else: ?>
    <h1>Set up multifactor authentication</h1>
    <?= flash_render() ?>
    <p>Install an authenticator app such as Google Authenticator, Microsoft Authenticator or Authy, then scan this code or enter the key manually.</p>
    <div class="two-col">
        <div><div class="qr-box" data-qr="<?= e(totp_uri($secret, $u['username'])) ?>">Loading QR code…</div></div>
        <div>
            <p class="small muted">Setup key</p>
            <p class="code-box"><?= e(trim(chunk_split($secret, 4, ' '))) ?></p>
            <p class="small muted">Account: <?= e($u['username']) ?>. Type: time-based.</p>
        </div>
    </div>
    <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="code">Enter the 6-digit code from the app</label>
            <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="6"></div>
        <button class="btn btn-primary" type="submit">Turn on multifactor authentication</button>
    </form>
    <form method="post" action="<?= e(url('account/logout.php')) ?>" class="auth-links"><?= csrf_field() ?><button class="link-btn" type="submit">Sign out</button></form>
<?php endif;
auth_end();
