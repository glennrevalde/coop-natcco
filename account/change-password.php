<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';

$u = require_login();
$errors = [];
if (is_post()) {
    $current = (string) ($_POST['current_password'] ?? '');
    $pw = (string) ($_POST['password'] ?? '');
    $hash = (string) q_val('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    if (!password_verify($current, $hash)) {
        $errors[] = 'Your current password is incorrect.';
        auth_event('password_change_failed', (int) $u['id'], $u['username']);
    }
    $errors = array_merge($errors, password_policy_errors($pw, $u));
    if ($pw !== (string) ($_POST['password_confirm'] ?? '')) {
        $errors[] = 'The two new passwords do not match.';
    }
    if ($pw !== '' && password_verify($pw, $hash)) {
        $errors[] = 'Choose a password different from your current one.';
    }
    if (!$errors) {
        q('UPDATE users SET password_hash = ?, password_changed_at = ?, must_change_password = 0, updated_at = ? WHERE id = ?', [password_make($pw), now(), now(), $u['id']]);
        $n = revoke_user_sessions((int) $u['id'], 'password_changed', (int) $u['session_row_id']);
        session_regenerate_id(true);
        auth_event('password_changed', (int) $u['id'], $u['username'], "Other sessions ended: {$n}");
        audit_log('password_changed', 'auth', (int) $u['id'], 'Changed own password');
        notify_user((int) $u['id'], 'security', 'The password for your account was changed.', 'Time: ' . fmt_datetime(now()));
        flash('success', 'Password changed. Other signed-in sessions were ended.');
        redirect('admin/index.php');
    }
}
auth_start('Change password');
?>
<h1>Change your password</h1>
<?php if ((int) $u['must_change_password'] === 1): ?><div class="notice notice-info" role="status">Your administrator requires you to set a new password before continuing.</div><?php endif; ?>
<?php if ($errors): ?><div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="text" name="username" value="<?= e($u['username']) ?>" autocomplete="username" hidden>
    <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
    <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" autocomplete="new-password" required maxlength="128">
        <p class="help">At least <?= max(10, setting_int('password_min_length', 12)) ?> characters, mixing letters, numbers and symbols.</p></div>
    <div class="field"><label for="password_confirm">Repeat the new password</label><input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required maxlength="128"></div>
    <button class="btn btn-primary" type="submit">Change password</button>
</form>
<form method="post" action="<?= e(url('account/logout.php')) ?>" class="auth-links"><?= csrf_field() ?><button class="link-btn" type="submit">Sign out</button></form>
<?php auth_end();
