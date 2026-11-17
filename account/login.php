<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';

if (current_user()) {
    redirect('admin/index.php');
}
$error = '';
$identifier = '';
if (is_post()) {
    $identifier = mb_substr(post('identifier'), 0, 191);
    $password = (string) ($_POST['password'] ?? '');
    $result = auth_attempt($identifier, mb_substr($password, 0, 256));
    if ($result['status'] === 'ok') {
        $to = safe_return($_SESSION['after_login'] ?? null, 'admin/index.php');
        unset($_SESSION['after_login']);
        redirect($to);
    }
    if ($result['status'] === 'mfa') {
        redirect('account/mfa.php');
    }
    $error = $result['status'] === 'locked'
        ? 'Too many unsuccessful attempts. Wait ' . setting_int('lockout_minutes', 15) . ' minutes and try again, or reset your password.'
        : 'The username or password is incorrect, or the account is not active.';
}
auth_start('Sign in');
?>
<h1>Sign in</h1>
<p class="muted">Use the account issued to you by your administrator.</p>
<?= flash_render() ?>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field"><label for="identifier">Username or email</label>
        <input type="text" id="identifier" name="identifier" value="<?= e($identifier) ?>" autocomplete="username" required autofocus maxlength="191"></div>
    <div class="field"><label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required maxlength="256"></div>
    <button class="btn btn-primary" type="submit">Sign in</button>
</form>
<div class="auth-links"><a href="<?= e(url('account/forgot-password.php')) ?>">Forgot your password?</a><a href="<?= e(url('helpdesk.php')) ?>">Need help?</a></div>
<?php auth_end();
