<?php
/**
 * Daily maintenance (schedule once a day):
 *  - remove expired tokens, throttles and old ended sessions
 *  - notify users about organizational events starting within 3 days
 *  - apply privacy retention to closed tickets, past event registrations and contact messages
 *  - flag member records past their retention period for Data Protection Officer review
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$out = [];
$out[] = 'Expired tokens removed: ' . q('DELETE FROM password_reset_tokens WHERE expires_at < ? OR used_at IS NOT NULL AND used_at < ?', [now(), date('Y-m-d H:i:s', strtotime('-7 days'))])->rowCount();
$out[] = 'Old throttles removed: ' . q('DELETE FROM throttles WHERE last_attempt_at < ? AND (locked_until IS NULL OR locked_until < ?)', [date('Y-m-d H:i:s', strtotime('-2 days')), now()])->rowCount();
$out[] = 'Old sessions removed: ' . q('DELETE FROM user_sessions WHERE revoked_at IS NOT NULL AND revoked_at < ?', [date('Y-m-d H:i:s', strtotime('-90 days'))])->rowCount();
$idle = max(5, setting_int('session_idle_minutes', 30));
q("UPDATE user_sessions SET revoked_at = ?, revoked_reason = 'timeout' WHERE revoked_at IS NULL AND last_activity_at < ?", [now(), date('Y-m-d H:i:s', time() - $idle * 60)]);

// Upcoming event notifications (once per user per event)
$events = q_all("SELECT id, title, slug, starts_at, visibility FROM events WHERE status = 'published' AND archived_at IS NULL AND event_status = 'scheduled' AND starts_at BETWEEN ? AND ?", [now(), date('Y-m-d H:i:s', strtotime('+3 days'))]);
$sent = 0;
foreach ($events as $ev) {
    $link = url('event.php', ['slug' => $ev['slug']]);
    foreach (q_col("SELECT id FROM users WHERE account_status = 'active'") as $u) {
        if (!q_val("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND notif_type = 'event_upcoming' AND link = ?", [$u, $link])) {
            notify_user((int) $u, 'event_upcoming', 'Upcoming event: ' . $ev['title'] . ' on ' . fmt_datetime($ev['starts_at']), '', $link);
            $sent++;
        }
    }
}
$out[] = "Event reminders sent: {$sent}";

// Retention: anonymize requester details of tickets closed longer than the retention period
$years = max(1, setting_int('helpdesk_retention_years', 3));
$n = 0;
foreach (q_col("SELECT id FROM helpdesk_tickets WHERE ticket_status = 'closed' AND closed_at < ? AND requester_name <> '(removed)'", [date('Y-m-d H:i:s', strtotime("-{$years} years"))]) as $id) {
    q("UPDATE helpdesk_tickets SET requester_name = '(removed)', requester_email_enc = ?, requester_email_bidx = ?, requester_phone_enc = NULL WHERE id = ?", [encrypt_value('removed'), hash('sha256', 'removed' . $id), $id]);
    $n++;
}
$out[] = "Help desk tickets anonymized: {$n}";
$months = max(1, setting_int('registration_retention_months', 24));
$n = 0;
foreach (q_col("SELECT r.id FROM event_registrations r JOIN events e ON e.id = r.event_id WHERE e.starts_at < ? AND r.full_name <> '(removed)'", [date('Y-m-d H:i:s', strtotime("-{$months} months"))]) as $id) {
    q("UPDATE event_registrations SET full_name = '(removed)', email_enc = ?, email_bidx = ?, phone_enc = NULL, organization = NULL, ip_address = NULL WHERE id = ?", [encrypt_value('removed'), hash('sha256', 'removed' . $id), $id]);
    $n++;
}
$out[] = "Event registrations anonymized: {$n}";
$out[] = 'Old contact messages removed: ' . q("DELETE FROM contact_messages WHERE created_at < ? AND message_status IN ('replied','archived')", [date('Y-m-d H:i:s', strtotime("-{$years} years"))])->rowCount();
$mYears = max(1, setting_int('member_retention_years', 5));
$due = (int) q_val("SELECT COUNT(*) FROM members WHERE archived_at IS NULL AND date_ended IS NOT NULL AND date_ended < ?", [date('Y-m-d', strtotime("-{$mYears} years"))]);
if ($due) {
    security_event('retention_review', 'low', "{$due} member record(s) are past the retention period and need Data Protection Officer review");
}
$out[] = "Member records due for retention review: {$due}";
audit_log('maintenance', 'system', null, implode('; ', $out));
echo implode("\n", $out) . "\n";
