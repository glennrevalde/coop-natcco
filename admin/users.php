<?php
/**
 * User accounts, role assignments (with approval), data scope assignments,
 * suspension, MFA reset and session revocation. Users can never raise their own privileges.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';

$me = require_perm('users.view');
$action = get('action', 'list');

function assignable_roles(): array
{
    $all = q_all('SELECT * FROM roles ORDER BY rank_level DESC, name');
    if (is_super()) {
        return $all;
    }
    $mine = user_permissions();
    $myRank = user_rank();
    $out = [];
    foreach ($all as $r) {
        if ((int) $r['rank_level'] >= $myRank) {
            continue;
        }
        $perms = q_col('SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [$r['id']]);
        if (!array_diff($perms, array_keys($mine))) {
            $out[] = $r;
        }
    }
    return $out;
}

function can_manage_user(array $target): bool
{
    if ((int) $target['id'] === uid()) {
        return false;
    }
    if (is_super()) {
        return true;
    }
    return !is_super((int) $target['id']) && user_rank((int) $target['id']) < user_rank();
}

function can_approve_role_assignment(array $ur): bool
{
    if (!can('users.approve_roles') || (int) $ur['user_id'] === uid()) {
        return false;
    }
    if (is_super()) {
        return true;
    }
    if ((int) $ur['assigned_by'] === uid()) {
        return false; // a second administrator must approve
    }
    return in_array((int) $ur['role_id'], array_map(fn($r) => (int) $r['id'], assignable_roles()), true);
}

function scope_label_row(array $s): string
{
    switch ($s['scope_type']) {
        case 'national':
            return 'Nationwide';
        case 'region':
            return 'Region: ' . q_val('SELECT name FROM regions WHERE id = ?', [$s['region_id']]);
        case 'province':
            return 'Province: ' . q_val('SELECT name FROM provinces WHERE id = ?', [$s['province_id']]);
        case 'city':
            return 'City/municipality: ' . q_val('SELECT name FROM cities_municipalities WHERE id = ?', [$s['city_id']]);
        case 'cooperative':
            return 'Cooperative: ' . lookup_label('coop', $s['cooperative_id']);
    }
    return '';
}

/** Validate a scope assignment posted from a form. Returns row data or throws. */
function scope_from_post(): array
{
    $type = post('scope_type');
    $row = ['scope_type' => $type, 'region_id' => null, 'province_id' => null, 'city_id' => null, 'cooperative_id' => null];
    switch ($type) {
        case 'national':
            if (!scope_is_national()) {
                throw new ValidationError('Only users with nationwide scope can grant nationwide scope.');
            }
            break;
        case 'region':
            $row['region_id'] = post_int('s_region_id') ?: null;
            if (!$row['region_id'] || !scope_allows($row['region_id'], null, null)) {
                throw new ValidationError('Select a region within your own scope.');
            }
            break;
        case 'province':
            $row['province_id'] = post_int('s_province_id') ?: null;
            $reg = $row['province_id'] ? q_val('SELECT region_id FROM provinces WHERE id = ?', [$row['province_id']]) : null;
            if (!$reg || !scope_allows((int) $reg, $row['province_id'], null)) {
                throw new ValidationError('Select a province within your own scope.');
            }
            break;
        case 'city':
            $row['city_id'] = post_int('s_city_id') ?: null;
            $c = $row['city_id'] ? q_one('SELECT region_id, province_id FROM cities_municipalities WHERE id = ?', [$row['city_id']]) : null;
            if (!$c || !scope_allows((int) $c['region_id'], $c['province_id'] ? (int) $c['province_id'] : null, $row['city_id'])) {
                throw new ValidationError('Select a city or municipality within your own scope.');
            }
            break;
        case 'cooperative':
            $row['cooperative_id'] = post_int('s_cooperative_id') ?: null;
            if (!$row['cooperative_id'] || !scope_allows_coop($row['cooperative_id'])) {
                throw new ValidationError('Select a cooperative within your own scope.');
            }
            break;
        case '':
            return [];
        default:
            throw new ValidationError('Choose a valid scope type.');
    }
    return $row;
}

