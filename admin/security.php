<?php
/**
 * Security monitoring: authentication events, alerts, lockouts, permission changes,
 * sensitive exports, imports and email delivery. Minimal personal data is shown.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';

require_perm('security.view');
$tab = in_array(get('tab'), ['overview', 'alerts', 'auth', 'permissions', 'exports', 'imports', 'email'], true) ? get('tab') : 'overview';

if (is_post() && post('do') === 'review') {
    require_perm('security.manage');
    $st = in_array(post('event_status'), ['reviewed', 'dismissed', 'escalated'], true) ? post('event_status') : 'reviewed';
    $id = post_int('event');
    q('UPDATE security_events SET event_status = ?, reviewed_by = ?, reviewed_at = ?, review_notes = ? WHERE id = ?', [$st, uid(), now(), mb_substr(post('notes'), 0, 255) ?: null, $id]);
    audit_log('alert_' . $st, 'security', $id, 'Security event #' . $id . ' marked ' . $st);
    if ($st === 'escalated' && can('security.manage')) {
        flash('success', 'Alert escalated. Record the incident details.');
        redirect(url('admin/incidents.php', ['action' => 'create']));
    }
    flash('success', 'Alert updated.');
    redirect(url('admin/security.php', ['tab' => 'alerts']));
}

$from = valid_date(get('from')) ? get('from') : date('Y-m-d', strtotime('-30 days'));
$to = valid_date(get('to')) ? get('to') : today();
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];
[$page, $per, $offset] = pager_params(50);

admin_start('Security monitoring', [['Security monitoring', null]]);
page_bar('Security monitoring', 'Authentication, alerts and sensitive activity from ' . e(fmt_date($from)) . ' to ' . e(fmt_date($to)) . '.');
echo flash_render();
echo '<nav class="tabs-inline" aria-label="Security sections">';
foreach (['overview' => 'Overview', 'alerts' => 'Alerts', 'auth' => 'Sign-in events', 'permissions' => 'Permission changes', 'exports' => 'Exports', 'imports' => 'Imports', 'email' => 'Email delivery'] as $k => $l) {
    echo '<a href="' . e(url('admin/security.php', ['tab' => $k, 'from' => get('from'), 'to' => get('to')])) . '"' . ($k === $tab ? ' aria-current="true"' : '') . '>' . $l . '</a>';
}
echo '</nav>';
echo '<form class="filters" method="get"><input type="hidden" name="tab" value="' . e($tab) . '">'
    . '<div class="filter"><label for="s_from">From</label><input type="date" id="s_from" name="from" value="' . e($from) . '"></div>'
    . '<div class="filter"><label for="s_to">To</label><input type="date" id="s_to" name="to" value="' . e($to) . '"></div>'
    . ($tab === 'alerts' ? '<div class="filter"><label for="s_sev">Severity</label>' . select_html('sev', ['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'info' => 'Info'], get('sev'), ['id' => 's_sev'], 'All') . '</div>'
        . '<div class="filter"><label for="s_st">Status</label>' . select_html('st', ['open' => 'Open', 'reviewed' => 'Reviewed', 'dismissed' => 'Dismissed', 'escalated' => 'Escalated'], get('st'), ['id' => 's_st'], 'All') . '</div>' : '')
    . ($tab === 'auth' ? '<div class="filter"><label for="s_type">Event</label>' . select_html('type', q_pairs('SELECT DISTINCT event_type, event_type FROM authentication_events ORDER BY event_type'), get('type'), ['id' => 's_type'], 'All') . '</div>'
        . '<div class="filter"><label for="s_ip">IP address</label><input type="search" id="s_ip" name="ip" value="' . e(get('ip')) . '" maxlength="45"></div>' : '')
    . '<div class="filter filter-actions"><button class="btn btn-primary" type="submit">Apply</button></div></form>';

if ($tab === 'overview') {
    $failed24 = (int) q_val("SELECT COUNT(*) FROM authentication_events WHERE event_type = 'login_failed' AND created_at >= ?", [date('Y-m-d H:i:s', time() - 86400)]);
    $locks = (int) q_val("SELECT COUNT(*) FROM authentication_events WHERE event_type = 'account_locked' AND created_at BETWEEN ? AND ?", $range);
    $open = (int) q_val("SELECT COUNT(*) FROM security_events WHERE event_status = 'open'");
    $sens = (int) q_val('SELECT COUNT(*) FROM export_logs WHERE is_sensitive = 1 AND created_at BETWEEN ? AND ?', $range);
    $sessions = (int) q_val('SELECT COUNT(*) FROM user_sessions WHERE revoked_at IS NULL AND last_activity_at >= ?', [date('Y-m-d H:i:s', time() - setting_int('session_idle_minutes', 30) * 60)]);
    $noMfa = (int) q_val("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.assignment_status = 'active' JOIN roles r ON r.id = ur.role_id WHERE r.is_privileged = 1 AND u.mfa_enabled = 0 AND u.account_status = 'active'");
    [$checked, $broken] = audit_verify_chain(5000);
    $cards = [['Failed sign-ins, last 24 hours', $failed24, 'auth', $failed24 > 20 ? 'kpi-alert' : ''], ['Account lockouts in range', $locks, 'auth', $locks ? 'kpi-attn' : ''], ['Open security alerts', $open, 'alerts', $open ? 'kpi-alert' : ''],
        ['Sensitive exports in range', $sens, 'exports', ''], ['Active sessions now', $sessions, 'auth', ''], ['Privileged users without MFA', $noMfa, 'auth', $noMfa ? 'kpi-attn' : '']];
    echo '<div class="kpis">';
    foreach ($cards as $c) {
        echo '<a class="kpi ' . $c[3] . '" href="' . e(url('admin/security.php', ['tab' => $c[2]])) . '"><span class="kpi-top">' . e($c[0]) . icon('shield') . '</span><span class="kpi-num">' . fmt_num($c[1]) . '</span></a>';
    }
    echo '</div>';
    echo '<section class="panel"><h2 class="panel-title">Audit log integrity</h2>' . ($broken === null
        ? '<p>' . status_text('verified', 'Intact') . ' The hash chain of the latest ' . fmt_num($checked) . ' audit entries is unbroken.</p>'
        : '<p>' . status_text('failed', 'Broken') . ' The audit chain does not match at entry #' . (int) $broken . '. Investigate possible tampering and record an incident.</p>') . '</section>';
    $top = q_all("SELECT ip_address, COUNT(*) n FROM authentication_events WHERE event_type = 'login_failed' AND created_at BETWEEN ? AND ? GROUP BY ip_address ORDER BY n DESC LIMIT 8", $range);
    echo '<section class="panel"><h2 class="panel-title">Most failed sign-ins by IP address</h2><ul class="mini-list">';
    foreach ($top as $t) {
        echo '<li><span class="ml-main"><a href="' . e(url('admin/security.php', ['tab' => 'auth', 'ip' => $t['ip_address'], 'type' => 'login_failed'])) . '">' . e($t['ip_address']) . '</a></span><span class="ml-meta">' . fmt_num($t['n']) . ' failures</span></li>';
    }
    if (!$top) {
        echo '<li class="muted">No failed sign-ins in this period.</li>';
    }
    echo '</ul></section>';
    if (!is_production() || !is_https()) {
        echo '<div class="notice notice-warn">Deployment check: ' . (!is_https() ? 'this site is not served over HTTPS. ' : '') . (!is_production() ? 'app_env is not set to production. ' : '') . 'See docs/DEPLOYMENT.md before going live.</div>';
    }
} elseif ($tab === 'alerts') {
    $p = $range;
    $w = 'e.created_at BETWEEN ? AND ?';
    if (in_array(get('sev'), ['critical', 'high', 'medium', 'low', 'info'], true)) {
        $w .= ' AND e.severity = ?';
        $p[] = get('sev');
    }
    if (in_array(get('st'), ['open', 'reviewed', 'dismissed', 'escalated'], true)) {
        $w .= ' AND e.event_status = ?';
        $p[] = get('st');
    }
    $total = (int) q_val("SELECT COUNT(*) FROM security_events e WHERE {$w}", $p);
    $rows = q_all("SELECT e.*, u.username, r.full_name AS reviewer FROM security_events e LEFT JOIN users u ON u.id = e.user_id LEFT JOIN users r ON r.id = e.reviewed_by WHERE {$w} ORDER BY e.id DESC LIMIT {$per} OFFSET {$offset}", $p);
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Alert</th><th scope="col">Severity</th><th scope="col">When</th><th scope="col">Status</th><th scope="col">Review</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td data-label="Alert"><strong>' . e(label_from_key($r['event_type'])) . '</strong><span class="sub">' . e($r['description']) . ($r['username'] ? ', user ' . e($r['username']) : '') . ($r['ip_address'] ? ', IP ' . e($r['ip_address']) : '') . '</span></td>'
            . '<td data-label="Severity">' . status_text($r['severity']) . '</td><td data-label="When">' . e(fmt_datetime($r['created_at'])) . '</td>'
            . '<td data-label="Status">' . status_text($r['event_status']) . ($r['reviewer'] ? '<span class="sub">' . e($r['reviewer']) . ($r['review_notes'] ? ': ' . e($r['review_notes']) : '') . '</span>' : '') . '</td><td data-label="Review">';
        if ($r['event_status'] === 'open' && can('security.manage')) {
            echo '<form method="post" class="btn-row">' . csrf_field() . '<input type="hidden" name="do" value="review"><input type="hidden" name="event" value="' . (int) $r['id'] . '"><input type="text" name="notes" maxlength="255" placeholder="Notes" aria-label="Review notes">'
                . '<button class="btn btn-secondary btn-sm" name="event_status" value="reviewed" type="submit">Reviewed</button><button class="btn btn-ghost btn-sm" name="event_status" value="dismissed" type="submit">Dismiss</button><button class="btn btn-danger-ghost btn-sm" name="event_status" value="escalated" type="submit">Escalate</button></form>';
        }
        echo '</td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="5" class="muted">No alerts in this period.</td></tr>';
    }
    echo '</tbody></table></div>' . pager_html($total, $page, $per);
} elseif ($tab === 'auth') {
    $p = $range;
    $w = 'a.created_at BETWEEN ? AND ?';
    if (get('type') !== '') {
        $w .= ' AND a.event_type = ?';
        $p[] = mb_substr(get('type'), 0, 40);
    }
    if (get('ip') !== '') {
        $w .= ' AND a.ip_address = ?';
        $p[] = mb_substr(get('ip'), 0, 45);
    }
    $total = (int) q_val("SELECT COUNT(*) FROM authentication_events a WHERE {$w}", $p);
    $rows = q_all("SELECT a.*, u.username FROM authentication_events a LEFT JOIN users u ON u.id = a.user_id WHERE {$w} ORDER BY a.id DESC LIMIT {$per} OFFSET {$offset}", $p);
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">When</th><th scope="col">Event</th><th scope="col">Account</th><th scope="col">IP address</th><th scope="col">Details</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $bad = in_array($r['event_type'], ['login_failed', 'account_locked', 'mfa_failed', 'login_blocked', 'mfa_locked'], true);
        echo '<tr><td data-label="When">' . e(fmt_datetime($r['created_at'])) . '</td><td data-label="Event">' . status_text($bad ? 'failed' : 'success', label_from_key($r['event_type'])) . '</td>'
            . '<td data-label="Account">' . e($r['username'] ?: $r['identifier']) . '</td><td data-label="IP">' . e($r['ip_address']) . '</td><td data-label="Details">' . e($r['details']) . '</td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="5" class="muted">No events in this period.</td></tr>';
    }
    echo '</tbody></table></div>' . pager_html($total, $page, $per);
} elseif ($tab === 'permissions') {
    $actions = ['role_assigned', 'role_requested', 'role_approved', 'role_rejected', 'role_revoked', 'scope_added', 'scope_removed', 'permissions_changed', 'role_created', 'account_suspended', 'account_deactivated', 'account_active', 'mfa_reset', 'settings_changed'];
    $ph = implode(',', array_fill(0, count($actions), '?'));
    $p = array_merge($range, $actions);
    $total = (int) q_val("SELECT COUNT(*) FROM audit_logs a WHERE a.created_at BETWEEN ? AND ? AND a.action IN ({$ph})", $p);
    $rows = q_all("SELECT a.* FROM audit_logs a WHERE a.created_at BETWEEN ? AND ? AND a.action IN ({$ph}) ORDER BY a.id DESC LIMIT {$per} OFFSET {$offset}", $p);
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">When</th><th scope="col">Change</th><th scope="col">By</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td data-label="When">' . e(fmt_datetime($r['created_at'])) . '</td><td data-label="Change"><strong>' . e(label_from_key($r['action'])) . '</strong><span class="sub">' . e($r['description']) . '</span></td><td data-label="By">' . e($r['actor_name']) . '</td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="3" class="muted">No permission changes in this period.</td></tr>';
    }
    echo '</tbody></table></div>' . pager_html($total, $page, $per);
} elseif ($tab === 'exports') {
    $total = (int) q_val('SELECT COUNT(*) FROM export_logs WHERE created_at BETWEEN ? AND ?', $range);
    $rows = q_all("SELECT x.*, u.username FROM export_logs x LEFT JOIN users u ON u.id = x.user_id WHERE x.created_at BETWEEN ? AND ? ORDER BY x.id DESC LIMIT {$per} OFFSET {$offset}", $range);
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">When</th><th scope="col">Module</th><th scope="col" class="num">Rows</th><th scope="col">Sensitive</th><th scope="col">By</th><th scope="col">Filters</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td data-label="When">' . e(fmt_datetime($r['created_at'])) . '</td><td data-label="Module">' . e(label_from_key($r['module'])) . '</td><td data-label="Rows" class="num">' . fmt_num($r['row_count']) . '</td>'
            . '<td data-label="Sensitive">' . ($r['is_sensitive'] ? status_text('high', 'Yes') : 'No') . '</td><td data-label="By">' . e($r['username']) . '<span class="sub">' . e($r['ip_address']) . '</span></td><td data-label="Filters">' . e($r['filters']) . '</td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="6" class="muted">No exports in this period.</td></tr>';
    }
    echo '</tbody></table></div>' . pager_html($total, $page, $per);
} elseif ($tab === 'imports') {
    $total = (int) q_val('SELECT COUNT(*) FROM import_batches WHERE created_at BETWEEN ? AND ?', $range);
    $rows = q_all("SELECT b.*, u.username, a.username AS approver FROM import_batches b LEFT JOIN users u ON u.id = b.uploaded_by LEFT JOIN users a ON a.id = b.approved_by WHERE b.created_at BETWEEN ? AND ? ORDER BY b.id DESC LIMIT {$per} OFFSET {$offset}", $range);
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Batch</th><th scope="col">Module</th><th scope="col" class="num">Rows</th><th scope="col" class="num">Invalid</th><th scope="col" class="num">Imported</th><th scope="col">Status</th><th scope="col">Uploaded / approved</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $odd = $r['total_rows'] > 0 && $r['invalid_rows'] / max(1, $r['total_rows']) > .5;
        echo '<tr><td data-label="Batch">#' . (int) $r['id'] . '<span class="sub">' . e($r['original_name']) . '</span></td><td data-label="Module">' . e(label_from_key($r['module'])) . '</td><td data-label="Rows" class="num">' . fmt_num($r['total_rows']) . '</td>'
            . '<td data-label="Invalid" class="num">' . ($odd ? status_text('failed', fmt_num($r['invalid_rows'])) : fmt_num($r['invalid_rows'])) . '</td><td data-label="Imported" class="num">' . fmt_num($r['imported_rows']) . '</td><td data-label="Status">' . e(label_from_key($r['batch_status'])) . '</td>'
            . '<td data-label="By">' . e($r['username']) . ($r['approver'] ? ' / ' . e($r['approver']) : '') . '<span class="sub">' . e(fmt_datetime($r['created_at'])) . '</span></td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="7" class="muted">No imports in this period.</td></tr>';
    }
    echo '</tbody></table></div>' . pager_html($total, $page, $per);
} else {
    $total = (int) q_val('SELECT COUNT(*) FROM email_log WHERE created_at BETWEEN ? AND ?', $range);
    $rows = q_all("SELECT * FROM email_log WHERE created_at BETWEEN ? AND ? ORDER BY id DESC LIMIT {$per} OFFSET {$offset}", $range);
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">When</th><th scope="col">Recipient</th><th scope="col">Subject</th><th scope="col">Result</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td data-label="When">' . e(fmt_datetime($r['created_at'])) . '</td><td data-label="Recipient">' . e($r['recipient_masked']) . '</td><td data-label="Subject">' . e($r['subject']) . '</td>'
            . '<td data-label="Result">' . status_text(['sent' => 'sent', 'logged' => 'info', 'failed' => 'failed', 'skipped' => 'inactive'][$r['delivery_status']] ?? 'info', ucfirst($r['delivery_status'])) . ($r['error_message'] ? '<span class="sub">' . e($r['error_message']) . '</span>' : '') . '</td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="4" class="muted">No emails in this period.</td></tr>';
    }
    echo '</tbody></table></div>' . pager_html($total, $page, $per);
}
admin_end();
