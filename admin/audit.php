<?php
/**
 * Audit log (read-only). Entries are hash-chained; the application never edits or deletes them.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';

require_perm('audit.view');
audit_log('view', 'audit', null, 'Opened the audit log');
$p = [];
$w = ['1=1'];
if (($q = mb_substr(get('q'), 0, 80)) !== '') {
    $w[] = '(a.description LIKE ? OR a.actor_name LIKE ?)';
    array_push($p, like_escape($q), like_escape($q));
}
if (get('module') !== '') {
    $w[] = 'a.module = ?';
    $p[] = mb_substr(get('module'), 0, 60);
}
if (get('act') !== '') {
    $w[] = 'a.action = ?';
    $p[] = mb_substr(get('act'), 0, 60);
}
if (in_array(get('outcome'), ['success', 'failure', 'denied'], true)) {
    $w[] = 'a.outcome = ?';
    $p[] = get('outcome');
}
if (valid_date(get('from'))) {
    $w[] = 'a.created_at >= ?';
    $p[] = get('from') . ' 00:00:00';
}
if (valid_date(get('to'))) {
    $w[] = 'a.created_at <= ?';
    $p[] = get('to') . ' 23:59:59';
}
if (get_int('record')) {
    $w[] = 'a.record_id = ?';
    $p[] = get_int('record');
}
$where = implode(' AND ', $w);
[$page, $per, $offset] = pager_params(50);
$total = (int) q_val("SELECT COUNT(*) FROM audit_logs a WHERE {$where}", $p);
$rows = q_all("SELECT a.* FROM audit_logs a WHERE {$where} ORDER BY a.id DESC LIMIT {$per} OFFSET {$offset}", $p);
admin_start('Audit log', [['Audit log', null]]);
page_bar('Audit log', fmt_num($total) . ' entries. Read-only and hash-chained.', '<button class="btn btn-secondary" type="button" data-print>Print</button>');
?>
<form class="filters" method="get" role="search" aria-label="Filter the audit log">
    <div class="filter filter-search"><label for="a_q">Search</label><input type="search" id="a_q" name="q" value="<?= e(get('q')) ?>" placeholder="Description or user"></div>
    <div class="filter"><label for="a_m">Module</label><?= select_html('module', q_pairs('SELECT DISTINCT module, module FROM audit_logs ORDER BY module'), get('module'), ['id' => 'a_m'], 'All') ?></div>
    <div class="filter"><label for="a_a">Action</label><?= select_html('act', q_pairs('SELECT DISTINCT action, action FROM audit_logs ORDER BY action'), get('act'), ['id' => 'a_a'], 'All') ?></div>
    <div class="filter"><label for="a_o">Outcome</label><?= select_html('outcome', ['success' => 'Success', 'failure' => 'Failure', 'denied' => 'Denied'], get('outcome'), ['id' => 'a_o'], 'All') ?></div>
    <div class="filter"><label for="a_f">From</label><input type="date" id="a_f" name="from" value="<?= e(get('from')) ?>"></div>
    <div class="filter"><label for="a_t">To</label><input type="date" id="a_t" name="to" value="<?= e(get('to')) ?>"></div>
    <div class="filter filter-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-ghost" href="<?= e(url('admin/audit.php')) ?>">Reset</a></div>
</form>
<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">When</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Resource</th><th scope="col">Outcome</th><th scope="col">IP address</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
    <tr><td data-label="When"><?= e(fmt_datetime($r['created_at'])) ?><span class="sub">#<?= (int) $r['id'] ?></span></td>
        <td data-label="Actor"><?= e($r['actor_name']) ?></td>
        <td data-label="Action"><strong><?= e(label_from_key($r['action'])) ?></strong><span class="sub"><?= e($r['description']) ?></span></td>
        <td data-label="Resource"><?= e(label_from_key($r['module'])) ?><?= $r['record_id'] ? ' #' . (int) $r['record_id'] : '' ?></td>
        <td data-label="Outcome"><?= status_text($r['outcome']) ?></td>
        <td data-label="IP"><?= e($r['ip_address']) ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="muted">No entries match.</td></tr><?php endif; ?>
</tbody></table></div>
<?= pager_html($total, $page, $per) ?>
<?php admin_end();