function scope_fields_html(): string
{
    $h = form_field('scope_type', ['label' => 'Data scope', 'type' => 'select', 'options' => ['national' => 'Nationwide', 'region' => 'Region', 'province' => 'Province', 'city' => 'City or municipality', 'cooperative' => 'Cooperative'], 'placeholder' => '— No scope —',
        'help' => 'Limits which records the user can see. Assignment-based roles (point persons, ambassadors) get their scope from their assignment record.'], post('scope_type'));
    $h .= form_field('s_region_id', ['label' => 'Region', 'type' => 'region', 'geo_group' => 'sc', 'width' => 'third'], post('s_region_id'), [], ['values' => []]);
    $h .= form_field('s_province_id', ['label' => 'Province', 'type' => 'province', 'geo_group' => 'sc', 'region_field' => 's_region_id', 'width' => 'third'], post('s_province_id'), [], ['values' => ['s_region_id' => post('s_region_id')]]);
    $h .= form_field('s_city_id', ['label' => 'City or municipality', 'type' => 'city', 'geo_group' => 'sc', 'region_field' => 's_region_id', 'province_field' => 's_province_id', 'width' => 'third'], post('s_city_id'), [], ['values' => ['s_region_id' => post('s_region_id'), 's_province_id' => post('s_province_id')]]);
    $h .= form_field('s_cooperative_id', ['label' => 'Cooperative', 'type' => 'coop'], post('s_cooperative_id'));
    return $h;
}

