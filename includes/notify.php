<?php
/**
 * In-system notifications and email delivery that respect user preferences.
 */
declare(strict_types=1);

const NOTIF_TYPES = [
    'security' => 'Account security actions',
    'ticket_new' => 'New help desk tickets',
    'ticket_assigned' => 'Tickets assigned to me',
    'ticket_update' => 'Updates on tickets I handle',
    'approval_pending' => 'Records waiting for my approval',
    'approval_done' => 'Decisions on my submissions',
    'event_upcoming' => 'Upcoming organizational events',
    'announcement' => 'New official announcements',
    'task' => 'Assigned administrative tasks',
];

function notif_pref(int $userId, string $type): array
{
    $row = q_one('SELECT in_app_enabled, email_enabled FROM notification_preferences WHERE user_id = ? AND notif_type = ?', [$userId, $type]);
    if (!$row) {
        return ['in_app' => true, 'email' => $type !== 'announcement'];
    }
    return ['in_app' => (bool) $row['in_app_enabled'], 'email' => (bool) $row['email_enabled']];
}

/**
 * Notify one user. The email subject is generic; details stay inside the system.
 * Security notices always go out by email.
 */
function notify_user(int $userId, string $type, string $title, string $body = '', string $link = ''): void
{
    $user = q_one("SELECT id, email, full_name, account_status FROM users WHERE id = ?", [$userId]);
    if (!$user || $user['account_status'] !== 'active') {
        return;
    }
    $pref = notif_pref($userId, $type);
    if ($pref['in_app'] || $type === 'security') {
        q_insert('notifications', ['user_id' => $userId, 'notif_type' => $type, 'title' => mb_substr($title, 0, 191),
            'body' => mb_substr($body, 0, 500), 'link' => mb_substr($link, 0, 255), 'created_at' => now()]);
    }
    if ($pref['email'] || $type === 'security') {
        $subject = $type === 'security' ? 'Security notice for your account' : 'You have a new notification';
        $text = "Hello {$user['full_name']},\n\n" . $title . "\n\nSign in to view the details:\n" . abs_url($link !== '' ? $link : 'admin/notifications.php');
        if ($type === 'security') {
            $text = "Hello {$user['full_name']},\n\n{$title}\n{$body}\n\nIf you did not do this, contact your system administrator immediately.";
        }
        send_mail($user['email'], $subject, $text, $type);
    }
}

/**
 * Notify every active user holding a permission. When a location is given,
 * only users whose scope covers it are notified.
 */
function notify_perm(string $perm, string $type, string $title, string $body = '', string $link = '', ?array $geo = null, ?int $exceptUser = null): void
{
    $users = q_col("SELECT DISTINCT ur.user_id FROM user_roles ur JOIN role_permissions rp ON rp.role_id = ur.role_id
                    JOIN permissions p ON p.id = rp.permission_id JOIN users u ON u.id = ur.user_id
                    WHERE p.slug = ? AND ur.assignment_status = 'active' AND u.account_status = 'active'", [$perm]);
    foreach ($users as $id) {
        $id = (int) $id;
        if ($id === $exceptUser) {
            continue;
        }
        if ($geo !== null) {
            $saved = $GLOBALS['CNPL_USER'] ?? null;
            $s = user_scope($id);
            $ok = $s['national']
                || (!empty($geo['coop']) && in_array((int) $geo['coop'], $s['coops'], true))
                || (!empty($geo['city']) && in_array((int) $geo['city'], $s['cities'], true))
                || (!empty($geo['province']) && in_array((int) $geo['province'], $s['provinces'], true))
                || (!empty($geo['region']) && in_array((int) $geo['region'], $s['regions'], true));
            $GLOBALS['CNPL_USER'] = $saved;
            if (!$ok) {
                continue;
            }
        }
        notify_user($id, $type, $title, $body, $link);
    }
}

function unread_notifications(): int
{
    $id = uid();
    return $id ? (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$id]) : 0;
}
