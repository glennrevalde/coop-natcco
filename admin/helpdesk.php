<?php
/**
 * Help desk ticket queue.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';
require APP_ROOT . '/includes/helpdesk.php';

require_perm(['helpdesk.view', 'helpdesk.view_all', 'helpdesk.submit']);
$p = [];
$w = [ticket_visibility_sql($p), 't.archived_at IS NULL'];
$q = mb_substr(get('q'), 0, 80);
if ($q !== '') {
    $w[] = '(t.ticket_no LIKE ? OR t.subject LIKE ? OR t.requester_name LIKE ? OR t.cooperative_name LIKE ?)';
    array_push($p, like_escape($q), like_escape($q), like_escape($q), like_escape($q));
}
if (isset(TICKET_STATUSES[get('f_status')])) {
    $w[] = 't.ticket_status = ?';
    $p[] = get('f_status');
} elseif (get('f_open') === '1') {
    $w[] = "t.ticket_status NOT IN ('resolved','closed')";
}
if (isset(TICKET_PRIORITIES[get('f_priority')])) {
    $w[] = 't.priority = ?';
    $p[] = get('f_priority');
}
if (get_int('f_category')) {
    $w[] = 't.category_id = ?';
    $p[] = get_int('f_category');
}
if (get('f_assigned') === 'me') {
    $w[] = 't.assigned_to = ?';
    $p[] = (int) uid();
} elseif (get('f_assigned') === 'none') {
    $w[] = 't.assigned_to IS NULL';
} elseif (get('f_assigned') === 'mine') {
    $w[] = 't.submitted_by_user_id = ?';
    $p[] = (int) uid();
}
$where = implode(' AND ', $w);
[$page, $per, $offset] = pager_params(25);
$total = (int) q_val("SELECT COUNT(*) FROM helpdesk_tickets t WHERE {$where}", $p);
$rows = q_all("SELECT t.*, c.name AS category_name, u.full_name AS assignee FROM helpdesk_tickets t LEFT JOIN helpdesk_categories c ON c.id = t.category_id LEFT JOIN users u ON u.id = t.assigned_to
               WHERE {$where} ORDER BY FIELD(t.ticket_status,'open','assigned','in_progress','awaiting_response','resolved','closed'), FIELD(t.priority,'urgent','high','normal','low'), t.created_at DESC LIMIT {$per} OFFSET {$offset}", $p);

$actions = '<a class="btn btn-primary" href="' . e(url('helpdesk.php')) . '">New ticket</a>';
if (can('helpdesk.reports')) {
    $actions = '<a class="btn btn-secondary" href="' . e(url('admin/reports.php', ['r' => 'helpdesk'])) . '">Performance report</a>' . $actions;
}
if (can('helpdesk.manage')) {
    $actions = '<a class="btn btn-secondary" href="' . e(url('admin/lookups.php', ['t' => 'helpdesk_categories'])) . '">Categories</a>' . $actions;
}
admin_start('Help desk', [['Help desk', null]]);
page_bar('Help desk tickets', fmt_num($total) . ' tickets' . (can('helpdesk.view_all') ? '' : ' assigned to or submitted by you'), $actions);
echo flash_render();
?>
<form class="filters" method="get" role="search" aria-label="Filter tickets">
    <div class="filter filter-search"><label for="t_q">Search</label><input type="search" id="t_q" name="q" value="<?= e($q) ?>" placeholder="Ticket number, subject, requester"></div>
    <div class="filter"><label for="t_s">Status</label><?= select_html('f_status', TICKET_STATUSES, get('f_status'), ['id' => 't_s'], 'All') ?></div>
    <div class="filter"><label for="t_p">Priority</label><?= select_html('f_priority', TICKET_PRIORITIES, get('f_priority'), ['id' => 't_p'], 'All') ?></div>
    <div class="filter"><label for="t_c">Category</label><?= select_html('f_category', q_pairs('SELECT id, name FROM helpdesk_categories ORDER BY sort_order'), get('f_category'), ['id' => 't_c'], 'All') ?></div>
    <div class="filter"><label for="t_a">Assignment</label><?= select_html('f_assigned', array_filter(['me' => 'Assigned to me', 'none' => can('helpdesk.assign') ? 'Unassigned' : null, 'mine' => 'Submitted by me']), get('f_assigned'), ['id' => 't_a'], 'Any') ?></div>
    <div class="filter filter-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-ghost" href="<?= e(url('admin/helpdesk.php')) ?>">Reset</a></div>
</form>
<?php if (!$rows): ?>
    <div class="empty"><h2>No tickets</h2><p>Tickets matching your filters will appear here.</p></div>
<?php else: ?>
<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Ticket</th><th scope="col">Requester</th><th scope="col">Category</th><th scope="col">Priority</th><th scope="col">Status</th><th scope="col">Assigned to</th><th scope="col">Received</th></tr></thead><tbody>
<?php foreach ($rows as $t): ?>
    <tr><td data-label="Ticket"><a class="row-link" href="<?= e(url('admin/helpdesk-ticket.php', ['id' => $t['id']])) ?>"><?= e($t['subject']) ?></a><span class="sub"><?= e($t['ticket_no']) ?><?= $t['is_confidential'] ? ', confidential' : '' ?></span></td>
        <td data-label="Requester"><?= e($t['requester_name']) ?><span class="sub"><?= e($t['cooperative_name']) ?></span></td>
        <td data-label="Category"><?= e($t['category_name']) ?></td>
        <td data-label="Priority"><?= status_text($t['priority']) ?></td>
        <td data-label="Status"><?= status_text($t['ticket_status'], TICKET_STATUSES[$t['ticket_status']]) ?></td>
        <td data-label="Assigned to"><?= $t['assignee'] ? e($t['assignee']) : '<span class="muted">Unassigned</span>' ?></td>
        <td data-label="Received"><?= e(fmt_date($t['created_at'])) ?><span class="sub"><?= e(time_ago($t['updated_at'] ?: $t['created_at'])) ?></span></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?= pager_html($total, $page, $per) ?>
<?php endif;
admin_end();
