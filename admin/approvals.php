<?php
/**
 * Approval inbox: submissions waiting for the current user's decision, by module.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/modules.php';

require_perm('approvals.view');

if (is_post() && post('do') === 'bulk') {
    $key = post('module');
    $decision = post('decision') === 'approve' ? 'approved' : 'rejected';
    $remarks = mb_substr(post('remarks'), 0, 255);
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    $map = ['cooperatives' => 'cooperatives', 'personnel' => 'personnel', 'members' => 'members', 'point_persons' => 'point_persons', 'ambassadors' => 'ambassadors', 'officers' => 'officers', 'activities' => 'activities'];
    if (!isset($map[$key]) || !$ids) {
        flash('error', 'Select at least one item.');
    } elseif ($decision === 'rejected' && $remarks === '') {
        flash('error', 'Enter remarks when rejecting.');
    } else {
        $m = module_def($map[$key]);
        if (!crud_can($m, 'approve')) {
            deny('Bulk approve ' . $key);
        }
        $n = 0;
        foreach (array_slice($ids, 0, 200) as $rid) {
            $row = crud_fetch($m, $rid);
            if ($row && $row['review_status'] === 'pending') {
                crud_apply_review($m, $row, $decision, $remarks);
                $n++;
            }
        }
        flash('success', "{$n} submission(s) {$decision}.");
    }
    redirect('admin/approvals.php');
}

admin_start('Approvals', [['Approvals', null]]);
page_bar('Approval inbox', 'Submissions within your scope that need a decision. Approving records makes them part of the verified national database.');
echo flash_render();
$any = false;
foreach (approval_sources() as $key => $src) {
    if (!can($src['perm'])) {
        continue;
    }
    $p = [];
    $w = $src['where']($p);
    $extra = $key === 'content' ? ', x.src' : ($key === 'imports' ? ', x.module, x.valid_rows, x.uploaded_by' : '');
    $idCol = $src['id_col'] ?? 'x.id';
    $rows = q_all("SELECT {$idCol} AS id, {$src['title']} AS t{$extra} FROM {$src['from']} WHERE {$w} ORDER BY {$idCol} DESC LIMIT 100", $p);
    if (!$rows) {
        continue;
    }
    $any = true;
    $bulk = in_array($key, ['cooperatives', 'personnel', 'members', 'point_persons', 'ambassadors', 'officers', 'activities'], true);
    echo '<section class="panel" id="' . e($key) . '"><div class="panel-head"><h2 class="panel-title">' . e($src['label']) . ' (' . count($rows) . ')</h2></div>';
    if ($bulk) {
        echo '<form method="post" data-confirm="Apply this decision to the selected items?">' . csrf_field() . '<input type="hidden" name="do" value="bulk"><input type="hidden" name="module" value="' . e($key) . '">';
    }
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr>' . ($bulk ? '<th scope="col"><input type="checkbox" data-check-all="#' . e($key) . ' input[name=\'ids[]\']" aria-label="Select all"></th>' : '') . '<th scope="col">Item</th><th scope="col">Submitted</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        switch ($key) {
            case 'content':
                $page = ['posts' => 'admin/posts.php', 'pages' => 'admin/pages.php', 'legislation' => 'admin/legislation.php', 'awards' => 'admin/awards.php', 'policies' => 'admin/policies.php', 'history' => 'admin/history.php', 'galleries' => 'admin/galleries.php', 'faqs' => 'admin/faqs.php'][$r['src']];
                $link = url($page, ['action' => 'view', 'id' => $r['id']]);
                break;
            case 'imports':
                $pageMap = ['cooperatives' => 'admin/cooperatives.php', 'personnel' => 'admin/personnel.php', 'members' => 'admin/members.php', 'point_persons' => 'admin/point-persons.php', 'ambassadors' => 'admin/ambassadors.php', 'officers' => 'admin/officers.php', 'activities' => 'admin/activities.php', 'election_results' => 'admin/election-results.php'];
                $link = url($pageMap[$r['module']] ?? 'admin/index.php', ['action' => 'import', 'step' => 'preview', 'batch' => $r['id']]);
                break;
            case 'roles':
                $link = url('admin/users.php', ['action' => 'view', 'id' => $r['id']]);
                break;
            default:
                $link = url($src['page'], ['action' => 'view', 'id' => $r['id']]);
        }
        $req = q_one('SELECT ar.created_at, u.full_name FROM approval_requests ar LEFT JOIN users u ON u.id = ar.requested_by WHERE ar.module = ? AND ar.record_id = ? ORDER BY ar.id DESC LIMIT 1', [$key === 'imports' ? $r['module'] : $key, $r['id']]);
        echo '<tr>' . ($bulk ? '<td><input type="checkbox" name="ids[]" value="' . (int) $r['id'] . '" aria-label="Select ' . e($r['t']) . '"></td>' : '')
            . '<td data-label="Item"><a class="row-link" href="' . e($link) . '">' . e($r['t']) . '</a>' . ($key === 'imports' ? '<span class="sub">' . fmt_num($r['valid_rows']) . ' valid rows</span>' : '') . '</td>'
            . '<td data-label="Submitted">' . ($req ? e(fmt_datetime($req['created_at'])) . '<span class="sub">' . e($req['full_name']) . '</span>' : '<span class="muted">Not recorded</span>') . '</td>'
            . '<td class="row-actions"><a href="' . e($link) . '">Review</a></td></tr>';
    }
    echo '</tbody></table></div>';
    if ($bulk) {
        echo '<div class="attach-form"><div class="field"><label for="rm_' . e($key) . '">Remarks (required when rejecting)</label><input type="text" id="rm_' . e($key) . '" name="remarks" maxlength="255"></div>'
            . '<div class="btn-row"><button class="btn btn-primary" type="submit" name="decision" value="approve">Approve selected</button><button class="btn btn-danger-ghost" type="submit" name="decision" value="reject">Reject selected</button></div></div></form>';
    }
    echo '</section>';
}
if (!$any) {
    echo '<div class="empty"><h2>All caught up</h2><p>Nothing is waiting for your approval.</p></div>';
}
admin_end();
