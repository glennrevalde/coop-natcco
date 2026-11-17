<?php
/**
 * Roles and permissions (configurable to the organization's approved access policy).
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';

require_perm('roles.manage');
$scopeOpts = ['global' => 'Global (all records)', 'national' => 'National', 'region' => 'Region', 'province' => 'Province', 'city' => 'City or municipality', 'cooperative' => 'Cooperative', 'assignment' => 'From assignment record', 'none' => 'Not geographic'];
$roleId = get_int('id');

if (is_post()) {
    $do = post('do');
    if ($do === 'create') {
        $slug = slugify(post('name'), 50);
        $slug = str_replace('-', '_', $slug);
        $name = mb_substr(post('name'), 0, 100);
        $rank = max(1, min(95, post_int('rank_level', 10)));
        if ($name === '' || q_val('SELECT id FROM roles WHERE slug = ?', [$slug])) {
            flash('error', 'Enter a unique role name.');
            redirect('admin/roles.php');
        }
        $id = q_insert('roles', ['slug' => $slug, 'name' => $name, 'description' => mb_substr(post('description'), 0, 255) ?: null, 'scope_level' => isset($scopeOpts[post('scope_level')]) && post('scope_level') !== 'global' ? post('scope_level') : 'none',
            'rank_level' => $rank, 'is_privileged' => post('is_privileged') === '1' ? 1 : 0, 'is_system' => 0, 'created_at' => now()]);
        audit_log('role_created', 'roles', $id, 'Created role ' . $name);
        flash('success', 'Role created. Now choose its permissions.');
        redirect(url('admin/roles.php', ['id' => $id]));
    }
    if ($do === 'perms') {
        $role = q_one('SELECT * FROM roles WHERE id = ?', [$roleId]);
        if (!$role || $role['slug'] === 'super_admin') {
            flash('error', 'The Super Administrator role always has every permission.');
            redirect('admin/roles.php');
        }
        $chosen = array_map('intval', (array) ($_POST['perm'] ?? []));
        $valid = array_map('intval', q_col('SELECT id FROM permissions'));
        $chosen = array_values(array_intersect($chosen, $valid));
        $before = q_col('SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [$roleId]);
        tx(function () use ($roleId, $chosen) {
            q('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
            foreach ($chosen as $pid) {
                q_insert('role_permissions', ['role_id' => $roleId, 'permission_id' => $pid]);
            }
            q('UPDATE roles SET updated_at = ? WHERE id = ?', [now(), $roleId]);
        });
        $after = q_col('SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [$roleId]);
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));
        audit_log('permissions_changed', 'roles', $roleId, 'Changed permissions of role ' . $role['name'] . ': +' . count($added) . ' -' . count($removed), 'success', ['added' => $added, 'removed' => $removed]);
        if ($added || $removed) {
            security_event('role_permissions_changed', 'medium', 'Permissions of role ' . $role['name'] . ' changed (' . count($added) . ' added, ' . count($removed) . ' removed)');
        }
        $meta = ['name' => mb_substr(post('name'), 0, 100) ?: $role['name'], 'description' => mb_substr(post('description'), 0, 255) ?: null, 'rank_level' => max(1, min(95, post_int('rank_level', (int) $role['rank_level']))),
            'is_privileged' => post('is_privileged') === '1' ? 1 : 0];
        if (isset($scopeOpts[post('scope_level')]) && post('scope_level') !== 'global') {
            $meta['scope_level'] = post('scope_level');
        }
        q_update('roles', $meta, $roleId);
        flash('success', 'Role saved.');
        redirect(url('admin/roles.php', ['id' => $roleId]));
    }
}

if ($roleId) {
    $role = q_one('SELECT * FROM roles WHERE id = ?', [$roleId]);
    if (!$role) {
        abort(404);
    }
    $perms = q_all('SELECT * FROM permissions ORDER BY module, id');
    $has = array_flip(q_col('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$roleId]));
    $locked = $role['slug'] === 'super_admin';
    admin_start($role['name'], [['Roles and permissions', url('admin/roles.php')], [$role['name'], null]]);
    page_bar($role['name'], e($role['description'] ?? ''));
    echo flash_render();
    if ($locked) {
        echo '<div class="notice notice-info">The Super Administrator role always has every permission and cannot be reduced.</div>';
    }
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="perms">';
    echo '<fieldset class="panel"><legend class="panel-title">Role</legend><div class="grid">'
        . form_field('name', ['label' => 'Name', 'required' => true, 'max' => 100], $role['name'])
        . form_field('description', ['label' => 'Core responsibility', 'max' => 255], $role['description'])
        . form_field('scope_level', ['label' => 'Expected data scope', 'type' => 'select', 'options' => $scopeOpts, 'placeholder' => null], $role['scope_level'])
        . form_field('rank_level', ['label' => 'Rank (higher can manage lower)', 'type' => 'number', 'min' => 1, 'max_value' => 95, 'help' => 'Administrators can only assign roles ranked below their own.'], $role['rank_level'])
        . form_field('is_privileged', ['label' => 'Privileged role (requires multifactor authentication)', 'type' => 'bool'], $role['is_privileged'])
        . '</div></fieldset>';
    echo '<section class="panel"><h2 class="panel-title">Permissions</h2><p class="muted small">Each action is checked on the server. Hiding a button is never the only control.</p>';
    $byModule = [];
    foreach ($perms as $p) {
        $byModule[$p['module']][] = $p;
    }
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Area</th><th scope="col">Permissions</th></tr></thead><tbody>';
    foreach ($byModule as $mod => $list) {
        echo '<tr><td data-label="Area"><strong>' . e(label_from_key($mod)) . '</strong></td><td data-label="Permissions"><div class="perm-list">';
        foreach ($list as $p) {
            echo '<label class="check"><input type="checkbox" name="perm[]" value="' . (int) $p['id'] . '"' . (isset($has[$p['id']]) ? ' checked' : '') . ($locked ? ' disabled' : '') . '> <span>' . e($p['label']) . ($p['is_sensitive'] ? ' <span class="st st-warn">Sensitive</span>' : '') . '</span></label>';
        }
        echo '</div></td></tr>';
    }
    echo '</tbody></table></div></section>';
    if (!$locked) {
        echo '<div class="form-actions"><button class="btn btn-primary" type="submit">Save role</button><a class="btn btn-ghost" href="' . e(url('admin/roles.php')) . '">Cancel</a></div>';
    }
    echo '</form>';
    admin_end();
    exit;
}

$roles = q_all("SELECT r.*, (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id AND ur.assignment_status = 'active') AS user_count FROM roles r ORDER BY r.rank_level DESC, r.name");
admin_start('Roles and permissions', [['Roles and permissions', null]]);
page_bar('Roles and permissions', 'Proposed system roles. Adjust them to the approved access policy.');
echo flash_render();
echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Role</th><th scope="col">Data scope</th><th scope="col" class="num">Rank</th><th scope="col" class="num">Permissions</th><th scope="col" class="num">Users</th><th scope="col">MFA required</th></tr></thead><tbody>';
foreach ($roles as $r) {
    echo '<tr><td data-label="Role"><a class="row-link" href="' . e(url('admin/roles.php', ['id' => $r['id']])) . '">' . e($r['name']) . '</a><span class="sub">' . e($r['description']) . '</span></td><td data-label="Scope">' . e($scopeOpts[$r['scope_level']]) . '</td>'
        . '<td data-label="Rank" class="num">' . (int) $r['rank_level'] . '</td><td data-label="Permissions" class="num">' . (int) $r['perm_count'] . '</td><td data-label="Users" class="num">' . (int) $r['user_count'] . '</td><td data-label="MFA">' . ($r['is_privileged'] ? 'Yes' : 'No') . '</td></tr>';
}
echo '</tbody></table></div>';
echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="do" value="create"><h2 class="panel-title">Add a role</h2><div class="grid">'
    . form_field('name', ['label' => 'Role name', 'required' => true, 'max' => 100], '')
    . form_field('description', ['label' => 'Core responsibility', 'max' => 255], '')
    . form_field('scope_level', ['label' => 'Expected data scope', 'type' => 'select', 'options' => array_diff_key($scopeOpts, ['global' => 1]), 'placeholder' => null], 'none')
    . form_field('rank_level', ['label' => 'Rank', 'type' => 'number', 'min' => 1, 'max_value' => 95], 10)
    . form_field('is_privileged', ['label' => 'Privileged (requires MFA)', 'type' => 'bool'], 0)
    . '</div><div class="form-actions"><button class="btn btn-secondary" type="submit">Create role</button></div></form>';
admin_end();