// ------------------------------------------------------------------ create
if ($action === 'create') {
    require_perm('users.create');
    $errors = [];
    $roles = assignable_roles();
    if (is_post()) {
        $data = ['full_name' => mb_substr(post('full_name'), 0, 150), 'username' => mb_strtolower(mb_substr(post('username'), 0, 60)), 'email' => mb_strtolower(mb_substr(post('email'), 0, 191)), 'phone' => mb_substr(post('phone'), 0, 40)];
        if ($data['full_name'] === '') {
            $errors['full_name'] = 'Enter the full name.';
        }
        if (!preg_match('/^[a-z0-9._\-]{3,60}$/', $data['username'])) {
            $errors['username'] = 'Use 3 to 60 lowercase letters, numbers, dots, dashes or underscores.';
        } elseif (q_val('SELECT id FROM users WHERE username = ?', [$data['username']])) {
            $errors['username'] = 'This username is already taken.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (q_val('SELECT id FROM users WHERE email = ?', [$data['email']])) {
            $errors['email'] = 'Another account already uses this email address.';
        }
        $roleId = post_int('role_id');
        if ($roleId && !in_array($roleId, array_map(fn($r) => (int) $r['id'], $roles), true)) {
            $errors['role_id'] = 'You cannot assign this role.';
        }
        try {
            $scope = scope_from_post();
        } catch (ValidationError $e) {
            $errors['scope_type'] = $e->getMessage();
            $scope = [];
        }
        $mode = post('setup') === 'temp' ? 'temp' : 'invite';
        if (!$errors) {
            $temp = $mode === 'temp' ? random_code(4, 'abcdefghjkmnpqrstuvwxyz') . '-' . random_code(4, '23456789') . '-' . random_code(4, 'ABCDEFGHJKLMNPQRSTUVWXYZ') . '!' : null;
            $newId = tx(function () use ($data, $roleId, $scope, $temp) {
                $id = q_insert('users', $data + ['phone' => $data['phone'] ?: null, 'password_hash' => password_make($temp ?? bin2hex(random_bytes(24))), 'account_status' => 'active',
                    'must_change_password' => 1, 'created_by' => uid(), 'created_at' => now()]);
                if ($roleId) {
                    $active = is_super();
                    q_insert('user_roles', ['user_id' => $id, 'role_id' => $roleId, 'assignment_status' => $active ? 'active' : 'pending', 'assigned_by' => uid(),
                        'approved_by' => $active ? uid() : null, 'approved_at' => $active ? now() : null, 'created_at' => now()]);
                }
                if ($scope) {
                    q_insert('user_scope_assignments', $scope + ['user_id' => $id, 'created_by' => uid(), 'created_at' => now()]);
                }
                return $id;
            });
            audit_log('create', 'users', $newId, 'Created account ' . $data['username'] . ($roleId ? ' with role request #' . $roleId : ''));
            if ($roleId && !is_super()) {
                notify_perm('users.approve_roles', 'approval_pending', 'A role assignment is waiting for approval', '', url('admin/users.php', ['action' => 'view', 'id' => $newId]), null, uid());
            }
            if ($mode === 'invite') {
                $token = create_user_token($newId, 'invite', 72 * 60);
                send_mail($data['email'], 'Your account for the COOP NATCCO Partylist system', "Hello {$data['full_name']},\n\nAn account was created for you. Your username is {$data['username']}.\n\nSet your password within 72 hours using this single-use link:\n"
                    . abs_url('account/reset-password.php', ['token' => $token]), 'security');
                flash('success', 'Account created and an invitation email was sent.');
            } else {
                $_SESSION['temp_pw_once'] = ['user' => $newId, 'pw' => $temp];
                flash('success', 'Account created. Give the temporary password to the user through a secure channel; it must be changed at first sign-in.');
            }
            redirect(url('admin/users.php', ['action' => 'view', 'id' => $newId]));
        }
    }
    admin_start('New user', [['Users', url('admin/users.php')], ['New user', null]]);
    page_bar('New user account', is_super() ? 'Roles you assign are active immediately.' : 'Roles you assign stay pending until another authorized administrator approves them.');
    echo form_error_summary($errors);
    echo '<form method="post" class="record-form" novalidate>' . csrf_field();
    echo '<fieldset class="panel"><legend class="panel-title">Account</legend><div class="grid">'
        . form_field('full_name', ['label' => 'Full name', 'required' => true, 'max' => 150], post('full_name'), $errors)
        . form_field('username', ['label' => 'Username', 'required' => true, 'max' => 60, 'autocomplete' => 'off'], post('username'), $errors)
        . form_field('email', ['label' => 'Email', 'type' => 'email', 'required' => true], post('email'), $errors)
        . form_field('phone', ['label' => 'Phone', 'type' => 'tel', 'max' => 40], post('phone'), $errors)
        . '</div></fieldset>';
    echo '<fieldset class="panel"><legend class="panel-title">Role and scope</legend><div class="grid">'
        . form_field('role_id', ['label' => 'Role', 'type' => 'select', 'options' => array_column($roles, 'name', 'id'), 'placeholder' => '— Assign later —'], post('role_id'), $errors)
        . scope_fields_html() . '</div></fieldset>';
    echo '<fieldset class="panel"><legend class="panel-title">First sign-in</legend><div class="grid">'
        . form_field('setup', ['label' => 'How will the user set a password?', 'type' => 'select', 'options' => ['invite' => 'Email a single-use invitation link (recommended)', 'temp' => 'Show a temporary password to give in person'], 'placeholder' => null, 'width' => 'full'], post('setup', 'invite'))
        . '</div></fieldset><div class="form-actions"><button class="btn btn-primary" type="submit">Create account</button><a class="btn btn-ghost" href="' . e(url('admin/users.php')) . '">Cancel</a></div></form>';
    admin_end();
    exit;
}

