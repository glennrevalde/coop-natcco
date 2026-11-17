<?php
/**
 * Reference lists: cooperative types, content/document/event categories, help desk categories.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';

require_perm(['settings.manage', 'helpdesk.manage']);
$lists = [
    'cooperative_types' => ['Cooperative types', 'settings.manage'],
    'content_categories' => ['Content categories', 'settings.manage'],
    'helpdesk_categories' => ['Help desk categories', 'helpdesk.manage'],
];
$t = isset($lists[get('t')]) ? get('t') : (can('settings.manage') ? 'cooperative_types' : 'helpdesk_categories');
if (!can($lists[$t][1])) {
    deny('Reference list ' . $t);
}
$scopes = ['post' => 'News and updates', 'document' => 'Policies and documents', 'event' => 'Events', 'legislative' => 'Legislative subject areas', 'award' => 'Award categories', 'gallery' => 'Galleries', 'faq' => 'FAQs'];
$users = q_pairs("SELECT DISTINCT u.id, u.full_name FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.assignment_status = 'active' JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'helpdesk.reply' AND u.account_status = 'active' ORDER BY u.full_name");

if (is_post()) {
    $id = post_int('id');
    $name = mb_substr(post('name'), 0, $t === 'helpdesk_categories' ? 120 : 100);
    if ($name === '') {
        flash('error', 'Enter a name.');
        redirect(url('admin/lookups.php', ['t' => $t]));
    }
    $data = ['name' => $name, 'description' => mb_substr(post('description'), 0, 255) ?: null, 'sort_order' => post_int('sort_order'), 'is_active' => post('is_active') === '1' ? 1 : 0];
    if ($t === 'content_categories') {
        $scope = post('scope');
        if (!isset($scopes[$scope])) {
            flash('error', 'Choose where the category is used.');
            redirect(url('admin/lookups.php', ['t' => $t]));
        }
        $data['scope'] = $scope;
        $data['slug'] = slugify($name, 100);
    }
    if ($t === 'helpdesk_categories') {
        $a = post_int('default_assignee_id');
        $data['default_assignee_id'] = isset($users[$a]) ? $a : null;
        $data['default_priority'] = isset(['low' => 1, 'normal' => 1, 'high' => 1, 'urgent' => 1][post('default_priority')]) ? post('default_priority') : 'normal';
    }
    try {
        if ($id) {
            $data['updated_at'] = now();
            q_update($t, $data, $id);
            audit_log('update', $t, $id, 'Updated reference item ' . $name);
        } else {
            $data['created_at'] = now();
            $id = q_insert($t, $data);
            audit_log('create', $t, $id, 'Added reference item ' . $name);
        }
        flash('success', 'Saved.');
    } catch (PDOException $e) {
        flash('error', 'An item with this name already exists.');
    }
    redirect(url('admin/lookups.php', ['t' => $t]));
}
$order = $t === 'content_categories' ? 'scope, sort_order, name' : 'sort_order, name';
$rows = q_all('SELECT * FROM ' . ident($t) . ' ORDER BY ' . $order);
$edit = get_int('edit') ? q_one('SELECT * FROM ' . ident($t) . ' WHERE id = ?', [get_int('edit')]) : null;
admin_start('Reference lists', [['Reference lists', null]]);
page_bar('Reference lists', 'Lists used in forms throughout the system. Turn items off instead of deleting them so older records keep their meaning.');
echo flash_render();
echo '<nav class="tabs-inline" aria-label="Lists">';
foreach ($lists as $k => $l) {
    if (can($l[1])) {
        echo '<a href="' . e(url('admin/lookups.php', ['t' => $k])) . '"' . ($k === $t ? ' aria-current="true"' : '') . '>' . e($l[0]) . '</a>';
    }
}
echo '</nav><div class="two-col"><div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Name</th>' . ($t === 'content_categories' ? '<th scope="col">Used for</th>' : '') . ($t === 'helpdesk_categories' ? '<th scope="col">Default officer</th>' : '') . '<th scope="col" class="num">Order</th><th scope="col">Active</th><th scope="col"><span class="sr-only">Edit</span></th></tr></thead><tbody>';
foreach ($rows as $r) {
    echo '<tr><td data-label="Name"><strong>' . e($r['name']) . '</strong>' . ($r['description'] ? '<span class="sub">' . e($r['description']) . '</span>' : '') . '</td>'
        . ($t === 'content_categories' ? '<td data-label="Used for">' . e($scopes[$r['scope']] ?? $r['scope']) . '</td>' : '')
        . ($t === 'helpdesk_categories' ? '<td data-label="Default officer">' . e($users[$r['default_assignee_id']] ?? 'None') . '</td>' : '')
        . '<td data-label="Order" class="num">' . (int) $r['sort_order'] . '</td><td data-label="Active">' . ($r['is_active'] ? 'Yes' : '<span class="muted">No</span>') . '</td>'
        . '<td class="row-actions"><a href="' . e(url('admin/lookups.php', ['t' => $t, 'edit' => $r['id']])) . '">Edit</a></td></tr>';
}
echo '</tbody></table></div>';
echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) ($edit['id'] ?? 0) . '"><h2 class="panel-title">' . ($edit ? 'Edit item' : 'Add item') . '</h2><div class="grid">'
    . form_field('name', ['label' => 'Name', 'required' => true, 'max' => 120, 'width' => 'full'], $edit['name'] ?? '')
    . ($t === 'content_categories' ? form_field('scope', ['label' => 'Used for', 'type' => 'select', 'options' => $scopes, 'required' => true, 'width' => 'full'], $edit['scope'] ?? '') : '')
    . form_field('description', ['label' => 'Description', 'max' => 255, 'width' => 'full'], $edit['description'] ?? '')
    . ($t === 'helpdesk_categories' ? form_field('default_assignee_id', ['label' => 'Assign new tickets to', 'type' => 'select', 'options' => $users, 'placeholder' => 'Nobody (triage queue)', 'width' => 'full'], $edit['default_assignee_id'] ?? '')
        . form_field('default_priority', ['label' => 'Default priority', 'type' => 'select', 'options' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'], 'placeholder' => null, 'width' => 'full'], $edit['default_priority'] ?? 'normal') : '')
    . form_field('sort_order', ['label' => 'Order', 'type' => 'number', 'width' => 'full'], $edit['sort_order'] ?? 0)
    . form_field('is_active', ['label' => 'Active', 'type' => 'bool', 'width' => 'full'], $edit['is_active'] ?? 1)
    . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">Save</button>' . ($edit ? '<a class="btn btn-ghost" href="' . e(url('admin/lookups.php', ['t' => $t])) . '">Cancel</a>' : '') . '</div></form></div>';
admin_end();
