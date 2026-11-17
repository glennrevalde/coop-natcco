<?php
/**
 * One help desk ticket: thread, internal notes, attachments, assignment and status.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';
require APP_ROOT . '/includes/helpdesk.php';

require_perm(['helpdesk.view', 'helpdesk.view_all', 'helpdesk.submit']);
$id = get_int('id');
$t = q_one('SELECT * FROM helpdesk_tickets WHERE id = ?', [$id]);
if (!$t || !ticket_can_view($t)) {
    if ($t) {
        audit_log('access_denied', 'helpdesk', $id, 'Attempt to open a ticket outside permissions', 'denied');
    }
    abort(404);
}
$handle = ticket_can_handle($t);
$isRequester = (int) $t['submitted_by_user_id'] === uid();
if ($t['is_confidential']) {
    audit_log('view', 'helpdesk', $id, 'Viewed confidential ticket ' . $t['ticket_no']);
}

if (is_post()) {
    $do = post('do');
    $self = url('admin/helpdesk-ticket.php', ['id' => $id]);
    if ($do === 'reply' && ($handle || $isRequester)) {
        $body = mb_substr(post('body'), 0, 10000);
        $internal = $handle && post('internal') === '1' ? 1 : 0;
        $newStatus = $handle ? post('new_status') : '';
        try {
            if ($body === '') {
                throw new ValidationError('Write a message.');
            }
            $mid = upload_present('file') ? media_upload($_FILES['file'], 'attachment', 'private', 'Ticket attachment') : null;
            tx(function () use ($t, $body, $internal, $mid, $handle, $newStatus) {
                $rid = q_insert('ticket_replies', ['ticket_id' => $t['id'], 'user_id' => uid(), 'author_type' => $handle ? 'staff' : 'requester', 'body' => $body, 'is_internal' => $internal, 'created_at' => now()]);
                if ($mid) {
                    q_insert('ticket_attachments', ['ticket_id' => $t['id'], 'reply_id' => $rid, 'media_id' => $mid, 'is_internal' => $internal, 'uploaded_by' => uid(), 'created_at' => now()]);
                }
                $upd = ['updated_at' => now()];
                if ($handle && !$internal && !$t['first_response_at']) {
                    $upd['first_response_at'] = now();
                }
                if ($handle && isset(TICKET_STATUSES[$newStatus]) && $newStatus !== $t['ticket_status'] && ($newStatus !== 'closed' || can('helpdesk.manage'))) {
                    $upd['ticket_status'] = $newStatus;
                    if ($newStatus === 'resolved') {
                        $upd['resolved_at'] = now();
                    }
                    if ($newStatus === 'closed') {
                        $upd['closed_at'] = now();
                    }
                } elseif (!$handle && in_array($t['ticket_status'], ['awaiting_response', 'resolved'], true)) {
                    $upd['ticket_status'] = 'in_progress';
                }
                q_update('helpdesk_tickets', $upd, (int) $t['id']);
            });
            audit_log($internal ? 'ticket_note' : 'ticket_reply', 'helpdesk', $id, ($internal ? 'Internal note on ' : 'Reply on ') . $t['ticket_no']);
            if ($handle && !$internal) {
                ticket_notify_requester($t, 'A help desk officer replied to your ticket.');
            }
            if (!$handle && $t['assigned_to']) {
                notify_user((int) $t['assigned_to'], 'ticket_update', 'New message on ticket ' . $t['ticket_no'], '', $self);
            }
            flash('success', $internal ? 'Internal note added.' : 'Reply sent.');
        } catch (ValidationError $e) {
            flash('error', $e->getMessage());
        }
    } elseif ($do === 'manage' && ($handle || can('helpdesk.assign'))) {
        $upd = [];
        $log = [];
        if (can('helpdesk.assign')) {
            $assignee = post_int('assigned_to');
            $valid = $assignee === 0 || q_val("SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.assignment_status = 'active' JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id
                                                 WHERE u.id = ? AND u.account_status = 'active' AND p.slug = 'helpdesk.reply'", [$assignee]);
            if ($valid && $assignee !== (int) $t['assigned_to']) {
                $upd['assigned_to'] = $assignee ?: null;
                $log[] = $assignee ? 'Assigned to ' . lookup_label('user', $assignee) : 'Unassigned';
                if ($assignee && in_array($t['ticket_status'], ['open'], true)) {
                    $upd['ticket_status'] = 'assigned';
                }
            }
        }
        if ($handle) {
            $prio = post('priority');
            if (isset(TICKET_PRIORITIES[$prio]) && $prio !== $t['priority']) {
                $upd['priority'] = $prio;
                $log[] = 'Priority set to ' . TICKET_PRIORITIES[$prio];
            }
            $cat = post_int('category_id');
            if ($cat && $cat !== (int) $t['category_id'] && q_val('SELECT COUNT(*) FROM helpdesk_categories WHERE id = ?', [$cat])) {
                $upd['category_id'] = $cat;
                $log[] = 'Category changed';
            }
            $st = post('ticket_status');
            if (isset(TICKET_STATUSES[$st]) && $st !== $t['ticket_status'] && ($st !== 'closed' || can('helpdesk.manage'))) {
                $upd['ticket_status'] = $st;
                $log[] = 'Status changed to ' . TICKET_STATUSES[$st];
                if ($st === 'resolved') {
                    $upd['resolved_at'] = now();
                }
                if ($st === 'closed') {
                    $upd['closed_at'] = now();
                }
            }
            if (can('helpdesk.view_confidential')) {
                $conf = post('is_confidential') === '1' ? 1 : 0;
                if ($conf !== (int) $t['is_confidential']) {
                    $upd['is_confidential'] = $conf;
                    $log[] = $conf ? 'Marked confidential' : 'Confidential flag removed';
                }
            }
        }
        if ($upd) {
            $upd['updated_at'] = now();
            q_update('helpdesk_tickets', $upd, $id);
            q_insert('ticket_replies', ['ticket_id' => $id, 'user_id' => uid(), 'author_type' => 'system', 'body' => implode('. ', $log) . '.', 'is_internal' => 1, 'created_at' => now()]);
            audit_log('ticket_update', 'helpdesk', $id, $t['ticket_no'] . ': ' . implode('; ', $log));
            if (!empty($upd['assigned_to']) && (int) $upd['assigned_to'] !== uid()) {
                notify_user((int) $upd['assigned_to'], 'ticket_assigned', 'Help desk ticket ' . $t['ticket_no'] . ' was assigned to you', '', url('admin/helpdesk-ticket.php', ['id' => $id]));
            }
            if (isset($upd['ticket_status'])) {
                ticket_notify_requester($t, 'The status of your ticket changed to: ' . TICKET_STATUSES[$upd['ticket_status']] . '.');
            }
            flash('success', 'Ticket updated.');
        }
    }
    redirect(url('admin/helpdesk-ticket.php', ['id' => $id]));
}

$t = q_one('SELECT t.*, c.name AS category_name, co.name AS coop_name, r.name AS region_name FROM helpdesk_tickets t LEFT JOIN helpdesk_categories c ON c.id = t.category_id
            LEFT JOIN cooperatives co ON co.id = t.cooperative_id LEFT JOIN regions r ON r.id = t.region_id WHERE t.id = ?', [$id]);
$showInternal = $handle || can('helpdesk.view_all');
$replies = q_all('SELECT r.*, u.full_name FROM ticket_replies r LEFT JOIN users u ON u.id = r.user_id WHERE r.ticket_id = ?' . ($showInternal ? '' : ' AND r.is_internal = 0') . ' ORDER BY r.id', [$id]);
$atts = q_all('SELECT a.*, m.original_name, m.file_size FROM ticket_attachments a JOIN media_assets m ON m.id = a.media_id WHERE a.ticket_id = ?' . ($showInternal ? '' : ' AND a.is_internal = 0') . ' ORDER BY a.id', [$id]);
$attByReply = [];
foreach ($atts as $a) {
    $attByReply[(int) $a['reply_id']][] = $a;
}
$assignees = q_pairs("SELECT DISTINCT u.id, u.full_name FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.assignment_status = 'active' JOIN role_permissions rp ON rp.role_id = ur.role_id
                      JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'helpdesk.reply' AND u.account_status = 'active' ORDER BY u.full_name");

admin_start($t['ticket_no'], [['Help desk', url('admin/helpdesk.php')], [$t['ticket_no'], null]]);
page_bar($t['subject'], e($t['ticket_no']) . ', received ' . e(fmt_datetime($t['created_at'])));
echo flash_render();
?>
<div class="status-strip"><span>Status: <?= status_text($t['ticket_status'], TICKET_STATUSES[$t['ticket_status']]) ?></span><span>Priority: <?= status_text($t['priority']) ?></span>
    <span>Category: <?= e($t['category_name'] ?: 'None') ?></span><?php if ($t['is_confidential']): ?><span class="st st-bad">Confidential</span><?php endif; ?></div>
<div class="two-col">
<div>
    <section class="panel"><h2 class="panel-title">Concern</h2><div class="prose"><?= nl2p($t['description']) ?></div>
        <?php foreach ($attByReply[0] ?? [] as $a): ?><p class="small"><a href="<?= e(media_url((int) $a['media_id'])) ?>"><?= icon('download') ?> <?= e($a['original_name']) ?></a> <span class="muted"><?= e(human_size((int) $a['file_size'])) ?></span></p><?php endforeach; ?>
    </section>
    <section class="panel"><h2 class="panel-title">Conversation</h2>
        <ol class="thread">
        <?php foreach ($replies as $r): ?>
            <li class="<?= $r['is_internal'] ? 'internal' : e($r['author_type']) ?>"><div class="th-head"><strong><?= e($r['author_type'] === 'requester' ? $t['requester_name'] : ($r['author_type'] === 'system' ? 'System' : ($r['full_name'] ?: 'Help desk'))) ?></strong>
                <span><?= $r['is_internal'] ? 'Internal note, ' : '' ?><?= e(fmt_datetime($r['created_at'])) ?></span></div>
                <div><?= nl2p($r['body']) ?></div>
                <?php foreach ($attByReply[(int) $r['id']] ?? [] as $a): ?><p class="small"><a href="<?= e(media_url((int) $a['media_id'])) ?>"><?= icon('download') ?> <?= e($a['original_name']) ?></a></p><?php endforeach; ?>
            </li>
        <?php endforeach; ?>
        </ol>
        <?php if (($handle || $isRequester) && $t['ticket_status'] !== 'closed'): ?>
        <form method="post" enctype="multipart/form-data" class="attach-form"><?= csrf_field() ?><input type="hidden" name="do" value="reply">
            <div class="field field-full"><label for="r_body">Message</label><textarea id="r_body" name="body" rows="5" required maxlength="10000"></textarea></div>
            <div class="field"><label for="r_file">Attachment</label><input type="file" id="r_file" name="file" accept="<?= e(media_accept_attr('attachment')) ?>"></div>
            <?php if ($handle): ?>
            <div class="field"><label for="r_status">Then set status to</label><?= select_html('new_status', TICKET_STATUSES, '', ['id' => 'r_status'], 'Keep ' . TICKET_STATUSES[$t['ticket_status']]) ?></div>
            <div class="field field-full"><label class="check"><input type="checkbox" name="internal" value="1"> <span>Internal note (not visible to the requester)</span></label></div>
            <?php endif; ?>
            <div class="btn-row"><button class="btn btn-primary" type="submit">Send</button></div>
        </form>
        <?php endif; ?>
    </section>
</div>
<div>
    <section class="panel"><h2 class="panel-title">Requester</h2><dl class="dl">
        <div class="dl-item dl-wide"><dt>Name</dt><dd><?= e($t['requester_name']) ?></dd></div>
        <div class="dl-item dl-wide"><dt>Email</dt><dd><?= e(decrypt_value($t['requester_email_enc'])) ?></dd></div>
        <?php if ($t['requester_phone_enc']): ?><div class="dl-item dl-wide"><dt>Phone</dt><dd><?= e(decrypt_value($t['requester_phone_enc'])) ?></dd></div><?php endif; ?>
        <div class="dl-item dl-wide"><dt>Cooperative</dt><dd><?= e($t['coop_name'] ?: ($t['cooperative_name'] ?: 'Not stated')) ?></dd></div>
        <div class="dl-item dl-wide"><dt>Region</dt><dd><?= e($t['region_name'] ?: 'Not stated') ?></dd></div>
        <div class="dl-item dl-wide"><dt>First response</dt><dd><?= $t['first_response_at'] ? e(fmt_datetime($t['first_response_at'])) : '<span class="muted">None yet</span>' ?></dd></div>
    </dl></section>
    <?php if ($handle || can('helpdesk.assign')): ?>
    <form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="do" value="manage">
        <h2 class="panel-title">Manage ticket</h2>
        <?php if (can('helpdesk.assign')): ?><div class="field field-full"><label for="m_assign">Assigned officer</label><?= select_html('assigned_to', $assignees, $t['assigned_to'], ['id' => 'm_assign'], 'Unassigned') ?></div><?php endif; ?>
        <?php if ($handle): ?>
        <div class="field field-full"><label for="m_status">Status</label><?= select_html('ticket_status', can('helpdesk.manage') ? TICKET_STATUSES : array_diff_key(TICKET_STATUSES, ['closed' => 1]), $t['ticket_status'], ['id' => 'm_status'], null) ?></div>
        <div class="field field-full"><label for="m_prio">Priority</label><?= select_html('priority', TICKET_PRIORITIES, $t['priority'], ['id' => 'm_prio'], null) ?></div>
        <div class="field field-full"><label for="m_cat">Category</label><?= select_html('category_id', q_pairs('SELECT id, name FROM helpdesk_categories WHERE is_active = 1 ORDER BY sort_order'), $t['category_id'], ['id' => 'm_cat'], null) ?></div>
        <?php if (can('helpdesk.view_confidential')): ?><div class="field field-full"><input type="hidden" name="is_confidential" value="0"><label class="check"><input type="checkbox" name="is_confidential" value="1"<?= $t['is_confidential'] ? ' checked' : '' ?>> <span>Confidential ticket</span></label></div><?php endif; ?>
        <?php endif; ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Update ticket</button></div>
    </form>
    <?php endif; ?>
</div>
</div>
<?php admin_end();
