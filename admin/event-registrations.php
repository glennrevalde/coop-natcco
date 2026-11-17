<?php
/**
 * Event participation management: registrations, waiting list, attendance and export.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';

require_perm('events.registrations');
$eventId = get_int('event');
$ev = q_one('SELECT * FROM events WHERE id = ?', [$eventId]);
if (!$ev) {
    abort(404);
}
$statuses = ['registered' => 'Registered', 'waitlisted' => 'Waiting list', 'attended' => 'Attended', 'no_show' => 'Did not attend', 'cancelled' => 'Cancelled'];

if (is_post()) {
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    $to = post('to');
    if ($ids && isset($statuses[$to])) {
        $p = [$to, now(), $eventId];
        $in = sql_in('id', $ids, $p);
        $n = q("UPDATE event_registrations SET registration_status = ?, updated_at = ? WHERE event_id = ? AND {$in}", $p)->rowCount();
        audit_log('registrations_updated', 'events', $eventId, "Set {$n} registration(s) to {$to}");
        if ($to === 'registered') {
            $pp = [$eventId];
            $in2 = sql_in('id', $ids, $pp);
            foreach (q_all("SELECT * FROM event_registrations WHERE event_id = ? AND {$in2}", $pp) as $r) {
                $email = decrypt_value($r['email_enc']);
                if ($email) {
                    send_mail($email, 'Your event registration is confirmed', "Hello {$r['full_name']},\n\nYour registration ({$r['reference_code']}) for \"{$ev['title']}\" on " . fmt_datetime($ev['starts_at']) . ' is confirmed.', 'event_upcoming');
                }
            }
        }
        flash('success', "{$n} registration(s) updated.");
    }
    redirect(url('admin/event-registrations.php', ['event' => $eventId]));
}

$p = [$eventId];
$w = 'r.event_id = ?';
if (isset($statuses[get('st')])) {
    $w .= ' AND r.registration_status = ?';
    $p[] = get('st');
}
if (($q = mb_substr(get('q'), 0, 80)) !== '') {
    $w .= ' AND (r.full_name LIKE ? OR r.reference_code LIKE ? OR r.organization LIKE ?)';
    array_push($p, like_escape($q), like_escape($q), like_escape($q));
}
$rows = q_all("SELECT r.*, c.name AS coop_name FROM event_registrations r LEFT JOIN cooperatives c ON c.id = r.cooperative_id WHERE {$w} ORDER BY r.created_at", $p);

if (get('export') === 'csv') {
    q_insert('export_logs', ['user_id' => uid(), 'module' => 'event_registrations', 'filters' => 'event=' . $eventId, 'row_count' => count($rows), 'is_sensitive' => 1, 'ip_address' => client_ip(), 'created_at' => now()]);
    audit_log('export', 'events', $eventId, 'Exported ' . count($rows) . ' registrations');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrations-' . $ev['slug'] . '.csv"');
    no_cache_headers();
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Reference', 'Name', 'Email', 'Phone', 'Organization', 'Cooperative', 'Status', 'Registered']);
    foreach ($rows as $r) {
        fputcsv($out, array_map('csv_safe', [$r['reference_code'], $r['full_name'], decrypt_value($r['email_enc']), decrypt_value($r['phone_enc']), $r['organization'], $r['coop_name'], $r['registration_status'], $r['created_at']]));
    }
    fclose($out);
    exit;
}
$counts = q_pairs('SELECT registration_status, COUNT(*) FROM event_registrations WHERE event_id = ? GROUP BY registration_status', [$eventId]);
admin_start('Registrations', [['Events', url('admin/events.php')], [$ev['title'], url('admin/events.php', ['action' => 'view', 'id' => $eventId])], ['Registrations', null]]);
page_bar('Registrations: ' . $ev['title'], e(fmt_datetime($ev['starts_at'])) . ($ev['registration_limit'] ? ', limit ' . fmt_num($ev['registration_limit']) : ', no limit'),
    '<a class="btn btn-secondary" href="' . e(current_url(['export' => 'csv'])) . '">Export CSV</a><button class="btn btn-ghost" type="button" data-print>Print attendance list</button>');
echo flash_render();
echo '<div class="stat-row">';
foreach ($statuses as $k => $l) {
    echo '<div class="stat"><span class="stat-num">' . fmt_num($counts[$k] ?? 0) . '</span><span class="stat-label">' . $l . '</span></div>';
}
echo '</div>';
?>
<form class="filters" method="get"><input type="hidden" name="event" value="<?= (int) $eventId ?>">
    <div class="filter filter-search"><label for="r_q">Search</label><input type="search" id="r_q" name="q" value="<?= e(get('q')) ?>" placeholder="Name, reference or organization"></div>
    <div class="filter"><label for="r_s">Status</label><?= select_html('st', $statuses, get('st'), ['id' => 'r_s'], 'All') ?></div>
    <div class="filter filter-actions"><button class="btn btn-primary" type="submit">Apply</button></div>
</form>
<form method="post" id="regs"><?= csrf_field() ?>
<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col"><input type="checkbox" data-check-all="#regs input[name='ids[]']" aria-label="Select all"></th><th scope="col">Registrant</th><th scope="col">Contact</th><th scope="col">Organization</th><th scope="col">Registered</th><th scope="col">Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
    <tr><td><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="Select <?= e($r['full_name']) ?>"></td>
        <td data-label="Registrant"><strong><?= e($r['full_name']) ?></strong><span class="sub"><?= e($r['reference_code']) ?></span></td>
        <td data-label="Contact"><?= e(decrypt_value($r['email_enc'])) ?><span class="sub"><?= e(decrypt_value($r['phone_enc'])) ?></span></td>
        <td data-label="Organization"><?= e($r['coop_name'] ?: $r['organization']) ?></td>
        <td data-label="Registered"><?= e(fmt_datetime($r['created_at'])) ?></td>
        <td data-label="Status"><?= status_text($r['registration_status'], $statuses[$r['registration_status']]) ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="muted">No registrations yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($rows): ?>
<div class="form-actions"><label for="r_to" class="sr-only">Change status to</label><?= select_html('to', $statuses, '', ['id' => 'r_to', 'required' => true], 'Change selected to…') ?><button class="btn btn-primary" type="submit">Update selected</button></div>
<?php endif; ?>
</form>
<?php admin_end();
