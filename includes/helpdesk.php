<?php
/**
 * Help desk rules: who can see which tickets, numbering and notifications.
 */
declare(strict_types=1);

const TICKET_STATUSES = ['open' => 'Open', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'awaiting_response' => 'Awaiting response', 'resolved' => 'Resolved', 'closed' => 'Closed'];
const TICKET_PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

/** SQL condition for tickets the current user may see. Confidential tickets need explicit permission or assignment. */
function ticket_visibility_sql(array &$p, string $a = 't'): string
{
    $me = (int) uid();
    $conds = [];
    if (can('helpdesk.view_all')) {
        if (can('helpdesk.view_confidential')) {
            $conds[] = '1=1';
        } else {
            $conds[] = "({$a}.is_confidential = 0 OR {$a}.assigned_to = ?)";
            $p[] = $me;
        }
    } else {
        if (can('helpdesk.view')) {
            $conds[] = "{$a}.assigned_to = ?";
            $p[] = $me;
        }
        $conds[] = "{$a}.submitted_by_user_id = ?";
        $p[] = $me;
    }
    return '(' . implode(' OR ', $conds) . ')';
}

function ticket_can_view(array $t): bool
{
    $me = uid();
    if (!$me) {
        return false;
    }
    if ((int) $t['assigned_to'] === $me || (int) $t['submitted_by_user_id'] === $me) {
        return true;
    }
    if (can('helpdesk.view_all')) {
        return !$t['is_confidential'] || can('helpdesk.view_confidential');
    }
    return false;
}

/** Staff-side handling (replies, notes, status) */
function ticket_can_handle(array $t): bool
{
    if (!can('helpdesk.reply')) {
        return false;
    }
    return (int) $t['assigned_to'] === uid() || (can('helpdesk.view_all') && ticket_can_view($t));
}

function ticket_create(array $data): array
{
    $accessKey = random_code(10);
    $data['ticket_no'] = 'TMP-' . bin2hex(random_bytes(8));
    $data['access_key_hash'] = token_hash($accessKey);
    $data['created_at'] = now();
    $data['ip_address'] = client_ip();
    $cat = !empty($data['category_id']) ? q_one('SELECT * FROM helpdesk_categories WHERE id = ?', [(int) $data['category_id']]) : null;
    if ($cat && $cat['default_assignee_id']) {
        $data['assigned_to'] = (int) $cat['default_assignee_id'];
        $data['ticket_status'] = 'assigned';
    }
    $id = tx(function () use ($data) {
        $id = q_insert('helpdesk_tickets', $data);
        q('UPDATE helpdesk_tickets SET ticket_no = ? WHERE id = ?', ['CNPL-HD-' . date('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), $id]);
        q_insert('ticket_replies', ['ticket_id' => $id, 'author_type' => 'system', 'body' => 'Ticket submitted.', 'is_internal' => 0, 'created_at' => now()]);
        return $id;
    });
    $t = q_one('SELECT * FROM helpdesk_tickets WHERE id = ?', [$id]);
    audit_log('ticket_create', 'helpdesk', $id, 'Help desk ticket ' . $t['ticket_no'] . ' submitted');
    $link = url('admin/helpdesk-ticket.php', ['id' => $id]);
    $notified = [];
    foreach (q_col("SELECT DISTINCT ur.user_id FROM user_roles ur JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id
                    JOIN users u ON u.id = ur.user_id WHERE p.slug = 'helpdesk.view_all' AND ur.assignment_status = 'active' AND u.account_status = 'active'") as $uidN) {
        $uidN = (int) $uidN;
        if ($t['is_confidential'] && !can('helpdesk.view_confidential', $uidN)) {
            continue;
        }
        notify_user($uidN, 'ticket_new', 'New help desk ticket ' . $t['ticket_no'], '', $link);
        $notified[$uidN] = true;
    }
    if ($t['assigned_to'] && !isset($notified[(int) $t['assigned_to']])) {
        notify_user((int) $t['assigned_to'], 'ticket_assigned', 'Help desk ticket ' . $t['ticket_no'] . ' was assigned to you', '', $link);
    }
    $email = decrypt_value($t['requester_email_enc']);
    if ($email) {
        send_mail($email, 'We received your concern (' . $t['ticket_no'] . ')', "Hello {$t['requester_name']},\n\nThank you. Your concern was received and recorded as ticket {$t['ticket_no']}.\n\n"
            . "To check its status, open " . abs_url('helpdesk-track.php') . " and enter your ticket number, this email address and the access key shown after you submitted the form.\n\nThe access key is not included in this email for your security.", 'ticket_update');
    }
    return ['id' => $id, 'ticket_no' => $t['ticket_no'], 'access_key' => $accessKey];
}

/** Email the requester that there is an update, without revealing its content. */
function ticket_notify_requester(array $t, string $what): void
{
    $email = decrypt_value($t['requester_email_enc']);
    if ($email) {
        send_mail($email, 'Update on your ticket ' . $t['ticket_no'], "Hello {$t['requester_name']},\n\n{$what}\n\nOpen " . abs_url('helpdesk-track.php') . " and enter your ticket number, email address and access key to read it.", 'ticket_update');
    }
}

/** Requester ownership check for public tracking. */
function ticket_find_for_requester(string $ticketNo, string $email, string $key): ?array
{
    $t = q_one('SELECT * FROM helpdesk_tickets WHERE ticket_no = ? AND archived_at IS NULL', [mb_strtoupper(trim($ticketNo))]);
    if (!$t) {
        return null;
    }
    $ok = hash_equals($t['requester_email_bidx'], (string) blind_index($email)) && hash_equals($t['access_key_hash'], token_hash(strtoupper(trim($key))));
    return $ok ? $t : null;
}
