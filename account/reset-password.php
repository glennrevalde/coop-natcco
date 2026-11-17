<?php
/**
 * Set a new password from a single-use reset or invitation link.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';

$token = (string) (is_post() ? post('token') : get('token'));
$row = find_user_token($token);
$errors = [];
if ($row && is_post()) {
    $pw = (string) ($_POST['password'] ?? '');
    $errors = password_policy_errors($pw, $row);
    if ($pw !== (string) ($_POST['password_confirm'] ?? '')) {
        $errors[] = 'The two passwords do not match.';
    }
    if (!$errors) {
        tx(function () use ($row, $pw) {
            q('UPDATE users SET password_hash = ?, password_changed_at = ?, must_change_password = 0, email_verified_at = COALESCE(email_verified_at, ?), updated_at = ? WHERE id = ?',
                [password_make($pw), now(), now(), now(), $row['user_id']]);
            q('UPDATE password_reset_tokens SET used_at = ? WHERE id = ?', [now(), $row['id']]);
            revoke_user_sessions((int) $row['user_id'], 'password_reset');
        });
        auth_event($row['purpose'] === 'invite' ? 'invite_accepted' : 'password_reset', (int) $row['user_id'], $row['username']);
        audit_log($row['purpose'] === 'invite' ? 'invite_accepted' : 'password_reset', 'auth', (int) $row['user_id'], 'Password set using a single-use link');
        notify_user((int) $row['user_id'], 'security', 'The password for your account was changed.', 'Time: ' . fmt_datetime(now()));
        flash('success', 'Your password has been set. You can now sign in.');
        redirect('account/login.php');
    }
}
auth_start('Choose a new password');
if (!$row): ?>
    <h1>This link is not valid</h1>
    <p>The link has expired, was already used, or is incorrect. Request a new one.</p>
    <p><a class="btn btn-primary" href="<?= e(url('account/forgot-password.php')) ?>">Request a new link</a></p>
<?php else: ?>
    <h1><?= $row['purpose'] === 'invite' ? 'Welcome, set your password' : 'Choose a new password' ?></h1>
    <p class="muted">Account: <?= e($row['username']) ?>. Use at least <?= max(10, setting_int('password_min_length', 12)) ?> characters and mix letters, numbers and symbols. A short sentence works well.</p>
    <?php if ($errors): ?><div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="text" name="username" value="<?= e($row['username']) ?>" autocomplete="username" hidden>
        <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" autocomplete="new-password" required maxlength="128"></div>
        <div class="field"><label for="password_confirm">Repeat the new password</label><input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required maxlength="128"></div>
        <button class="btn btn-primary" type="submit">Save password</button>
    </form>
<?php endif;
auth_end();
