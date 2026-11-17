<?php
/**
 * My account: details, security (password, MFA, sessions), notification preferences.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';
require APP_ROOT . '/includes/totp.php';

$u = require_login();
$id = (int) $u['id'];

if (is_post()) {
    $do = post('do');
    if ($do === 'details') {
        $name = mb_substr(post('full_name'), 0, 150);
        $phone = mb_substr(post('phone'), 0, 40);
        if ($name === '') {
            flash('error', 'Enter your full name.');
        } elseif ($phone !== '' && !preg_match('/^[0-9+()\-\s.\/]{5,40}$/', $phone)) {
            flash('error', 'Enter a valid phone number.');
        } else {
            q('UPDATE users SET full_name = ?, phone = ?, updated_at = ? WHERE id = ?', [$name, $phone ?: null, now(), $id]);
            audit_log('update_profile', 'users', $id, 'Updated own profile');
            flash('success', 'Your details were saved.');
        }
    } elseif ($do === 'revoke') {
        $sid = post_int('session');
        $n = q('UPDATE user_sessions SET revoked_at = ?, revoked_reason = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL AND id <> ?', [now(), 'revoked_by_user', $sid, $id, $u['session_row_id']])->rowCount();
        if ($n) {
            auth_event('session_revoked', $id, $u['username'], 'Session #' . $sid);
            audit_log('revoke_session', 'auth', $id, 'Ended another session');
        }
        flash('success', 'The session was ended.');
    } elseif ($do === 'revoke_all') {
        $n = revoke_user_sessions($id, 'revoked_by_user', (int) $u['session_row_id']);
        auth_event('session_revoked', $id, $u['username'], "All other sessions ({$n})");
        audit_log('revoke_session', 'auth', $id, "Ended {$n} other sessions");
        flash('success', "{$n} other session(s) ended.");
    } elseif ($do === 'prefs') {
        foreach (array_keys(NOTIF_TYPES) as $t) {
            if ($t === 'security') {
                continue;
            }
            $inApp = !empty($_POST['inapp'][$t]) ? 1 : 0;
            $email = !empty($_POST['email'][$t]) ? 1 : 0;
            q('DELETE FROM notification_preferences WHERE user_id = ? AND notif_type = ?', [$id, $t]);
            q_insert('notification_preferences', ['user_id' => $id, 'notif_type' => $t, 'in_app_enabled' => $inApp, 'email_enabled' => $email, 'updated_at' => now()]);
        }
        flash('success', 'Notification preferences saved.');
    } elseif ($do === 'mfa_codes' || $do === 'mfa_disable') {
        $hash = (string) q_val('SELECT password_hash FROM users WHERE id = ?', [$id]);
        if (!password_verify((string) ($_POST['password'] ?? ''), $hash) || !mfa_check_user_code($id, post('code'))) {
            auth_event('mfa_change_failed', $id, $u['username']);
            flash('error', 'Your password or verification code was not accepted.');
        } elseif ($do === 'mfa_codes') {
            [$plain, $hashes] = mfa_recovery_codes();
            q('UPDATE mfa_credentials SET recovery_codes = ?, updated_at = ? WHERE user_id = ?', [$hashes, now(), $id]);
            auth_event('mfa_codes_regenerated', $id, $u['username']);
            audit_log('mfa_codes_regenerated', 'auth', $id, 'Generated new recovery codes');
            $_SESSION['mfa_show_codes'] = $plain;
            redirect('account/mfa-setup.php');
        } elseif (mfa_enrollment_required(array_merge($u, ['mfa_enabled' => 0]))) {
            flash('error', 'Your role requires multifactor authentication, so it cannot be turned off.');
        } else {
            q('DELETE FROM mfa_credentials WHERE user_id = ?', [$id]);
            q('UPDATE users SET mfa_enabled = 0, updated_at = ? WHERE id = ?', [now(), $id]);
            auth_event('mfa_disabled', $id, $u['username']);
            audit_log('mfa_disabled', 'auth', $id, 'Turned off multifactor authentication');
            notify_user($id, 'security', 'Multifactor authentication was turned off for your account.', 'Time: ' . fmt_datetime(now()));
            flash('success', 'Multifactor authentication was turned off.');
        }
    }
    redirect('account/profile.php');
}

$user = q_one('SELECT * FROM users WHERE id = ?', [$id]);
$roles = user_role_rows($id);
$sessions = q_all('SELECT * FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_activity_at DESC LIMIT 20', [$id]);
$events = q_all('SELECT * FROM authentication_events WHERE user_id = ? ORDER BY id DESC LIMIT 12', [$id]);
$codesLeft = count(array_filter(explode("\n", (string) q_val('SELECT recovery_codes FROM mfa_credentials WHERE user_id = ?', [$id]))));

admin_start('My account', [['My account', null]]);
page_bar('My account and security', 'Signed in as ' . e($user['username']), '<a class="btn btn-secondary" href="' . e(url('account/change-password.php')) . '">Change password</a>');
echo flash_render();
?>
<div class="two-col">
<div>
<form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="do" value="details">
    <h2 class="panel-title">My details</h2>
    <div class="grid">
        <?= form_field('full_name', ['label' => 'Full name', 'required' => true, 'max' => 150], $user['full_name']) ?>
        <?= form_field('phone', ['label' => 'Phone', 'type' => 'tel', 'max' => 40], $user['phone']) ?>
        <div class="field"><label>Email</label><div class="static"><?= e($user['email']) ?> <span class="muted small">(changed by an administrator)</span></div></div>
        <div class="field"><label>Last sign-in</label><div class="static"><?= e(fmt_datetime($user['last_login_at'])) ?></div></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Save details</button></div>
</form>

<section class="panel"><h2 class="panel-title">Active sessions</h2>
    <div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Device</th><th scope="col">IP address</th><th scope="col">Signed in</th><th scope="col">Last active</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead><tbody>
    <?php foreach ($sessions as $s): ?>
        <tr><td data-label="Device"><?= e(excerpt($s['user_agent'], 60)) ?><?= (int) $s['id'] === (int) $u['session_row_id'] ? '<span class="sub st st-ok">This session</span>' : '' ?></td>
            <td data-label="IP"><?= e($s['ip_address']) ?></td><td data-label="Signed in"><?= e(fmt_datetime($s['created_at'])) ?></td><td data-label="Last active"><?= e(time_ago($s['last_activity_at'])) ?></td>
            <td class="row-actions"><?php if ((int) $s['id'] !== (int) $u['session_row_id']): ?><form method="post" data-confirm="End this session?"><?= csrf_field() ?><input type="hidden" name="do" value="revoke"><input type="hidden" name="session" value="<?= (int) $s['id'] ?>"><button class="link-btn" type="submit">End session</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php if (count($sessions) > 1): ?><form method="post" class="form-actions" data-confirm="End all other sessions?"><?= csrf_field() ?><input type="hidden" name="do" value="revoke_all"><button class="btn btn-secondary" type="submit">End all other sessions</button></form><?php endif; ?>
</section>

<form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="do" value="prefs">
    <h2 class="panel-title">Notification preferences</h2>
    <p class="muted small">Security notices are always sent. Emails never include confidential details; sign in to read them.</p>
    <div class="table-wrap"><table class="table matrix"><thead><tr><th scope="col">Notification</th><th scope="col">In the system</th><th scope="col">By email</th></tr></thead><tbody>
    <?php foreach (NOTIF_TYPES as $t => $label): if ($t === 'security') { continue; } $p = notif_pref($id, $t); ?>
        <tr><td><?= e($label) ?></td><td><input type="checkbox" name="inapp[<?= e($t) ?>]" value="1" aria-label="<?= e($label) ?> in the system"<?= $p['in_app'] ? ' checked' : '' ?>></td>
            <td><input type="checkbox" name="email[<?= e($t) ?>]" value="1" aria-label="<?= e($label) ?> by email"<?= $p['email'] ? ' checked' : '' ?>></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Save preferences</button></div>
</form>
</div>

<div>
<section class="panel"><h2 class="panel-title">Roles and access</h2>
    <dl class="dl"><div class="dl-item dl-wide"><dt>Roles</dt><dd><?= $roles ? e(implode(', ', array_column($roles, 'name'))) : '<span class="muted">No active role</span>' ?></dd></div>
    <div class="dl-item dl-wide"><dt>Data scope</dt><dd><?= e(scope_description()) ?></dd></div></dl>
</section>

<section class="panel"><h2 class="panel-title">Multifactor authentication</h2>
<?php if ((int) $user['mfa_enabled'] === 1): ?>
    <p><?= status_text('active', 'On') ?> <span class="muted small"><?= (int) $codesLeft ?> recovery codes left</span></p>
    <form method="post" novalidate><?= csrf_field() ?>
        <div class="field"><label for="mfa_pw">Password</label><input type="password" id="mfa_pw" name="password" autocomplete="current-password" required></div>
        <div class="field"><label for="mfa_code">Current authenticator code</label><input type="text" id="mfa_code" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="11"></div>
        <div class="btn-row"><button class="btn btn-secondary" type="submit" name="do" value="mfa_codes">New recovery codes</button>
        <?php if (!mfa_enrollment_required(array_merge($user, ['mfa_enabled' => 0]))): ?><button class="btn btn-danger-ghost" type="submit" name="do" value="mfa_disable">Turn off</button><?php endif; ?></div>
    </form>
<?php else: ?>
    <p><?= status_text('inactive', 'Off') ?></p>
    <p class="small">Add a second step to your sign-in with an authenticator app.</p>
    <a class="btn btn-primary" href="<?= e(url('account/mfa-setup.php')) ?>">Set up</a>
<?php endif; ?>
</section>

<section class="panel"><h2 class="panel-title">Recent sign-in activity</h2>
    <ul class="mini-list"><?php foreach ($events as $ev): ?>
        <li><span class="ml-main"><?= e(label_from_key($ev['event_type'])) ?><span class="sub muted small"><?= e($ev['ip_address']) ?></span></span><span class="ml-meta"><?= e(time_ago($ev['created_at'])) ?></span></li>
    <?php endforeach; ?></ul>
</section>
</div>
</div>
<?php admin_end();