// -------------------------------------------------------------------- view
if ($action === 'view') {
    $id = get_int('id');
    $user = q_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$user) {
        abort(404);
    }
    $manage = can_manage_user($user);
    if (is_post()) {
        $do = post('do');
        $self = url('admin/users.php', ['action' => 'view', 'id' => $id]);
        try {
            if ($do === 'role_decision') {
                $ur = q_one("SELECT * FROM user_roles WHERE id = ? AND user_id = ? AND assignment_status = 'pending'", [post_int('ur'), $id]);
                if (!$ur || !can_approve_role_assignment($ur)) {
                    deny('Approve role assignment');
                }
                $ok = post('decision') === 'approve';
                q('UPDATE user_roles SET assignment_status = ?, approved_by = ?, approved_at = ?, updated_at = ? WHERE id = ?', [$ok ? 'active' : 'revoked', uid(), now(), now(), $ur['id']]);
                q("UPDATE approval_requests SET request_status = ?, reviewed_by = ?, reviewed_at = ? WHERE module = 'roles' AND record_id = ? AND request_status = 'pending'", [$ok ? 'approved' : 'rejected', uid(), now(), $ur['id']]);
                $rname = q_val('SELECT name FROM roles WHERE id = ?', [$ur['role_id']]);
                audit_log($ok ? 'role_approved' : 'role_rejected', 'users', $id, ($ok ? 'Approved' : 'Rejected') . " role {$rname} for {$user['username']}");
                if ($ok) {
                    notify_user($id, 'security', "The role {$rname} was added to your account.", '');
                }
                if ($ur['assigned_by'] && (int) $ur['assigned_by'] !== uid()) {
                    notify_user((int) $ur['assigned_by'], 'approval_done', "Role request for {$user['full_name']} was " . ($ok ? 'approved' : 'rejected'), '', $self);
                }
                flash('success', $ok ? 'Role approved.' : 'Role request rejected.');
            } elseif (!$manage) {
                deny('Manage user ' . $id);
            } elseif ($do === 'details' && can('users.edit')) {
                $name = mb_substr(post('full_name'), 0, 150);
                $email = mb_strtolower(mb_substr(post('email'), 0, 191));
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new ValidationError('Enter a name and a valid email address.');
                }
                if (q_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])) {
                    throw new ValidationError('Another account already uses this email address.');
                }
                $new = ['full_name' => $name, 'email' => $email, 'phone' => mb_substr(post('phone'), 0, 40) ?: null, 'notes' => mb_substr(post('notes'), 0, 255) ?: null];
                $changes = diff_changes($user, $new);
                q_update('users', $new + ['updated_at' => now()], $id);
                audit_log('update', 'users', $id, 'Updated account details', 'success', $changes);
                if (isset($changes['email'])) {
                    notify_user($id, 'security', 'The email address of your account was changed by an administrator.', '');
                }
                flash('success', 'Details saved.');
            } elseif ($do === 'role_add' && can('users.assign_roles')) {
                $rid = post_int('role_id');
                if (!in_array($rid, array_map(fn($r) => (int) $r['id'], assignable_roles()), true)) {
                    throw new ValidationError('You cannot assign this role.');
                }
                $active = is_super();
                $existing = q_one('SELECT * FROM user_roles WHERE user_id = ? AND role_id = ?', [$id, $rid]);
                if ($existing && $existing['assignment_status'] !== 'revoked') {
                    throw new ValidationError('The user already has or is waiting for this role.');
                }
                if ($existing) {
                    q('UPDATE user_roles SET assignment_status = ?, assigned_by = ?, approved_by = ?, approved_at = ?, updated_at = ? WHERE id = ?', [$active ? 'active' : 'pending', uid(), $active ? uid() : null, $active ? now() : null, now(), $existing['id']]);
                    $urId = (int) $existing['id'];
                } else {
                    $urId = q_insert('user_roles', ['user_id' => $id, 'role_id' => $rid, 'assignment_status' => $active ? 'active' : 'pending', 'assigned_by' => uid(), 'approved_by' => $active ? uid() : null, 'approved_at' => $active ? now() : null, 'created_at' => now()]);
                }
                $rname = q_val('SELECT name FROM roles WHERE id = ?', [$rid]);
                if (!$active) {
                    q_insert('approval_requests', ['module' => 'roles', 'record_id' => $urId, 'request_type' => 'role_assignment', 'summary' => mb_substr("Role {$rname} for {$user['full_name']}", 0, 255), 'request_status' => 'pending', 'requested_by' => uid(), 'created_at' => now()]);
                    notify_perm('users.approve_roles', 'approval_pending', 'A role assignment is waiting for approval', '', $self, null, uid());
                }
                audit_log($active ? 'role_assigned' : 'role_requested', 'users', $id, ($active ? 'Assigned' : 'Requested') . " role {$rname} for {$user['username']}");
                flash('success', $active ? 'Role assigned.' : 'Role requested. Another administrator must approve it.');
            } elseif ($do === 'role_revoke' && can('users.assign_roles')) {
                $ur = q_one("SELECT * FROM user_roles WHERE id = ? AND user_id = ? AND assignment_status <> 'revoked'", [post_int('ur'), $id]);
                if (!$ur || !in_array((int) $ur['role_id'], array_map(fn($r) => (int) $r['id'], assignable_roles()), true)) {
                    throw new ValidationError('You cannot remove this role.');
                }
                q("UPDATE user_roles SET assignment_status = 'revoked', updated_at = ? WHERE id = ?", [now(), $ur['id']]);
                revoke_user_sessions($id, 'role_changed');
                audit_log('role_revoked', 'users', $id, 'Removed role ' . q_val('SELECT name FROM roles WHERE id = ?', [$ur['role_id']]) . " from {$user['username']}");
                flash('success', 'Role removed. The user was signed out so the change applies immediately.');
            } elseif ($do === 'scope_add' && can('users.assign_roles')) {
                $s = scope_from_post();
                if (!$s) {
                    throw new ValidationError('Choose a scope type.');
                }
                q_insert('user_scope_assignments', $s + ['user_id' => $id, 'created_by' => uid(), 'created_at' => now()]);
                audit_log('scope_added', 'users', $id, 'Added data scope: ' . scope_label_row($s));
                flash('success', 'Data scope added.');
            } elseif ($do === 'scope_remove' && can('users.assign_roles')) {
                $s = q_one('SELECT * FROM user_scope_assignments WHERE id = ? AND user_id = ?', [post_int('sid'), $id]);
                if ($s) {
                    q('DELETE FROM user_scope_assignments WHERE id = ?', [$s['id']]);
                    revoke_user_sessions($id, 'scope_changed');
                    audit_log('scope_removed', 'users', $id, 'Removed data scope: ' . scope_label_row($s));
                }
                flash('success', 'Data scope removed.');
            } elseif ($do === 'status' && can('users.suspend')) {
                $st = post('account_status');
                if (!in_array($st, ['active', 'suspended', 'deactivated'], true)) {
                    throw new ValidationError('Invalid status.');
                }
                q('UPDATE users SET account_status = ?, updated_at = ? WHERE id = ?', [$st, now(), $id]);
                if ($st !== 'active') {
                    revoke_user_sessions($id, 'account_' . $st);
                }
                auth_event('account_' . $st, $id, $user['username'], 'By ' . $me['username']);
                audit_log('account_' . $st, 'users', $id, 'Account ' . $st . ': ' . $user['username']);
                if ($st !== 'active') {
                    security_event('account_' . $st, 'info', "Account {$user['username']} {$st} by {$me['username']}", $id);
                }
                flash('success', 'Account status changed to ' . $st . '.');
            } elseif ($do === 'reset_link' && can('users.edit')) {
                $token = create_user_token($id, 'reset', max(10, setting_int('reset_token_minutes', 30)));
                send_mail($user['email'], 'Password reset requested by an administrator', "Hello {$user['full_name']},\n\nAn administrator started a password reset for your account. Use this single-use link:\n" . abs_url('account/reset-password.php', ['token' => $token]), 'security');
                audit_log('password_reset_sent', 'users', $id, 'Sent password reset link');
                flash('success', 'A password reset link was emailed to the user.');
            } elseif ($do === 'reset_mfa' && can('users.reset_mfa')) {
                q('DELETE FROM mfa_credentials WHERE user_id = ?', [$id]);
                q('UPDATE users SET mfa_enabled = 0, updated_at = ? WHERE id = ?', [now(), $id]);
                revoke_user_sessions($id, 'mfa_reset');
                auth_event('mfa_reset_by_admin', $id, $user['username'], 'By ' . $me['username']);
                audit_log('mfa_reset', 'users', $id, 'Reset multifactor authentication');
                security_event('mfa_reset', 'medium', "MFA reset for {$user['username']} by {$me['username']}", $id);
                notify_user($id, 'security', 'Your multifactor authentication was reset by an administrator.', 'You will be asked to set it up again at your next sign-in.');
                flash('success', 'Multifactor authentication was reset.');
            } elseif ($do === 'revoke_sessions' && can('users.suspend')) {
                $n = revoke_user_sessions($id, 'revoked_by_admin');
                audit_log('revoke_sessions', 'users', $id, "Ended {$n} sessions");
                flash('success', "{$n} session(s) ended.");
            }
        } catch (ValidationError $e) {
            flash('error', $e->getMessage());
        }
        redirect($self);
    }
    $roles = q_all('SELECT ur.*, r.name, r.rank_level, a.full_name AS assigner, b.full_name AS approver FROM user_roles ur JOIN roles r ON r.id = ur.role_id LEFT JOIN users a ON a.id = ur.assigned_by LEFT JOIN users b ON b.id = ur.approved_by WHERE ur.user_id = ? ORDER BY ur.assignment_status, r.rank_level DESC', [$id]);
    $scopes = q_all('SELECT * FROM user_scope_assignments WHERE user_id = ?', [$id]);
    $events = q_all('SELECT * FROM authentication_events WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$id]);
    $sess = (int) q_val('SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL', [$id]);
    $temp = ($_SESSION['temp_pw_once']['user'] ?? 0) === $id ? $_SESSION['temp_pw_once']['pw'] : null;
    unset($_SESSION['temp_pw_once']);

    admin_start($user['full_name'], [['Users', url('admin/users.php')], [$user['full_name'], null]]);
    page_bar($user['full_name'], e($user['username']) . ', ' . e($user['email']), $manage && can('users.edit') ? '<form method="post" data-confirm="Email a password reset link to this user?">' . csrf_field() . '<input type="hidden" name="do" value="reset_link"><button class="btn btn-secondary" type="submit">Send password reset link</button></form>' : '');
    echo flash_render();
    if ($temp) {
        echo '<div class="notice notice-warn" role="status"><strong>Temporary password (shown once):</strong> <span class="code-box">' . e($temp) . '</span></div>';
    }
    if ((int) $user['id'] === uid()) {
        echo '<div class="notice notice-info">This is your own account. Your roles and scope must be changed by another administrator.</div>';
    }
    echo '<div class="status-strip"><span>Status: ' . status_text($user['account_status']) . '</span><span>MFA: ' . ($user['mfa_enabled'] ? status_text('active', 'On') : status_text('inactive', 'Off')) . '</span><span>Last sign-in: ' . e(fmt_datetime($user['last_login_at']) ?: 'Never') . '</span><span>Active sessions: ' . $sess . '</span></div>';
    echo '<div class="two-col"><div>';
    // Roles
    echo '<section class="panel"><h2 class="panel-title">Roles</h2>';
    if (!$roles) {
        echo '<p class="muted">No roles assigned.</p>';
    } else {
        echo '<ul class="mini-list">';
        foreach ($roles as $r) {
            echo '<li><span class="ml-main"><strong>' . e($r['name']) . '</strong> ' . status_text($r['assignment_status']) . '<span class="sub muted small">Requested by ' . e($r['assigner'] ?: 'system') . ($r['approver'] ? ', approved by ' . e($r['approver']) : '') . '</span></span><span class="ml-meta btn-row">';
            if ($r['assignment_status'] === 'pending' && can_approve_role_assignment($r)) {
                echo '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="role_decision"><input type="hidden" name="ur" value="' . (int) $r['id'] . '"><button class="btn btn-primary btn-sm" name="decision" value="approve" type="submit">Approve</button> <button class="btn btn-danger-ghost btn-sm" name="decision" value="reject" type="submit">Reject</button></form>';
            } elseif ($r['assignment_status'] !== 'revoked' && $manage && can('users.assign_roles')) {
                echo '<form method="post" data-confirm="Remove this role? The user will be signed out.">' . csrf_field() . '<input type="hidden" name="do" value="role_revoke"><input type="hidden" name="ur" value="' . (int) $r['id'] . '"><button class="link-btn" type="submit">Remove</button></form>';
            }
            echo '</span></li>';
        }
        echo '</ul>';
    }
    if ($manage && can('users.assign_roles')) {
        echo '<form method="post" class="attach-form">' . csrf_field() . '<input type="hidden" name="do" value="role_add">'
            . form_field('role_id', ['label' => 'Add role', 'type' => 'select', 'options' => array_column(assignable_roles(), 'name', 'id'), 'required' => true], '')
            . '<div class="btn-row"><button class="btn btn-secondary" type="submit">' . (is_super() ? 'Assign role' : 'Request role') . '</button></div></form>';
    }
    echo '</section>';
    // Scope
    echo '<section class="panel"><h2 class="panel-title">Data scope</h2><ul class="mini-list">';
    foreach ($scopes as $s) {
        echo '<li><span class="ml-main">' . e(scope_label_row($s)) . '</span><span class="ml-meta">' . ($manage && can('users.assign_roles') ? '<form method="post" data-confirm="Remove this scope?">' . csrf_field() . '<input type="hidden" name="do" value="scope_remove"><input type="hidden" name="sid" value="' . (int) $s['id'] . '"><button class="link-btn" type="submit">Remove</button></form>' : '') . '</span></li>';
    }
    if (!$scopes) {
        echo '<li><span class="ml-main muted">No scope assigned. ' . (has_role('point_person', $id) || has_role('ambassador', $id) || has_role('focal_person', $id) ? 'Scope comes from the linked assignment record.' : 'The user cannot see scoped records.') . '</span></li>';
    }
    echo '</ul>';
    if ($manage && can('users.assign_roles')) {
        echo '<form method="post" class="record-form">' . csrf_field() . '<input type="hidden" name="do" value="scope_add"><div class="grid">' . scope_fields_html() . '</div><div class="form-actions"><button class="btn btn-secondary" type="submit">Add scope</button></div></form>';
    }
    echo '</section>';
    // Details
    if ($manage && can('users.edit')) {
        echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="do" value="details"><h2 class="panel-title">Account details</h2><div class="grid">'
            . form_field('full_name', ['label' => 'Full name', 'required' => true, 'max' => 150], $user['full_name'])
            . form_field('email', ['label' => 'Email', 'type' => 'email', 'required' => true], $user['email'])
            . form_field('phone', ['label' => 'Phone', 'type' => 'tel', 'max' => 40], $user['phone'])
            . form_field('notes', ['label' => 'Administrative note', 'max' => 255], $user['notes'])
            . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">Save details</button></div></form>';
    }
    echo '</div><div>';
    if ($manage) {
        echo '<section class="panel"><h2 class="panel-title">Account controls</h2><div class="btn-row">';
        if (can('users.suspend')) {
            foreach (['active' => 'Activate', 'suspended' => 'Suspend', 'deactivated' => 'Deactivate'] as $st => $lbl) {
                if ($user['account_status'] !== $st) {
                    echo '<form method="post" data-confirm="' . e($lbl) . ' this account?">' . csrf_field() . '<input type="hidden" name="do" value="status"><input type="hidden" name="account_status" value="' . $st . '"><button class="btn ' . ($st === 'active' ? 'btn-primary' : 'btn-danger-ghost') . ' btn-sm" type="submit">' . $lbl . '</button></form>';
                }
            }
            echo '<form method="post" data-confirm="End all sessions of this user?">' . csrf_field() . '<input type="hidden" name="do" value="revoke_sessions"><button class="btn btn-secondary btn-sm" type="submit">End sessions</button></form>';
        }
        if (can('users.reset_mfa') && $user['mfa_enabled']) {
            echo '<form method="post" data-confirm="Reset multifactor authentication? The user must set it up again.">' . csrf_field() . '<input type="hidden" name="do" value="reset_mfa"><button class="btn btn-secondary btn-sm" type="submit">Reset MFA</button></form>';
        }
        echo '</div></section>';
    }
    echo '<section class="panel"><h2 class="panel-title">Recent sign-in activity</h2><ul class="mini-list">';
    foreach ($events as $ev) {
        echo '<li><span class="ml-main">' . e(label_from_key($ev['event_type'])) . '<span class="sub muted small"> ' . e($ev['ip_address']) . '</span></span><span class="ml-meta">' . e(time_ago($ev['created_at'])) . '</span></li>';
    }
    if (!$events) {
        echo '<li class="muted">No activity.</li>';
    }
    echo '</ul></section></div></div>';
    admin_end();
    exit;
}

