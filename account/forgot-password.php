<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';

$sent = false;
if (is_post()) {
    public_form_limit('pw_reset_ip', 5, 900);
    $email = mb_substr(post('email'), 0, 191);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !throttle_locked('pw_reset_email', mb_strtolower($email))) {
        throttle_hit('pw_reset_email', mb_strtolower($email), 3, 3600, 3600);
        $user = q_one("SELECT * FROM users WHERE email = ? AND account_status = 'active'", [$email]);
        if ($user) {
            $minutes = max(10, setting_int('reset_token_minutes', 30));
            $token = create_user_token((int) $user['id'], 'reset', $minutes);
            send_mail($user['email'], 'Password reset request', "Hello {$user['full_name']},\n\nWe received a request to reset the password for your account. Use this link within {$minutes} minutes:\n\n"
                . abs_url('account/reset-password.php', ['token' => $token]) . "\n\nIf you did not request this, you can ignore this email. Your password will not change.", 'security');
            auth_event('password_reset_requested', (int) $user['id'], $email);
        } else {
            auth_event('password_reset_unknown', null, $email);
        }
    }
    $sent = true; // same response whether or not the account exists
}
auth_start('Reset your password');
?>
<h1>Reset your password</h1>
<?php if ($sent): ?>
    <div class="notice notice-success" role="status">If an active account uses that email address, a reset link has been sent. The link expires soon and works once.</div>
    <p><a href="<?= e(url('account/login.php')) ?>">Back to sign in</a></p>
<?php else: ?>
    <p class="muted">Enter the email address of your account. We will send you a single-use link.</p>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" autocomplete="email" required maxlength="191"></div>
        <button class="btn btn-primary" type="submit">Send reset link</button>
    </form>
    <div class="auth-links"><a href="<?= e(url('account/login.php')) ?>">Back to sign in</a></div>
<?php endif;
auth_end();
