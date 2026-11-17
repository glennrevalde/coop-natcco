<?php
/**
 * Second sign-in step: authenticator code or recovery code.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/totp.php';

$pending = $_SESSION['mfa_pending'] ?? null;
if (!$pending || time() - (int) $pending['at'] > 300) {
    unset($_SESSION['mfa_pending']);
    flash('info', 'Please sign in again.');
    redirect('account/login.php');
}
$error = '';
if (is_post()) {
    $uid = (int) $pending['uid'];
    $user = q_one("SELECT * FROM users WHERE id = ? AND account_status = 'active'", [$uid]);
    if (!$user || throttle_locked('mfa', (string) $uid)) {
        unset($_SESSION['mfa_pending']);
        auth_event('mfa_locked', $uid);
        flash('error', 'Too many incorrect codes. Sign in again later.');
        redirect('account/login.php');
    }
    if (mfa_check_user_code($uid, post('code'))) {
        throttle_clear('mfa', (string) $uid);
        auth_event('mfa_success', $uid, $user['username']);
        complete_login($user, true);
        $to = safe_return($_SESSION['after_login'] ?? null, 'admin/index.php');
        unset($_SESSION['after_login']);
        redirect($to);
    }
    throttle_hit('mfa', (string) $uid, 5, 900, 900);
    auth_event('mfa_failed', $uid, $user['username']);
    audit_log('mfa_failed', 'auth', $uid, 'Incorrect MFA code', 'failure');
    $error = 'That code is not valid. Check the time on your phone and try again.';
}
auth_start('Verification code');
?>
<h1>Enter your verification code</h1>
<p class="muted">Open your authenticator app and enter the 6-digit code for COOP NATCCO Partylist. You can also use one of your recovery codes.</p>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field"><label for="code">Code</label>
        <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="11"></div>
    <button class="btn btn-primary" type="submit">Verify and continue</button>
</form>
<div class="auth-links"><a href="<?= e(url('account/login.php')) ?>">Use a different account</a></div>
<?php auth_end();
