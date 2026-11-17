<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';

$u = require_login();
$id = (int) $u['id'];
if (is_post()) {
    if (post('do') === 'read_all') {
        q('UPDATE notifications SET is_read = 1, read_at = ? WHERE user_id = ? AND is_read = 0', [now(), $id]);
        flash('success', 'All notifications marked as read.');
        redirect('admin/notifications.php');
    }
    if (post('do') === 'open') {
        $n = q_one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [post_int('n'), $id]);
        if ($n) {
            q('UPDATE notifications SET is_read = 1, read_at = ? WHERE id = ?', [now(), $n['id']]);
            redirect(safe_return($n['link'] ?: null, 'admin/notifications.php'));
        }
        redirect('admin/notifications.php');
    }
}
[$page, $per, $offset] = pager_params(25);
$total = (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$id]);
$rows = q_all("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT {$per} OFFSET {$offset}", [$id]);
admin_start('Notifications', [['Notifications', null]]);
page_bar('Notifications', fmt_num(unread_notifications()) . ' unread', '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="read_all"><button class="btn btn-secondary" type="submit">Mark all as read</button></form><a class="btn btn-ghost" href="' . e(url('account/profile.php')) . '">Preferences</a>');
echo flash_render();
if (!$rows) {
    echo '<div class="empty"><h2>No notifications</h2><p>Updates about approvals, tickets, events and your account will appear here.</p></div>';
} else {
    echo '<section class="panel"><ul class="mini-list">';
    foreach ($rows as $n) {
        echo '<li><span class="ml-main"><form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="do" value="open"><input type="hidden" name="n" value="' . (int) $n['id'] . '">'
            . '<button type="submit" class="link-plain">' . ($n['is_read'] ? '' : '<strong>') . e($n['title']) . ($n['is_read'] ? '' : '</strong>') . '</button></form>'
            . ($n['body'] ? '<span class="sub muted small"> ' . e($n['body']) . '</span>' : '') . '</span><span class="ml-meta">' . ($n['is_read'] ? '' : status_text('new', 'New') . ' ') . e(time_ago($n['created_at'])) . '</span></li>';
    }
    echo '</ul></section>' . pager_html($total, $page, $per);
}
admin_end();