// -------------------------------------------------------------------- list
$p = [];
$w = ['1=1'];
if (($q = mb_substr(get('q'), 0, 80)) !== '') {
    $w[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    array_push($p, like_escape($q), like_escape($q), like_escape($q));
}
if (in_array(get('f_status'), ['active', 'suspended', 'deactivated', 'pending'], true)) {
    $w[] = 'u.account_status = ?';
    $p[] = get('f_status');
}
if (get_int('f_role')) {
    $w[] = "EXISTS (SELECT 1 FROM user_roles x WHERE x.user_id = u.id AND x.role_id = ? AND x.assignment_status <> 'revoked')";
    $p[] = get_int('f_role');
}
if (get('f_pending') === '1') {
    $w[] = "EXISTS (SELECT 1 FROM user_roles x WHERE x.user_id = u.id AND x.assignment_status = 'pending')";
}
$where = implode(' AND ', $w);
[$page, $per, $offset] = pager_params(25);
$total = (int) q_val("SELECT COUNT(*) FROM users u WHERE {$where}", $p);
$rows = q_all("SELECT u.*, (SELECT GROUP_CONCAT(CONCAT(r.name, IF(ur.assignment_status = 'pending', ' (pending)', '')) ORDER BY r.rank_level DESC SEPARATOR ', ') FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND ur.assignment_status <> 'revoked') AS role_names
               FROM users u WHERE {$where} ORDER BY u.full_name LIMIT {$per} OFFSET {$offset}", $p);
admin_start('Users', [['Users', null]]);
page_bar('User accounts', fmt_num($total) . ' accounts', can('users.create') ? '<a class="btn btn-primary" href="' . e(url('admin/users.php', ['action' => 'create'])) . '">New user</a>' : '');
echo flash_render();
?>
<form class="filters" method="get" role="search" aria-label="Filter users">
    <div class="filter filter-search"><label for="u_q">Search</label><input type="search" id="u_q" name="q" value="<?= e(get('q')) ?>" placeholder="Name, username or email"></div>
    <div class="filter"><label for="u_r">Role</label><?= select_html('f_role', q_pairs('SELECT id, name FROM roles ORDER BY rank_level DESC'), get('f_role'), ['id' => 'u_r'], 'All roles') ?></div>
    <div class="filter"><label for="u_s">Status</label><?= select_html('f_status', ['active' => 'Active', 'suspended' => 'Suspended', 'deactivated' => 'Deactivated'], get('f_status'), ['id' => 'u_s'], 'All') ?></div>
    <div class="filter"><label for="u_p">Role approval</label><?= select_html('f_pending', ['1' => 'Has pending role'], get('f_pending'), ['id' => 'u_p'], 'Any') ?></div>
    <div class="filter filter-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-ghost" href="<?= e(url('admin/users.php')) ?>">Reset</a></div>
</form>
<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">User</th><th scope="col">Roles</th><th scope="col">Scope</th><th scope="col">MFA</th><th scope="col">Last sign-in</th><th scope="col">Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
    <tr><td data-label="User"><a class="row-link" href="<?= e(url('admin/users.php', ['action' => 'view', 'id' => $r['id']])) ?>"><?= e($r['full_name']) ?></a><span class="sub"><?= e($r['username']) ?>, <?= e($r['email']) ?></span></td>
        <td data-label="Roles"><?= $r['role_names'] ? e($r['role_names']) : '<span class="muted">None</span>' ?></td>
        <td data-label="Scope"><?= e(scope_description((int) $r['id'])) ?></td>
        <td data-label="MFA"><?= $r['mfa_enabled'] ? 'On' : '<span class="muted">Off</span>' ?></td>
        <td data-label="Last sign-in"><?= e(fmt_datetime($r['last_login_at']) ?: 'Never') ?></td>
        <td data-label="Status"><?= status_text($r['account_status']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?= pager_html($total, $page, $per) ?>
<?php admin_end();
