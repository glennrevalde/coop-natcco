<?php
/**
 * Ticket tracking for requesters: ticket number + email + access key.
 */
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';
require APP_ROOT . '/includes/helpdesk.php';

$ticket = null;
$error = '';
$sid = (int) ($_SESSION['track_ticket'] ?? 0);
if (is_post() && post('do') === 'lookup') {
    $ip = client_ip();
    if (throttle_locked('ticket_track', $ip)) {
        abort(429);
    }
    $t = ticket_find_for_requester(post('ticket_no'), post('email'), post('access_key'));
    if ($t) {
        throttle_clear('ticket_track', $ip);
        $_SESSION['track_ticket'] = (int) $t['id'];
        $_SESSION['tracked_tickets'][] = (int) $t['id'];
        redirect('helpdesk-track.php');
    }
    throttle_hit('ticket_track', $ip, 8, 900, 900);
    security_event('ticket_lookup_failed', 'info', 'Failed help desk ticket lookup');
    $error = 'We could not find a ticket with those details. Check the ticket number, email address and access key.';
}
if ($sid) {
    $ticket = q_one('SELECT t.*, c.name AS category_name FROM helpdesk_tickets t LEFT JOIN helpdesk_categories c ON c.id = t.category_id WHERE t.id = ? AND t.archived_at IS NULL', [$sid]);
}
if ($ticket && is_post() && post('do') === 'reply') {
    public_form_limit('ticket_reply', 10, 600);
    $body = mb_substr(post('body'), 0, 8000);
    try {
        if ($ticket['ticket_status'] === 'closed') {
            throw new ValidationError('This ticket is closed. Submit a new concern if you need more help.');
        }
        if (mb_strlen($body) < 2) {
            throw new ValidationError('Write a message.');
        }
        $info = upload_present('file__file') ? media_validate_upload($_FILES['file__file'], 'attachment') : null;
        tx(function () use ($ticket, $body, $info) {
            $rid = q_insert('ticket_replies', ['ticket_id' => $ticket['id'], 'author_type' => 'requester', 'body' => $body, 'is_internal' => 0, 'created_at' => now()]);
            if ($info) {
                $mid = media_store($info, 'private', 'Ticket attachment');
                q_insert('ticket_attachments', ['ticket_id' => $ticket['id'], 'reply_id' => $rid, 'media_id' => $mid, 'is_internal' => 0, 'created_at' => now()]);
            }
            $st = in_array($ticket['ticket_status'], ['awaiting_response', 'resolved'], true) ? 'in_progress' : $ticket['ticket_status'];
            q('UPDATE helpdesk_tickets SET ticket_status = ?, updated_at = ? WHERE id = ?', [$st, now(), $ticket['id']]);
        });
        audit_log('ticket_requester_reply', 'helpdesk', (int) $ticket['id'], 'Requester replied on ' . $ticket['ticket_no']);
        if ($ticket['assigned_to']) {
            notify_user((int) $ticket['assigned_to'], 'ticket_update', 'The requester replied on ticket ' . $ticket['ticket_no'], '', url('admin/helpdesk-ticket.php', ['id' => $ticket['id']]));
        }
        flash('success', 'Your message was sent.');
    } catch (ValidationError $e) {
        flash('error', $e->getMessage());
    }
    redirect('helpdesk-track.php');
}
if (is_post() && post('do') === 'leave') {
    unset($_SESSION['track_ticket']);
    redirect('helpdesk-track.php');
}
public_start('Track a ticket', ['crumbs' => [['Help desk', url('helpdesk.php')], ['Track a ticket', null]], 'sub' => 'Check the status of your concern and read replies from the help desk.']);
echo '<div class="wrap section">' . flash_render();
if (!$ticket) {
    ?>
    <div class="split"><form method="post" class="form-card" novalidate><?= csrf_field() ?><input type="hidden" name="do" value="lookup">
        <h2>Find your ticket</h2>
        <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <div class="grid">
            <?= form_field('ticket_no', ['label' => 'Ticket number', 'required' => true, 'max' => 30, 'placeholder' => 'CNPL-HD-2026-000001', 'width' => 'full'], post('ticket_no') ?: get('no')) ?>
            <?= form_field('email', ['label' => 'Email used when submitting', 'type' => 'email', 'required' => true, 'width' => 'full', 'autocomplete' => 'email'], post('email')) ?>
            <?= form_field('access_key', ['label' => 'Access key', 'required' => true, 'max' => 12, 'width' => 'full', 'autocomplete' => 'off', 'help' => 'The 10-character key shown after you submitted.'], '') ?>
        </div>
        <button class="btn btn-primary" type="submit">Show my ticket</button>
    </form>
    <aside><div class="box"><h2 class="h3">Lost your access key?</h2><p>Submit a new concern and mention your earlier ticket number in the details. The help desk will link them.</p><a class="btn btn-secondary" href="<?= e(url('helpdesk.php')) ?>">Submit a concern</a></div></aside></div>
    <?php
} else {
    $replies = q_all('SELECT r.*, u.full_name FROM ticket_replies r LEFT JOIN users u ON u.id = r.user_id WHERE r.ticket_id = ? AND r.is_internal = 0 AND r.author_type <> \'system\' ORDER BY r.id', [$ticket['id']]);
    $atts = q_all('SELECT a.*, m.original_name FROM ticket_attachments a JOIN media_assets m ON m.id = a.media_id WHERE a.ticket_id = ? AND a.is_internal = 0', [$ticket['id']]);
    $byReply = [];
    foreach ($atts as $a) {
        $byReply[(int) $a['reply_id']][] = $a;
    }
    ?>
    <div class="split"><div>
        <div class="box"><h2><?= e($ticket['subject']) ?></h2><p class="muted"><?= e($ticket['ticket_no']) ?>, submitted <?= e(fmt_datetime($ticket['created_at'])) ?></p><div class="prose"><?= nl2p($ticket['description']) ?></div>
            <?php foreach ($byReply[0] ?? [] as $a): ?><p><a class="file-dl" href="<?= e(media_url((int) $a['media_id'])) ?>"><?= icon('download') ?><?= e($a['original_name']) ?></a></p><?php endforeach; ?></div>
        <h2 class="h3 mt">Messages</h2>
        <?php if (!$replies): ?><p class="muted">No replies yet. You will receive an email when the help desk responds.</p><?php endif; ?>
        <ol class="thread-pub"><?php foreach ($replies as $r): ?>
            <li class="<?= e($r['author_type']) ?>"><div class="th-head"><strong><?= $r['author_type'] === 'staff' ? 'Help desk' . ($r['full_name'] ? ' (' . e(strtok($r['full_name'], ' ')) . ')' : '') : 'You' ?></strong><span><?= e(fmt_datetime($r['created_at'])) ?></span></div><?= nl2p($r['body']) ?>
                <?php foreach ($byReply[(int) $r['id']] ?? [] as $a): ?><p><a class="file-dl" href="<?= e(media_url((int) $a['media_id'])) ?>"><?= icon('download') ?><?= e($a['original_name']) ?></a></p><?php endforeach; ?></li>
        <?php endforeach; ?></ol>
        <?php if ($ticket['ticket_status'] !== 'closed'): ?>
        <form method="post" enctype="multipart/form-data" class="form-card"><?= csrf_field() ?><input type="hidden" name="do" value="reply">
            <div class="grid"><?= form_field('body', ['label' => 'Send a message', 'type' => 'textarea', 'required' => true, 'max' => 8000, 'rows' => 4], '') ?>
            <?= form_field('file', ['label' => 'Attach a file (optional)', 'type' => 'media', 'kind' => 'attachment', 'width' => 'full'], null) ?></div>
            <button class="btn btn-primary" type="submit">Send message</button></form>
        <?php endif; ?>
    </div>
    <aside><div class="box"><h2 class="h3">Status</h2><dl class="dl-pub"><dt>Status</dt><dd><?= status_text($ticket['ticket_status'], TICKET_STATUSES[$ticket['ticket_status']]) ?></dd>
        <dt>Category</dt><dd><?= e($ticket['category_name']) ?></dd><dt>Priority</dt><dd><?= e(TICKET_PRIORITIES[$ticket['priority']]) ?></dd><dt>Last update</dt><dd><?= e(fmt_datetime($ticket['updated_at'] ?: $ticket['created_at'])) ?></dd></dl>
        <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="do" value="leave"><button class="btn btn-secondary btn-sm" type="submit">Close this view</button></form></div></aside></div>
    <?php
}
echo '</div>';
public_end();
