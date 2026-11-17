<?php
/**
 * CRUD engine used by every registry and publishing module.
 *
 * A module is described by a definition array (see includes/modules/*.php).
 * The engine provides list/search/filter/sort/pagination, view, create, edit,
 * archive/restore, review approval, publishing workflow, revision history,
 * attachments, CSV export and staged imports - all with permission and data
 * scope checks on the server for every request.
 */
declare(strict_types=1);

require_once APP_ROOT . '/includes/forms.php';
require_once APP_ROOT . '/includes/layout.php';

function crud_norm(array $m): array
{
    $m += [
        'alias' => 't', 'soft_delete' => true, 'review' => false, 'workflow' => false, 'wf_perm' => 'content',
        'revisions' => true, 'attachments' => false, 'export' => true, 'import' => false, 'search' => [],
        'sorts' => [], 'default_sort' => null, 'default_dir' => 'desc', 'filters' => [], 'columns' => [], 'fields' => [],
        'sections' => null, 'scope' => null, 'base_where' => null, 'prefill' => [], 'defaults' => [], 'custom_actions' => [],
        'view_panels' => null, 'sensitive' => false, 'per_page' => 25, 'page_params' => [], 'list_intro' => '',
        'redact' => [], 'can_view_row' => null, 'can_edit_row' => null, 'no_create' => false,
    ];
    $m['from'] = $m['from'] ?? ($m['table'] . ' ' . $m['alias']);
    $m['select'] = $m['select'] ?? ($m['alias'] . '.*');
    foreach ($m['fields'] as $k => &$f) {
        $f['column'] = $f['column'] ?? $k;
    }
    unset($f);
    if ($m['workflow']) {
        $m['soft_delete'] = true;
    }
    return $m;
}

function crud_url(array $m, array $params = []): string
{
    return url($m['page'], array_merge($m['page_params'], $params));
}

function crud_can(array $m, string $action): bool
{
    if (isset($m['perm_map'])) {
        if ($action === 'list') {
            $action = 'view';
        }
        if (in_array($action, ['export', 'import', 'approve'], true) && empty($m[$action === 'approve' ? 'review' : $action])) {
            return false;
        }
        if ($action === 'create' && $m['no_create']) {
            return false;
        }
        return isset($m['perm_map'][$action]) && can($m['perm_map'][$action]);
    }
    $p = $m['perm'];
    switch ($action) {
        case 'view':
        case 'list':
            return can($p . '.view');
        case 'create':
            return !$m['no_create'] && can($p . '.create');
        case 'edit':
            return can($p . '.edit');
        case 'archive':
            return can($p . '.archive');
        case 'approve':
            return $m['review'] && can($p . '.approve');
        case 'export':
            return $m['export'] && can($p . '.export');
        case 'import':
            return $m['import'] && can($p . '.import');
        case 'publish':
            return can($m['wf_perm'] . '.publish');
        case 'review_content':
            return can($m['wf_perm'] . '.review');
    }
    return false;
}

function crud_require(array $m, string $action): void
{
    require_login();
    if (!crud_can($m, $action)) {
        deny("No {$action} permission on {$m['key']}");
    }
}

function crud_run(array $m): void
{
    $m = crud_norm($m);
    $action = get('action', 'list');
    if (isset($m['custom_page'][$action])) {
        ($m['custom_page'][$action])($m);
        return;
    }
    switch ($action) {
        case 'list':
            crud_list($m);
            return;
        case 'view':
            crud_view($m, get_int('id'));
            return;
        case 'create':
            crud_form($m, 'create', null);
            return;
        case 'edit':
            crud_form($m, 'edit', get_int('id'));
            return;
        case 'archive':
        case 'restore':
            crud_archive($m, $action);
            return;
        case 'review':
            crud_review($m);
            return;
        case 'workflow':
            crud_workflow_action($m);
            return;
        case 'export':
            crud_export($m);
            return;
        case 'attach':
            crud_attach($m);
            return;
        case 'detach':
            crud_detach($m);
            return;
        case 'custom':
            crud_custom($m);
            return;
        case 'import':
        case 'template':
            require_once APP_ROOT . '/includes/crud_import.php';
            $action === 'template' ? crud_import_template($m) : crud_import($m);
            return;
    }
    abort(404);
}

// ------------------------------------------------------------------ query
function crud_where(array $m, array &$params, bool $withFilters = true): string
{
    $a = $m['alias'];
    $w = [];
    if ($m['base_where']) {
        [$sql, $bp] = $m['base_where'];
        $w[] = $sql;
        foreach ($bp as $v) {
            $params[] = $v;
        }
    }
    if ($m['scope']) {
        $w[] = scope_sql($m['scope'], $params);
    }
    if (!$withFilters) {
        return $w ? implode(' AND ', $w) : '1=1';
    }
    if ($m['soft_delete']) {
        $showArchived = get('show') === 'archived' || ($m['workflow'] && get('status') === 'archived');
        $w[] = $showArchived ? "{$a}.archived_at IS NOT NULL" : "{$a}.archived_at IS NULL";
    }
    $search = mb_substr(get('q'), 0, 100);
    if ($search !== '' && $m['search']) {
        $parts = [];
        foreach ($m['search'] as $col) {
            $parts[] = "{$col} LIKE ?";
            $params[] = like_escape($search);
        }
        $w[] = '(' . implode(' OR ', $parts) . ')';
    }
    foreach ($m['filters'] as $key => $f) {
        $v = get('f_' . $key);
        if ($v === '') {
            continue;
        }
        if (isset($f['where'])) {
            [$sql, $fp] = ($f['where'])($v);
            if ($sql) {
                $w[] = $sql;
                foreach ($fp as $x) {
                    $params[] = $x;
                }
            }
            continue;
        }
        $type = $f['type'] ?? 'select';
        if (in_array($type, ['region', 'province', 'city', 'int', 'hidden'], true) || isset($f['options_sql'])) {
            if (!ctype_digit($v)) {
                continue;
            }
            $w[] = $f['col'] . ' = ?';
            $params[] = (int) $v;
        } elseif ($type === 'date_from') {
            if (valid_date($v)) {
                $w[] = $f['col'] . ' >= ?';
                $params[] = $v . (strpos($f['col'], '_at') !== false ? ' 00:00:00' : '');
            }
        } elseif ($type === 'date_to') {
            if (valid_date($v)) {
                $w[] = $f['col'] . ' <= ?';
                $params[] = $v . (strpos($f['col'], '_at') !== false ? ' 23:59:59' : '');
            }
        } else {
            $opts = field_options($f);
            if (!array_key_exists($v, $opts)) {
                continue;
            }
            $w[] = $f['col'] . ' = ?';
            $params[] = $v;
        }
    }
    if ($m['review'] && in_array(get('review'), ['pending', 'approved', 'rejected'], true)) {
        $w[] = "{$a}.review_status = ?";
        $params[] = get('review');
    }
    if ($m['workflow'] && in_array(get('status'), ['draft', 'in_review', 'published', 'archived'], true)) {
        $w[] = "{$a}.status = ?";
        $params[] = get('status');
    }
    return $w ? implode(' AND ', $w) : '1=1';
}

function crud_order(array $m): string
{
    $sort = get('sort', (string) $m['default_sort']);
    $dir = strtolower(get('dir', $m['default_dir'])) === 'asc' ? 'ASC' : 'DESC';
    $col = $m['sorts'][$sort] ?? null;
    if (!$col) {
        return $m['alias'] . '.id DESC';
    }
    return $col . ' ' . $dir . ', ' . $m['alias'] . '.id DESC';
}

function crud_fetch(array $m, int $id, bool $forEdit = false): ?array
{
    if ($id <= 0) {
        return null;
    }
    $params = [];
    $where = crud_where($m, $params, false);
    array_unshift($params, $id);
    $row = q_one("SELECT {$m['select']} FROM {$m['from']} WHERE {$m['alias']}.id = ? AND {$where} LIMIT 1", $params);
    if (!$row) {
        return null;
    }
    if ($m['can_view_row'] && !($m['can_view_row'])($row)) {
        return null;
    }
    if ($forEdit && $m['can_edit_row'] && !($m['can_edit_row'])($row)) {
        return null;
    }
    return $row;
}

function crud_title(array $m, array $row): string
{
    if (isset($m['title_of'])) {
        return (string) ($m['title_of'])($row);
    }
    return (string) ($row['title'] ?? $row['name'] ?? ('#' . $row['id']));
}

// ------------------------------------------------------------------- list
function crud_list(array $m): void
{
    crud_require($m, 'list');
    $params = [];
    $where = crud_where($m, $params);
    [$page, $per, $offset] = pager_params($m['per_page']);
    $total = (int) q_val("SELECT COUNT(*) FROM {$m['from']} WHERE {$where}", $params);
    $rows = q_all("SELECT {$m['select']} FROM {$m['from']} WHERE {$where} ORDER BY " . crud_order($m) . " LIMIT {$per} OFFSET {$offset}", $params);

    $actions = '';
    if (crud_can($m, 'import')) {
        $actions .= '<a class="btn btn-secondary" href="' . e(crud_url($m, ['action' => 'import'])) . '">Import</a>';
    }
    if (crud_can($m, 'export') && $total > 0) {
        $actions .= '<a class="btn btn-secondary" href="' . e(current_url(['action' => 'export', 'page' => null])) . '">Export CSV</a>';
    }
    if (crud_can($m, 'create')) {
        $actions .= '<a class="btn btn-primary" href="' . e(crud_url($m, ['action' => 'create'])) . '">New ' . e($m['singular']) . '</a>';
    }
    admin_start($m['title'], [[$m['title'], null]]);
    page_bar($m['title'], fmt_num($total) . ' ' . ($total === 1 ? 'record' : 'records') . (get('show') === 'archived' ? ' in the archive' : ''), $actions);
    echo flash_render();
    if ($m['list_intro']) {
        echo '<p class="intro">' . $m['list_intro'] . '</p>';
    }
    crud_filter_bar($m);

    if (!$rows) {
        $filtered = get('q') !== '' || array_filter(array_keys($_GET), fn($k) => strpos($k, 'f_') === 0 && get($k) !== '');
        echo '<div class="empty"><h2>' . ($filtered ? 'No matching records' : 'Nothing here yet') . '</h2><p>'
            . ($filtered ? 'Try a different search or clear the filters.' : (crud_can($m, 'create') ? 'Create the first ' . e($m['singular']) . ' to get started.' : 'Records you are allowed to see will appear here.')) . '</p></div>';
    } else {
        echo '<div class="table-wrap"><table class="table table-cards"><thead><tr>';
        foreach ($m['columns'] as $c) {
            $sortKey = $c['sort'] ?? null;
            if ($sortKey && isset($m['sorts'][$sortKey])) {
                $cur = get('sort', (string) $m['default_sort']) === $sortKey;
                $dir = $cur && strtolower(get('dir', $m['default_dir'])) === 'asc' ? 'desc' : 'asc';
                $ariaSort = $cur ? (strtolower(get('dir', $m['default_dir'])) === 'asc' ? 'ascending' : 'descending') : null;
                echo '<th scope="col"' . ($ariaSort ? ' aria-sort="' . $ariaSort . '"' : '') . '><a href="' . e(current_url(['sort' => $sortKey, 'dir' => $dir, 'page' => null])) . '">' . e($c['label']) . ($cur ? ($ariaSort === 'ascending' ? ' ↑' : ' ↓') : '') . '</a></th>';
            } else {
                echo '<th scope="col">' . e($c['label']) . '</th>';
            }
        }
        echo '<th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($m['columns'] as $i => $c) {
                $val = isset($c['value']) ? ($c['value'])($r) : e($r[$c['key']] ?? '');
                if ($i === 0 || !empty($c['link'])) {
                    $val = '<a class="row-link" href="' . e(crud_url($m, ['action' => 'view', 'id' => $r['id']])) . '">' . ($val !== '' ? $val : '#' . (int) $r['id']) . '</a>';
                }
                $cls = trim(($i === 0 ? 'td-primary ' : '') . ($c['class'] ?? ''));
                echo '<td data-label="' . e($c['label']) . '"' . ($cls !== '' ? ' class="' . e($cls) . '"' : '') . '>' . $val . '</td>';
            }
            echo '<td class="row-actions"><a href="' . e(crud_url($m, ['action' => 'view', 'id' => $r['id']])) . '">View</a>';
            if (crud_can($m, 'edit') && !($m['soft_delete'] && $r['archived_at']) && (!$m['can_edit_row'] || ($m['can_edit_row'])($r))) {
                echo '<a href="' . e(crud_url($m, ['action' => 'edit', 'id' => $r['id']])) . '">Edit</a>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo pager_html($total, $page, $per);
    }
    admin_end();
}

function crud_filter_bar(array $m): void
{
    echo '<form class="filters" method="get" role="search" aria-label="Filter ' . e($m['title']) . '">';
    foreach ($m['page_params'] as $k => $v) {
        echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    if ($m['search']) {
        echo '<div class="filter filter-search"><label for="flt_q">Search</label><input type="search" id="flt_q" name="q" value="' . e(get('q')) . '" placeholder="Search…" maxlength="100"></div>';
    }
    $active = 0;
    foreach ($_GET as $k => $v) {
        if (is_string($v) && $v !== '' && (strpos($k, 'f_') === 0 || in_array($k, ['review', 'status', 'show'], true))) {
            $active++;
        }
    }
    echo '<button type="button" class="btn btn-secondary filter-toggle" aria-expanded="' . ($active ? 'true' : 'false') . '" aria-controls="filter-more">'
        . 'Filters' . ($active ? ' (' . $active . ' active)' : '') . '</button>';
    echo '<div class="filter-more' . ($active ? ' open' : '') . '" id="filter-more">';
    foreach ($m['filters'] as $key => $f) {
        $type = $f['type'] ?? 'select';
        $id = 'flt_' . $key;
        if ($type === 'hidden') {
            $hv = get('f_' . $key);
            if ($hv !== '') {
                echo '<input type="hidden" name="f_' . e($key) . '" value="' . e($hv) . '"><div class="filter filter-note"><span>' . e($f['label']) . ': '
                    . e(isset($f['describe']) ? ($f['describe'])($hv) : $hv) . '</span> <a href="' . e(current_url(['f_' . $key => null, 'page' => null])) . '">Clear</a></div>';
            }
            continue;
        }
        echo '<div class="filter"><label for="' . e($id) . '">' . e($f['label']) . '</label>';
        if ($type === 'region') {
            echo select_html('f_' . $key, regions_list(), get('f_' . $key), ['id' => $id, 'data-geo' => 'region', 'data-geo-group' => 'flt'], 'All regions');
        } elseif ($type === 'province') {
            $rid = get('f_region');
            echo select_html('f_' . $key, provinces_for(ctype_digit($rid) ? (int) $rid : null), get('f_' . $key), ['id' => $id, 'data-geo' => 'province', 'data-geo-group' => 'flt'], 'All provinces');
        } elseif ($type === 'city') {
            $rid = get('f_region');
            $pid = get('f_province');
            echo select_html('f_' . $key, cities_for(ctype_digit($rid) ? (int) $rid : null, ctype_digit($pid) ? (int) $pid : null), get('f_' . $key), ['id' => $id, 'data-geo' => 'city', 'data-geo-group' => 'flt'], 'All cities/municipalities');
        } elseif ($type === 'date_from' || $type === 'date_to') {
            echo '<input type="date" id="' . e($id) . '" name="f_' . e($key) . '" value="' . e(get('f_' . $key)) . '">';
        } else {
            echo select_html('f_' . $key, field_options($f), get('f_' . $key), ['id' => $id], 'All');
        }
        echo '</div>';
    }
    if ($m['review']) {
        echo '<div class="filter"><label for="flt_review">Review</label>' . select_html('review', ['pending' => 'Pending approval', 'approved' => 'Approved', 'rejected' => 'Rejected'], get('review'), ['id' => 'flt_review'], 'All') . '</div>';
    }
    if ($m['workflow']) {
        echo '<div class="filter"><label for="flt_status">Status</label>' . select_html('status', ['draft' => 'Draft', 'in_review' => 'In review', 'published' => 'Published', 'archived' => 'Archived'], get('status'), ['id' => 'flt_status'], 'All') . '</div>';
    }
    if ($m['soft_delete'] && !$m['workflow']) {
        echo '<div class="filter"><label for="flt_show">Records</label>' . select_html('show', ['archived' => 'Archived'], get('show'), ['id' => 'flt_show'], 'Active') . '</div>';
    }
    echo '</div>';
    echo '<div class="filter filter-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-ghost" href="' . e(crud_url($m)) . '">Reset</a></div>';
    echo '</form>';
}

// ------------------------------------------------------------------- view
function crud_display_value(array $m, string $name, array $f, array $row): string
{
    $type = $f['type'] ?? 'text';
    if (isset($f['display'])) {
        return ($f['display'])($row);
    }
    $col = $f['column'];
    $v = $row[$col] ?? null;
    if (!empty($f['encrypt'])) {
        $plain = decrypt_value($v);
        if ($plain === null || $plain === '') {
            return '';
        }
        if (!empty($f['sensitive_perm']) && !can($f['sensitive_perm'])) {
            return e($type === 'email' ? mask_email($plain) : mask_phone($plain)) . ' <span class="muted">(masked)</span>';
        }
        return e($plain);
    }
    if ($v === null || $v === '') {
        return '';
    }
    switch ($type) {
        case 'select':
        case 'lookup_sql':
            $opts = field_options($f);
            return e($opts[$v] ?? $v);
        case 'bool':
            return $v ? 'Yes' : 'No';
        case 'date':
            return e(fmt_date($v));
        case 'datetime':
            return e(fmt_datetime($v));
        case 'number':
            return e(fmt_num($v));
        case 'richtext':
            return '<div class="prose">' . $v . '</div>';
        case 'textarea':
            return nl2p($v);
        case 'region':
            return e((string) q_val('SELECT name FROM regions WHERE id = ?', [(int) $v]));
        case 'province':
            return e((string) q_val('SELECT name FROM provinces WHERE id = ?', [(int) $v]));
        case 'city':
            return e((string) q_val('SELECT name FROM cities_municipalities WHERE id = ?', [(int) $v]));
        case 'coop':
            return can('cooperatives.view') ? '<a href="' . e(url('admin/cooperatives.php', ['action' => 'view', 'id' => $v])) . '">' . e(lookup_label('coop', $v)) . '</a>' : e(lookup_label('coop', $v));
        case 'person':
            return can('persons.view') ? '<a href="' . e(url('admin/persons.php', ['action' => 'view', 'id' => $v])) . '">' . e(lookup_label('person', $v)) . '</a>' : e(lookup_label('person', $v));
        case 'user':
            return e(lookup_label('user', $v));
        case 'media':
            $mr = media_row((int) $v);
            if (!$mr) {
                return '';
            }
            $img = strpos($mr['mime_type'], 'image/') === 0 ? '<img class="thumb" src="' . e(media_url((int) $v, true)) . '" alt="" loading="lazy"> ' : '';
            return $img . '<a href="' . e(media_url((int) $v)) . '" target="_blank" rel="noopener">' . e($mr['original_name']) . '</a>';
        case 'url':
            return '<a href="' . e($v) . '" target="_blank" rel="noopener noreferrer">' . e($v) . '</a>';
    }
    return e($v);
}

function crud_view(array $m, int $id): void
{
    crud_require($m, 'view');
    $row = crud_fetch($m, $id);
    if (!$row) {
        abort(404);
    }
    if (!empty($m['log_view'])) {
        audit_log('view', $m['key'], $id, 'Viewed ' . $m['singular']);
    }
    $title = crud_title($m, $row);
    $archived = $m['soft_delete'] && !empty($row['archived_at']);
    $editable = crud_can($m, 'edit') && !$archived && (!$m['can_edit_row'] || ($m['can_edit_row'])($row));

    $actions = '';
    if ($editable) {
        $actions .= '<a class="btn btn-primary" href="' . e(crud_url($m, ['action' => 'edit', 'id' => $id])) . '">Edit</a>';
    }
    foreach ($m['custom_actions'] as $key => $ca) {
        if (!($ca['show'])($row)) {
            continue;
        }
        $actions .= '<form method="post" action="' . e(crud_url($m, ['action' => 'custom', 'do' => $key, 'id' => $id])) . '"' . (!empty($ca['confirm']) ? ' data-confirm="' . e($ca['confirm']) . '"' : '') . '>' . csrf_field()
            . (!empty($ca['remarks']) ? '<input type="hidden" name="remarks" value="" data-remarks="' . e($ca['remarks']) . '">' : '')
            . '<button class="btn ' . e($ca['class'] ?? 'btn-secondary') . '" type="submit">' . e($ca['label']) . '</button></form>';
    }
    if ($m['soft_delete'] && crud_can($m, 'archive') && (!isset($m['can_archive_row']) || ($m['can_archive_row'])($row))) {
        $act = $archived ? 'restore' : 'archive';
        $actions .= '<form method="post" action="' . e(crud_url($m, ['action' => $act, 'id' => $id])) . '" data-confirm="' . ($archived ? 'Restore this record?' : 'Archive this record? It will be hidden from active lists but kept in the archive.') . '">' . csrf_field()
            . '<button class="btn ' . ($archived ? 'btn-secondary' : 'btn-danger-ghost') . '" type="submit">' . ($archived ? 'Restore' : 'Archive') . '</button></form>';
    }
    admin_start($title, [[$m['title'], crud_url($m)], [$title, null]]);
    page_bar($title, ucfirst($m['singular']) . ($archived ? ', archived ' . fmt_date($row['archived_at']) : ''), $actions);
    echo flash_render();

    // Status strip
    $strip = [];
    if ($m['review']) {
        $strip[] = '<span>Review: ' . status_text($row['review_status'], ['pending' => 'Pending approval'][$row['review_status']] ?? null) . '</span>';
        if ($row['review_status'] === 'rejected' && !empty($row['review_remarks'])) {
            $strip[] = '<span>Remarks: ' . e($row['review_remarks']) . '</span>';
        }
    }
    if ($m['workflow']) {
        $st = $row['status'];
        $label = $st === 'published' && $row['publish_at'] && strtotime($row['publish_at']) > time() ? 'Scheduled for ' . fmt_datetime($row['publish_at']) : null;
        $strip[] = '<span>Status: ' . status_text($st, $label) . '</span>';
        if (!empty($m['public_url']) && ($pu = ($m['public_url'])($row))) {
            $strip[] = '<span><a href="' . e($pu) . (strpos($pu, '?') === false ? '?' : '&') . 'preview=1" target="_blank" rel="noopener">' . ($st === 'published' ? 'Open public page' : 'Preview') . '</a></span>';
        }
    }
    if ($strip) {
        echo '<div class="status-strip">' . implode('', $strip) . '</div>';
    }
    if ($m['review'] && $row['review_status'] === 'pending' && crud_can($m, 'approve') && !$archived) {
        echo '<div class="panel panel-attn"><h2 class="panel-title">This ' . e($m['singular']) . ' is waiting for approval</h2>'
            . '<form method="post" class="inline-form" action="' . e(crud_url($m, ['action' => 'review', 'id' => $id])) . '">' . csrf_field()
            . '<div class="field field-full"><label for="rv_remarks">Remarks (required when rejecting)</label><input type="text" id="rv_remarks" name="remarks" maxlength="255"></div>'
            . '<div class="btn-row"><button class="btn btn-primary" name="decision" value="approve" type="submit">Approve</button>'
            . '<button class="btn btn-danger-ghost" name="decision" value="reject" type="submit">Reject</button></div></form></div>';
    }
    if ($m['workflow'] && !$archived) {
        crud_workflow_panel($m, $row);
    }

    $sections = $m['view_sections'] ?? $m['sections'] ?? ['Details' => array_keys($m['fields'])];
    foreach ($sections as $label => $names) {
        $items = '';
        foreach ($names as $n) {
            if (!isset($m['fields'][$n]) || !empty($m['fields'][$n]['hide_view'])) {
                continue;
            }
            $f = $m['fields'][$n];
            $val = crud_display_value($m, $n, $f, $row);
            $wide = in_array($f['type'] ?? 'text', ['richtext', 'textarea'], true);
            $items .= '<div class="dl-item' . ($wide ? ' dl-wide' : '') . '"><dt>' . e($f['label'] ?? label_from_key($n)) . '</dt><dd>' . ($val !== '' ? $val : '<span class="muted">Not set</span>') . '</dd></div>';
        }
        if ($items) {
            echo '<section class="panel"><h2 class="panel-title">' . e($label) . '</h2><dl class="dl">' . $items . '</dl></section>';
        }
    }
    if ($m['view_panels']) {
        ($m['view_panels'])($row, $m);
    }
    if ($m['attachments']) {
        crud_attachments_panel($m, $row, $editable);
    }
    $meta = [];
    if (!empty($row['created_at'])) {
        $meta[] = 'Created ' . fmt_datetime($row['created_at']) . (!empty($row['created_by']) ? ' by ' . e(lookup_label('user', $row['created_by'])) : '');
    }
    if (!empty($row['updated_at'])) {
        $meta[] = 'Last updated ' . fmt_datetime($row['updated_at']) . (!empty($row['updated_by']) ? ' by ' . e(lookup_label('user', $row['updated_by'])) : '');
    }
    if ($meta) {
        echo '<p class="meta-line">' . implode('<br>', $meta) . '</p>';
    }
    if ($m['revisions']) {
        crud_revisions_panel($m, $id);
    }
    admin_end();
}

function crud_revisions_panel(array $m, int $id): void
{
    $revs = q_all('SELECT r.*, u.full_name FROM record_revisions r LEFT JOIN users u ON u.id = r.user_id WHERE r.module = ? AND r.record_id = ? ORDER BY r.id DESC LIMIT 50', [$m['key'], $id]);
    echo '<details class="panel panel-collapsible"' . (get('history') ? ' open' : '') . '><summary class="panel-title">Revision history (' . count($revs) . ')</summary>';
    if (!$revs) {
        echo '<p class="muted">No changes recorded yet.</p>';
    } else {
        echo '<ol class="timeline">';
        foreach ($revs as $r) {
            $changes = $r['changes'] ? json_decode($r['changes'], true) : [];
            echo '<li><div class="tl-head"><strong>' . e(label_from_key($r['action'])) . '</strong> by ' . e($r['full_name'] ?: 'System') . ' <span class="muted">' . e(fmt_datetime($r['created_at'])) . '</span></div>';
            if (is_array($changes) && $changes) {
                echo '<ul class="changes">';
                foreach ($changes as $field => $c) {
                    $label = $m['fields'][$field]['label'] ?? label_from_key((string) $field);
                    if (is_array($c) && array_key_exists('to', $c)) {
                        echo '<li><span class="ch-field">' . e($label) . '</span>: <del>' . e(excerpt((string) ($c['from'] ?? ''), 80)) . '</del> → <ins>' . e(excerpt((string) ($c['to'] ?? ''), 80)) . '</ins></li>';
                    } else {
                        echo '<li><span class="ch-field">' . e($label) . '</span>: ' . e(excerpt(is_scalar($c) ? (string) $c : json_encode($c), 80)) . '</li>';
                    }
                }
                echo '</ul>';
            }
            echo '</li>';
        }
        echo '</ol>';
    }
    echo '</details>';
}

function crud_attachments_panel(array $m, array $row, bool $editable): void
{
    $files = q_all('SELECT a.*, ma.original_name, ma.file_size, ma.mime_type, u.full_name FROM record_attachments a JOIN media_assets ma ON ma.id = a.media_id LEFT JOIN users u ON u.id = a.uploaded_by
                    WHERE a.module = ? AND a.record_id = ? AND a.archived_at IS NULL ORDER BY a.id DESC', [$m['key'], (int) $row['id']]);
    echo '<section class="panel" id="attachments"><h2 class="panel-title">Supporting documents</h2>';
    if (!$files) {
        echo '<p class="muted">No documents attached.</p>';
    } else {
        echo '<ul class="file-list">';
        foreach ($files as $f) {
            echo '<li><a href="' . e(media_url((int) $f['media_id'])) . '" target="_blank" rel="noopener">' . e($f['title'] ?: $f['original_name']) . '</a>'
                . ' <span class="muted">' . e(human_size((int) $f['file_size'])) . ', ' . e(fmt_date($f['created_at'])) . ', ' . e($f['full_name'] ?? '') . '</span>';
            if ($editable && (crud_can($m, 'archive') || (int) $f['uploaded_by'] === uid())) {
                echo '<form method="post" class="inline" action="' . e(crud_url($m, ['action' => 'detach', 'id' => $row['id'], 'att' => $f['id']])) . '" data-confirm="Remove this document?">' . csrf_field() . '<button class="link-btn" type="submit">Remove</button></form>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }
    if ($editable) {
        echo '<form method="post" enctype="multipart/form-data" class="attach-form" action="' . e(crud_url($m, ['action' => 'attach', 'id' => $row['id']])) . '">' . csrf_field()
            . '<div class="field field-half"><label for="att_title">Document title</label><input type="text" id="att_title" name="title" maxlength="191"></div>'
            . '<div class="field field-half"><label for="att_file">File</label><input type="file" id="att_file" name="file" required accept="' . e(media_accept_attr('attachment')) . '"></div>'
            . '<div class="btn-row"><button class="btn btn-secondary" type="submit">Upload document</button></div></form>';
    }
    echo '</section>';
}

function crud_attach(array $m): void
{
    if (!is_post()) {
        abort(405);
    }
    crud_require($m, 'edit');
    $id = get_int('id');
    $row = crud_fetch($m, $id, true);
    if (!$row || !$m['attachments']) {
        abort(404);
    }
    try {
        if (!upload_present('file')) {
            throw new ValidationError('Choose a file to upload.');
        }
        $title = mb_substr(post('title'), 0, 191);
        $mid = media_upload($_FILES['file'], 'attachment', 'private', $title ?: null);
        q_insert('record_attachments', ['module' => $m['key'], 'record_id' => $id, 'media_id' => $mid, 'title' => $title ?: null, 'uploaded_by' => uid(), 'created_at' => now()]);
        revision_log($m['key'], $id, 'document_added', ['document' => ['from' => null, 'to' => $title ?: 'file']]);
        audit_log('attach', $m['key'], $id, 'Attached document ' . ($title ?: ''));
        flash('success', 'Document uploaded.');
    } catch (ValidationError $e) {
        flash('error', $e->getMessage());
    }
    redirect(crud_url($m, ['action' => 'view', 'id' => $id]) . '#attachments');
}

function crud_detach(array $m): void
{
    if (!is_post()) {
        abort(405);
    }
    crud_require($m, 'edit');
    $id = get_int('id');
    $row = crud_fetch($m, $id, true);
    $att = q_one('SELECT * FROM record_attachments WHERE id = ? AND module = ? AND record_id = ?', [get_int('att'), $m['key'], $id]);
    if (!$row || !$att) {
        abort(404);
    }
    if (!crud_can($m, 'archive') && (int) $att['uploaded_by'] !== uid()) {
        deny('Remove attachment');
    }
    q('UPDATE record_attachments SET archived_at = ? WHERE id = ?', [now(), $att['id']]);
    revision_log($m['key'], $id, 'document_removed', ['document' => ['from' => $att['title'] ?: 'file', 'to' => null]]);
    audit_log('detach', $m['key'], $id, 'Removed document #' . $att['id']);
    flash('success', 'Document removed.');
    redirect(crud_url($m, ['action' => 'view', 'id' => $id]) . '#attachments');
}

// ------------------------------------------------------------------- form
function crud_form(array $m, string $mode, ?int $id): void
{
    crud_require($m, $mode === 'create' ? 'create' : 'edit');
    $old = null;
    if ($mode === 'edit') {
        $old = crud_fetch($m, (int) $id, true);
        if (!$old || ($m['soft_delete'] && !empty($old['archived_at']))) {
            abort(404);
        }
    }
    $errors = [];
    $values = crud_initial_values($m, $old);

    if (is_post()) {
        [$data, $errors, $values] = crud_collect($m, $_POST, $old, 'form');
        if (!$errors) {
            try {
                $wf = $m['workflow'] ? post('wf', 'draft') : null;
                $newId = crud_save($m, $data, $old, $values, $wf);
                $verb = $mode === 'create' ? 'created' : 'saved';
                $note = '';
                if ($m['review'] && !crud_can($m, 'approve')) {
                    $note = ' It was submitted for approval.';
                }
                flash('success', ucfirst($m['singular']) . ' ' . $verb . '.' . $note);
                redirect(crud_url($m, ['action' => 'view', 'id' => $newId]));
            } catch (ValidationError $e) {
                $errors['_form'] = $e->getMessage();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors['_form'] = 'This record conflicts with an existing one (a unique value such as a reference or registration number is already used).';
                } else {
                    throw $e;
                }
            }
        }
    }

    $title = $mode === 'create' ? 'New ' . $m['singular'] : 'Edit ' . crud_title($m, $old);
    $crumbs = [[$m['title'], crud_url($m)]];
    if ($old) {
        $crumbs[] = [crud_title($m, $old), crud_url($m, ['action' => 'view', 'id' => $old['id']])];
    }
    $crumbs[] = [$mode === 'create' ? 'New' : 'Edit', null];
    admin_start($title, $crumbs);
    page_bar($title, $mode === 'create' ? 'Fields marked * are required.' : 'Changes are recorded in the revision history.');
    echo flash_render();
    $formErrors = $errors;
    if (isset($formErrors['_form'])) {
        echo '<div class="notice notice-error" role="alert">' . e($formErrors['_form']) . '</div>';
        unset($formErrors['_form']);
    }
    echo form_error_summary($formErrors);
    $hasFile = (bool) array_filter($m['fields'], fn($f) => ($f['type'] ?? '') === 'media');
    echo '<form method="post" class="record-form" novalidate' . ($hasFile ? ' enctype="multipart/form-data"' : '') . ' data-unsaved>' . csrf_field();
    $sections = $m['sections'] ?? ['Details' => array_keys($m['fields'])];
    foreach ($sections as $label => $names) {
        $html = '';
        foreach ($names as $n) {
            $f = $m['fields'][$n] ?? null;
            if (!$f || !empty($f['computed']) || !empty($f['hide_form']) || ($mode === 'edit' && !empty($f['create_only']))) {
                continue;
            }
            if (!empty($f['perm']) && !can($f['perm'])) {
                continue;
            }
            $html .= form_field($n, $f, $values[$n] ?? null, $errors, ['values' => $values, 'mode' => $mode]);
        }
        if ($html) {
            echo '<fieldset class="panel"><legend class="panel-title">' . e($label) . '</legend><div class="grid">' . $html . '</div></fieldset>';
        }
    }
    echo '<div class="form-actions">';
    if ($m['workflow']) {
        $st = $old['status'] ?? 'draft';
        echo '<button class="btn btn-secondary" type="submit" name="wf" value="draft">' . ($st === 'published' ? 'Save and unpublish' : 'Save draft') . '</button>';
        if (!crud_can($m, 'publish')) {
            echo '<button class="btn btn-primary" type="submit" name="wf" value="in_review">Submit for review</button>';
        } else {
            echo '<button class="btn btn-primary" type="submit" name="wf" value="published">' . ($st === 'published' ? 'Save changes' : 'Publish') . '</button>';
        }
    } else {
        echo '<button class="btn btn-primary" type="submit">' . ($mode === 'create' ? 'Create ' . e($m['singular']) : 'Save changes') . '</button>';
    }
    echo '<a class="btn btn-ghost" href="' . e($old ? crud_url($m, ['action' => 'view', 'id' => $old['id']]) : crud_url($m)) . '">Cancel</a></div>';
    echo '</form>';
    admin_end();
}

function crud_initial_values(array $m, ?array $old): array
{
    $values = [];
    foreach ($m['fields'] as $n => $f) {
        if ($old) {
            $v = $old[$f['column']] ?? null;
            if (!empty($f['encrypt'])) {
                $v = decrypt_value($v);
                if (!empty($f['sensitive_perm']) && !can($f['sensitive_perm'])) {
                    $v = null; // never pre-fill protected values for users without access
                }
            }
            $values[$n] = $v;
        } else {
            $values[$n] = $m['defaults'][$n] ?? ($f['default'] ?? null);
        }
    }
    if (!$old) {
        foreach ($m['prefill'] as $n) {
            $v = get($n);
            if ($v !== '' && isset($m['fields'][$n])) {
                $values[$n] = $v;
            }
        }
    }
    return $values;
}

/**
 * Validate input and map it to database columns.
 * Returns [data, errors, formValues]. $source is 'form' or 'import'.
 */
function crud_collect(array $m, array $in, ?array $old, string $source): array
{
    $data = [];
    $errors = [];
    $values = [];
    $import = $source === 'import';
    foreach ($m['fields'] as $n => $f) {
        $type = $f['type'] ?? 'text';
        if (!empty($f['computed']) || $type === 'static' || !empty($f['hide_form'])) {
            continue;
        }
        if ($old && !empty($f['create_only'])) {
            continue;
        }
        if (!empty($f['perm']) && !can($f['perm'])) {
            continue;
        }
        if ($import && isset($f['import']) && $f['import'] === false) {
            continue;
        }
        $label = $f['label'] ?? label_from_key($n);
        $raw = $in[$n] ?? null;
        $raw = is_string($raw) ? trim($raw) : $raw;
        if ($import && is_string($raw)) {
            $raw = import_clean($raw);
        }
        $req = !empty($f['required']);

        // Protected fields left blank on edit keep their stored value
        if (!empty($f['encrypt']) && $old && ($raw === '' || $raw === null) && !empty($f['sensitive_perm']) && !can($f['sensitive_perm'])) {
            $values[$n] = '';
            continue;
        }

        switch ($type) {
            case 'bool':
                $v = in_array(strtolower((string) $raw), ['1', 'yes', 'true', 'y', 'on'], true) ? 1 : 0;
                $values[$n] = $v;
                $data[$f['column']] = $v;
                continue 2;
            case 'media':
                $values[$n] = $old[$f['column']] ?? null;
                if ($import) {
                    continue 2;
                }
                if (upload_present($n . '__file')) {
                    try {
                        $info = media_validate_upload($_FILES[$n . '__file'], $f['kind'] ?? 'image');
                        $data['__media'][$f['column']] = ['info' => $info, 'visibility' => $f['visibility'] ?? 'private', 'title' => $label];
                    } catch (ValidationError $e) {
                        $errors[$n] = $label . ': ' . $e->getMessage();
                    }
                } elseif (!empty($in[$n . '__remove'])) {
                    $data[$f['column']] = null;
                    $values[$n] = null;
                }
                if ($req && empty($values[$n]) && empty($data['__media'][$f['column']])) {
                    $errors[$n] = $label . ' is required.';
                }
                continue 2;
            case 'person':
                $values['person_mode'] = $in['person_mode'] ?? 'existing';
                foreach (['np_first_name', 'np_middle_name', 'np_last_name', 'np_suffix', 'np_email', 'np_phone', 'np_confirm_different'] as $k) {
                    $values[$k] = isset($in[$k]) && is_string($in[$k]) ? trim($in[$k]) : '';
                }
                if ($import) {
                    $values['person_mode'] = 'import';
                    foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'email', 'phone'] as $k) {
                        $values['np_' . $k] = import_clean($in['person_' . $k] ?? '');
                    }
                }
                if ($values['person_mode'] === 'existing') {
                    $pid = ctype_digit((string) $raw) ? (int) $raw : 0;
                    $values[$n] = $pid ?: null;
                    if ($pid && !q_val('SELECT COUNT(*) FROM persons WHERE id = ? AND archived_at IS NULL', [$pid])) {
                        $errors[$n] = 'The selected person was not found.';
                    } elseif (!$pid && $req) {
                        $errors[$n] = 'Select a person or choose "New person".';
                    } else {
                        $data[$f['column']] = $pid ?: null;
                    }
                } else {
                    if ($values['np_first_name'] === '') {
                        $errors['np_first_name'] = 'First name is required.';
                    }
                    if ($values['np_last_name'] === '') {
                        $errors['np_last_name'] = 'Last name is required.';
                    }
                    if ($values['np_email'] !== '' && !filter_var($values['np_email'], FILTER_VALIDATE_EMAIL)) {
                        $errors['np_email'] = 'Enter a valid email address.';
                    }
                    foreach (['np_first_name', 'np_middle_name', 'np_last_name'] as $k) {
                        if (mb_strlen($values[$k]) > 80) {
                            $errors[$k] = 'Use at most 80 characters.';
                        }
                    }
                    if (!isset($errors['np_first_name'], $errors['np_last_name'])) {
                        $data['__new_person'] = $values;
                        $data[$f['column']] = 0; // filled in during save
                    }
                }
                continue 2;
        }

        if ($raw === null || $raw === '') {
            if ($req) {
                $errors[$n] = $label . ' is required.';
            }
            $values[$n] = '';
            $data[$f['column']] = null;
            if (!empty($f['encrypt'])) {
                if (!empty($f['bidx'])) {
                    $data[$f['bidx']] = null;
                }
            }
            continue;
        }
        if (!is_string($raw)) {
            $errors[$n] = $label . ' is invalid.';
            continue;
        }
        $v = $raw;
        $values[$n] = $v;
        $max = $f['max'] ?? (in_array($type, ['textarea'], true) ? 5000 : ($type === 'richtext' ? 200000 : 191));
        switch ($type) {
            case 'email':
                if (!filter_var($v, FILTER_VALIDATE_EMAIL) || mb_strlen($v) > 191) {
                    $errors[$n] = $label . ' must be a valid email address.';
                }
                break;
            case 'url':
                if (!preg_match('#^https?://#i', $v) || !filter_var($v, FILTER_VALIDATE_URL) || mb_strlen($v) > 255) {
                    $errors[$n] = $label . ' must be a full web address starting with https://.';
                }
                break;
            case 'tel':
                if (!preg_match('/^[0-9+()\-\s.\/]{5,40}$/', $v)) {
                    $errors[$n] = $label . ' may contain only digits, spaces and + ( ) - characters.';
                }
                break;
            case 'number':
                $v = str_replace(',', '', $v);
                if (!is_numeric($v) || (empty($f['decimal']) && (string) (int) $v !== ltrim($v, '+'))) {
                    $errors[$n] = $label . ' must be a whole number.';
                } elseif (isset($f['min']) && (float) $v < $f['min']) {
                    $errors[$n] = $label . ' must be at least ' . $f['min'] . '.';
                } elseif (isset($f['max_value']) && (float) $v > $f['max_value']) {
                    $errors[$n] = $label . ' must be at most ' . $f['max_value'] . '.';
                }
                $v = empty($f['decimal']) ? (int) $v : (float) $v;
                break;
            case 'date':
                $d = $import ? parse_date_input($v) : (valid_date($v) ? $v : null);
                if (!$d) {
                    $errors[$n] = $label . ' must be a valid date.';
                }
                $v = $d;
                break;
            case 'datetime':
                $d = parse_datetime_input($v) ?? ($import ? (($pd = parse_date_input($v)) ? $pd . ' 00:00:00' : null) : null);
                if (!$d) {
                    $errors[$n] = $label . ' must be a valid date and time.';
                }
                $v = $d;
                break;
            case 'select':
                $opts = field_options($f);
                if (!array_key_exists($v, $opts)) {
                    $match = $import ? array_search(mb_strtolower($v), array_map('mb_strtolower', $opts), true) : false;
                    if ($match === false) {
                        $errors[$n] = $label . ' has an invalid value.';
                    } else {
                        $v = (string) $match;
                    }
                }
                break;
            case 'lookup_sql':
                $opts = field_options($f);
                if (!ctype_digit($v) || !isset($opts[(int) $v])) {
                    $match = $import ? array_search(mb_strtolower($v), array_map('mb_strtolower', $opts), true) : false;
                    if ($match === false) {
                        $errors[$n] = $label . ' has an invalid value.';
                    } else {
                        $v = (int) $match;
                    }
                } else {
                    $v = (int) $v;
                }
                break;
            case 'region':
                $v = $import ? geo_resolve_region($v) : (ctype_digit($v) ? (int) $v : null);
                if (!$v || !q_val('SELECT COUNT(*) FROM regions WHERE id = ?', [$v])) {
                    $errors[$n] = $label . ' is not a valid region.';
                }
                break;
            case 'province':
                $regionVal = $data[$f['region_field'] ?? 'region_id'] ?? null;
                $v = $import ? geo_resolve_province($v, $regionVal) : (ctype_digit($v) ? (int) $v : null);
                if (!$v) {
                    $errors[$n] = $label . ' is not a valid province.';
                }
                break;
            case 'city':
                $regionVal = $data[$f['region_field'] ?? 'region_id'] ?? null;
                $provVal = $data[$f['province_field'] ?? 'province_id'] ?? null;
                $v = $import ? geo_resolve_city($v, $regionVal, $provVal) : (ctype_digit($v) ? (int) $v : null);
                if (!$v) {
                    $errors[$n] = $label . ' is not a valid city or municipality.';
                }
                break;
            case 'coop':
                if ($import) {
                    $v = (int) (q_val('SELECT id FROM cooperatives WHERE reference_no = ? AND archived_at IS NULL', [$v]) ?: q_val('SELECT id FROM cooperatives WHERE name = ? AND archived_at IS NULL LIMIT 1', [$v]) ?: 0);
                } else {
                    $v = ctype_digit($v) ? (int) $v : 0;
                }
                if (!$v || !q_val('SELECT COUNT(*) FROM cooperatives WHERE id = ? AND archived_at IS NULL', [$v])) {
                    $errors[$n] = $label . ': cooperative not found.';
                } elseif (!scope_allows_coop($v)) {
                    $errors[$n] = $label . ': this cooperative is outside your assigned scope.';
                }
                break;
            case 'user':
                $v = $import ? (int) (q_val('SELECT id FROM users WHERE username = ? OR email = ?', [$v, $v]) ?: 0) : (ctype_digit($v) ? (int) $v : 0);
                if (!$v || !q_val("SELECT COUNT(*) FROM users WHERE id = ? AND account_status = 'active'", [$v])) {
                    $errors[$n] = $label . ': user account not found.';
                }
                break;
            case 'richtext':
                $v = sanitize_html($v);
                $values[$n] = $v;
                if (mb_strlen($v) > $max) {
                    $errors[$n] = $label . ' is too long.';
                }
                break;
            default:
                if (mb_strlen($v) > $max) {
                    $errors[$n] = $label . " must be at most {$max} characters.";
                }
                if (!empty($f['pattern']) && !preg_match($f['pattern'], $v)) {
                    $errors[$n] = $f['pattern_message'] ?? ($label . ' has an invalid format.');
                }
        }
        if (isset($errors[$n])) {
            continue;
        }
        if (!empty($f['encrypt'])) {
            $data[$f['column']] = encrypt_value((string) $v);
            if (!empty($f['bidx'])) {
                $data[$f['bidx']] = blind_index((string) $v);
            }
            $data['__plain'][$n] = $v;
        } else {
            $data[$f['column']] = $v;
        }
    }

    foreach ($m['fields'] as $n => $f) {
        if (!empty($f['virtual'])) {
            unset($data[$f['column']]);
        }
    }

    // Geographic consistency: derive region/province from the chosen city
    foreach (crud_geo_groups($m) as $g) {
        [$rc, $pc, $cc] = $g;
        $cityId = $cc ? ($data[$cc] ?? null) : null;
        if ($cityId) {
            $city = q_one('SELECT region_id, province_id FROM cities_municipalities WHERE id = ?', [(int) $cityId]);
            if ($city) {
                if ($rc && !empty($data[$rc]) && (int) $data[$rc] !== (int) $city['region_id']) {
                    $errors[$cc] = 'The city or municipality is not in the selected region.';
                }
                if ($pc && !empty($data[$pc]) && $city['province_id'] && (int) $data[$pc] !== (int) $city['province_id']) {
                    $errors[$cc] = 'The city or municipality is not in the selected province.';
                }
                if ($rc) {
                    $data[$rc] = (int) $city['region_id'];
                }
                if ($pc) {
                    $data[$pc] = $city['province_id'] ? (int) $city['province_id'] : null;
                }
            }
        } elseif ($pc && !empty($data[$pc])) {
            $prov = q_one('SELECT region_id FROM provinces WHERE id = ?', [(int) $data[$pc]]);
            if (!$prov || ($rc && !empty($data[$rc]) && (int) $prov['region_id'] !== (int) $data[$rc])) {
                $errors[$pc] = 'The province is not in the selected region.';
            } elseif ($rc) {
                $data[$rc] = (int) $prov['region_id'];
            }
        }
    }

    // Unique fields
    foreach ($m['fields'] as $n => $f) {
        if (!empty($f['unique']) && isset($data[$f['column']]) && $data[$f['column']] !== null && $data[$f['column']] !== '') {
            $p = [$data[$f['column']]];
            $sql = 'SELECT id FROM ' . ident($m['table']) . ' WHERE ' . ident($f['column']) . ' = ?';
            if ($old) {
                $sql .= ' AND id <> ?';
                $p[] = (int) $old['id'];
            }
            if (q_val($sql . ' LIMIT 1', $p)) {
                $errors[$n] = ($f['label'] ?? label_from_key($n)) . ' is already used by another record.';
            }
        }
    }

    if (isset($m['validate'])) {
        ($m['validate'])($data, $old, $errors, $values);
    }

    // Write scope: the record being saved must fall within the user's scope
    if (!$errors && $m['scope'] && isset($m['scope_of'])) {
        $merged = array_merge($old ?? [], array_filter($data, fn($k) => strpos((string) $k, '__') !== 0, ARRAY_FILTER_USE_KEY));
        [$r, $p, $c, $k] = ($m['scope_of'])($merged) + [null, null, null, null];
        if ($k !== null && !$r && !$p && !$c) {
            if (!scope_allows_coop((int) $k)) {
                $errors['_scope'] = 'This record is outside your assigned data scope.';
            }
        } elseif (!scope_allows($r ? (int) $r : null, $p ? (int) $p : null, $c ? (int) $c : null, $k ? (int) $k : null)) {
            $errors['_scope'] = 'The selected location is outside your assigned data scope.';
        }
    }
    return [$data, $errors, $values];
}

/** Geo field groups: list of [regionCol, provinceCol, cityCol]. */
function crud_geo_groups(array $m): array
{
    $groups = [];
    foreach ($m['fields'] as $n => $f) {
        $t = $f['type'] ?? '';
        if (in_array($t, ['region', 'province', 'city'], true)) {
            $g = $f['geo_group'] ?? 'g';
            $groups[$g] = $groups[$g] ?? [null, null, null];
            $groups[$g][['region' => 0, 'province' => 1, 'city' => 2][$t]] = $f['column'];
        }
    }
    return array_values($groups);
}

function geo_resolve_region(string $v): ?int
{
    $v = trim($v);
    if (ctype_digit($v) && strlen($v) < 4) {
        return (int) q_val('SELECT id FROM regions WHERE id = ?', [(int) $v]) ?: null;
    }
    return (int) q_val('SELECT id FROM regions WHERE name = ? OR short_name = ? OR psgc_code = ? LIMIT 1', [$v, $v, $v]) ?: null;
}

function geo_resolve_province(string $v, $regionId): ?int
{
    $p = [trim($v), trim($v)];
    $sql = 'SELECT id FROM provinces WHERE (name = ? OR psgc_code = ?)';
    if ($regionId) {
        $sql .= ' AND region_id = ?';
        $p[] = (int) $regionId;
    }
    return (int) q_val($sql . ' LIMIT 1', $p) ?: null;
}

function geo_resolve_city(string $v, $regionId, $provinceId): ?int
{
    $v = trim($v);
    $cands = [$v, 'City of ' . preg_replace('/\s+city$/i', '', $v), preg_replace('/^city of\s+/i', '', $v) . ' City'];
    foreach ($cands as $name) {
        $p = [$name, $name];
        $sql = 'SELECT id FROM cities_municipalities WHERE (name = ? OR psgc_code = ?)';
        if ($provinceId) {
            $sql .= ' AND province_id = ?';
            $p[] = (int) $provinceId;
        } elseif ($regionId) {
            $sql .= ' AND region_id = ?';
            $p[] = (int) $regionId;
        }
        $id = (int) q_val($sql . ' LIMIT 1', $p);
        if ($id) {
            return $id;
        }
    }
    return null;
}

/** Insert or update inside a transaction. Returns the record id. */
function crud_save(array $m, array $data, ?array $old, array $values, ?string $wf = null, bool $fromImport = false): int
{
    return tx(function () use ($m, $data, $old, $values, $wf, $fromImport) {
        // New person record (dedupe by normalized name / email)
        if (isset($data['__new_person'])) {
            $np = $data['__new_person'];
            $key = name_key($np['np_first_name'], $np['np_last_name'], $np['np_middle_name']);
            $emailIdx = $np['np_email'] !== '' ? blind_index($np['np_email']) : null;
            $existing = $emailIdx ? q_val('SELECT id FROM persons WHERE email_bidx = ? AND archived_at IS NULL LIMIT 1', [$emailIdx]) : null;
            if (!$existing) {
                $existing = q_val('SELECT id FROM persons WHERE name_key = ? AND archived_at IS NULL LIMIT 1', [$key]);
                if ($existing && ($fromImport || !empty($np['np_confirm_different']))) {
                    $existing = $fromImport ? $existing : null; // imports reuse the same-name person; forms may confirm a different person
                }
            }
            if ($existing && !$fromImport) {
                $label = lookup_label('person', $existing);
                throw new ValidationError("A person named {$label} already exists. Choose \"Existing person\" and select them, or tick the confirmation box if this is a different person.");
            }
            if ($existing) {
                $pid = (int) $existing;
            } else {
                $pid = q_insert('persons', [
                    'first_name' => $np['np_first_name'], 'middle_name' => $np['np_middle_name'] ?: null, 'last_name' => $np['np_last_name'],
                    'suffix' => $np['np_suffix'] ?: null, 'name_key' => $key, 'email_enc' => encrypt_value($np['np_email'] ?: null),
                    'email_bidx' => $emailIdx, 'phone_enc' => encrypt_value($np['np_phone'] ?: null), 'created_by' => uid(), 'created_at' => now(),
                ]);
                revision_log('persons', $pid, 'created', null);
                audit_log('create', 'persons', $pid, 'Created person record');
            }
            foreach ($m['fields'] as $f) {
                if (($f['type'] ?? '') === 'person') {
                    $data[$f['column']] = $pid;
                }
            }
        }
        unset($data['__new_person']);
        // Uploaded media
        foreach ($data['__media'] ?? [] as $col => $mm) {
            $data[$col] = media_store($mm['info'], $mm['visibility'], $mm['title']);
        }
        unset($data['__media']);
        $plain = $data['__plain'] ?? [];
        unset($data['__plain']);

        if (isset($m['before_save'])) {
            ($m['before_save'])($data, $old, $values);
        }
        $a = $m['table'];
        $isNew = $old === null;
        $hasCols = array_flip(crud_table_columns($a));
        $userId = uid();
        if ($isNew) {
            if (isset($hasCols['created_at'])) {
                $data['created_at'] = now();
            }
            if (isset($hasCols['created_by'])) {
                $data['created_by'] = $userId;
            }
        } else {
            if (isset($hasCols['updated_at'])) {
                $data['updated_at'] = now();
            }
            if (isset($hasCols['updated_by'])) {
                $data['updated_by'] = $userId;
            }
        }
        $pendingReview = false;
        if ($m['review']) {
            $pendingReview = !$fromImport && !crud_can($m, 'approve');
            $data['review_status'] = $pendingReview ? 'pending' : 'approved';
            if (!$pendingReview) {
                $data['review_remarks'] = null;
            }
        }
        $wfChange = null;
        if ($m['workflow'] && $wf !== null) {
            $wf = in_array($wf, ['draft', 'in_review', 'published'], true) ? $wf : 'draft';
            if ($wf === 'published' && !crud_can($m, 'publish')) {
                $wf = 'in_review';
            }
            $data['status'] = $wf;
            if ($wf === 'published') {
                $data['reviewed_by'] = $userId;
                if (empty($old['published_at'])) {
                    $data['published_at'] = $data['publish_at'] ?? null ?: now();
                }
            }
            $wfChange = ($old['status'] ?? null) !== $wf ? $wf : null;
        }
        if ($isNew) {
            $id = q_insert($a, $data);
            revision_log($m['key'], $id, $fromImport ? 'imported' : 'created', crud_redacted_snapshot($m, $data));
            audit_log($fromImport ? 'import' : 'create', $m['key'], $id, 'Created ' . $m['singular']);
        } else {
            $id = (int) $old['id'];
            $changes = diff_changes($old, $data, crud_redact_cols($m));
            q_update($a, $data, $id);
            if ($changes) {
                revision_log($m['key'], $id, 'updated', $changes);
            }
            audit_log('update', $m['key'], $id, 'Updated ' . $m['singular'] . ($changes ? ': ' . implode(', ', array_keys($changes)) : ''), 'success', $changes ?: null);
        }
        if ($pendingReview) {
            q("UPDATE approval_requests SET request_status = 'superseded' WHERE module = ? AND record_id = ? AND request_status = 'pending'", [$m['key'], $id]);
            q_insert('approval_requests', ['module' => $m['key'], 'record_id' => $id, 'request_type' => $isNew ? 'create' : 'update',
                'summary' => mb_substr(($isNew ? 'New ' : 'Changes to ') . $m['singular'] . ': ' . crud_title($m, array_merge($old ?? [], $data, ['id' => $id])), 0, 255),
                'request_status' => 'pending', 'requested_by' => $userId, 'created_at' => now()]);
            $geo = isset($m['scope_of']) ? ($m['scope_of'])(array_merge($old ?? [], $data)) + [null, null, null, null] : [null, null, null, null];
            notify_perm($m['perm'] . '.approve', 'approval_pending', 'A ' . $m['singular'] . ' is waiting for your approval', '', crud_url($m, ['action' => 'view', 'id' => $id]),
                ['region' => $geo[0], 'province' => $geo[1], 'city' => $geo[2], 'coop' => $geo[3]], $userId);
        }
        if ($wfChange === 'in_review') {
            notify_perm($m['wf_perm'] . '.publish', 'approval_pending', ucfirst($m['singular']) . ' submitted for review', '', crud_url($m, ['action' => 'view', 'id' => $id]), null, $userId);
        }
        if ($wfChange) {
            revision_log($m['key'], $id, 'status_' . $wfChange, null);
        }
        if (isset($m['after_save'])) {
            ($m['after_save'])($id, $data, $old, $values, $plain);
        }
        return $id;
    });
}

function crud_table_columns(string $table): array
{
    static $cache = [];
    return $cache[$table] ??= q_col('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
}

function crud_redact_cols(array $m): array
{
    $cols = $m['redact'];
    foreach ($m['fields'] as $f) {
        if (!empty($f['encrypt'])) {
            $cols[] = $f['column'];
            if (!empty($f['bidx'])) {
                $cols[] = $f['bidx'];
            }
        }
    }
    return $cols;
}

function crud_redacted_snapshot(array $m, array $data): array
{
    $red = crud_redact_cols($m);
    $out = [];
    foreach ($data as $k => $v) {
        if (in_array($k, ['created_at', 'created_by'], true) || $v === null || $v === '') {
            continue;
        }
        $out[$k] = ['from' => null, 'to' => in_array($k, $red, true) ? '[protected]' : (is_string($v) ? mb_substr($v, 0, 200) : $v)];
    }
    return $out;
}

// ------------------------------------------------------- archive / review
function crud_archive(array $m, string $action): void
{
    if (!is_post()) {
        abort(405);
    }
    crud_require($m, 'archive');
    $id = get_int('id');
    $row = crud_fetch($m, $id);
    if (!$row || !$m['soft_delete']) {
        abort(404);
    }
    if (isset($m['can_archive_row']) && !($m['can_archive_row'])($row)) {
        flash('error', 'This record cannot be archived.');
        redirect(crud_url($m, ['action' => 'view', 'id' => $id]));
    }
    tx(function () use ($m, $id, $action, $row) {
        $data = ['archived_at' => $action === 'archive' ? now() : null];
        if ($m['workflow']) {
            $data['status'] = $action === 'archive' ? 'archived' : 'draft';
        }
        q_update($m['table'], $data, $id);
        revision_log($m['key'], $id, $action === 'archive' ? 'archived' : 'restored', null);
        audit_log($action, $m['key'], $id, ucfirst($action) . 'd ' . $m['singular'] . ': ' . crud_title($m, $row));
        if (isset($m['after_archive'])) {
            ($m['after_archive'])($id, $action);
        }
    });
    flash('success', ucfirst($m['singular']) . ($action === 'archive' ? ' archived.' : ' restored.'));
    redirect(crud_url($m, ['action' => 'view', 'id' => $id]));
}

function crud_review(array $m): void
{
    if (!is_post()) {
        abort(405);
    }
    crud_require($m, 'approve');
    $id = get_int('id');
    $row = crud_fetch($m, $id);
    if (!$row || $row['review_status'] !== 'pending') {
        abort(404);
    }
    $decision = post('decision') === 'approve' ? 'approved' : 'rejected';
    $remarks = mb_substr(post('remarks'), 0, 255);
    if ($decision === 'rejected' && $remarks === '') {
        flash('error', 'Enter remarks explaining why the submission is rejected.');
        redirect(crud_url($m, ['action' => 'view', 'id' => $id]));
    }
    crud_apply_review($m, $row, $decision, $remarks);
    flash('success', 'Submission ' . $decision . '.');
    redirect(crud_url($m, ['action' => 'view', 'id' => $id]));
}

function crud_apply_review(array $m, array $row, string $decision, string $remarks): void
{
    $id = (int) $row['id'];
    tx(function () use ($m, $id, $decision, $remarks) {
        q_update($m['table'], ['review_status' => $decision, 'review_remarks' => $remarks ?: null], $id);
        $req = q_one("SELECT * FROM approval_requests WHERE module = ? AND record_id = ? AND request_status = 'pending' ORDER BY id DESC LIMIT 1", [$m['key'], $id]);
        if ($req) {
            q('UPDATE approval_requests SET request_status = ?, reviewed_by = ?, review_remarks = ?, reviewed_at = ? WHERE id = ?', [$decision, uid(), $remarks ?: null, now(), $req['id']]);
            if ($req['requested_by'] && (int) $req['requested_by'] !== uid()) {
                notify_user((int) $req['requested_by'], 'approval_done', 'Your ' . $m['singular'] . ' submission was ' . $decision, $remarks, crud_url($m, ['action' => 'view', 'id' => $id]));
            }
        }
        revision_log($m['key'], $id, $decision, $remarks ? ['remarks' => ['from' => null, 'to' => $remarks]] : null);
        audit_log($decision === 'approved' ? 'approve' : 'reject', $m['key'], $id, ucfirst($decision) . ' ' . $m['singular'] . ($remarks ? ': ' . $remarks : ''));
    });
}

// ---------------------------------------------------------------- workflow
function crud_workflow_panel(array $m, array $row): void
{
    $st = $row['status'];
    $btns = '';
    $mk = function (string $to, string $label, string $cls, string $confirm = '') use ($m, $row) {
        return '<form method="post" action="' . e(crud_url($m, ['action' => 'workflow', 'id' => $row['id'], 'to' => $to])) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<button class="btn ' . $cls . '" type="submit">' . e($label) . '</button></form>';
    };
    if ($st === 'draft' && crud_can($m, 'edit') && !crud_can($m, 'publish')) {
        $btns .= $mk('in_review', 'Submit for review', 'btn-primary');
    }
    if (in_array($st, ['draft', 'in_review'], true) && crud_can($m, 'publish')) {
        $btns .= $mk('published', 'Publish now', 'btn-primary', 'Publish this item on the public website?');
    }
    if ($st === 'in_review' && (crud_can($m, 'review_content') || crud_can($m, 'publish'))) {
        $btns .= $mk('draft', 'Return to draft', 'btn-secondary');
    }
    if ($st === 'published' && crud_can($m, 'publish')) {
        $btns .= $mk('draft', 'Unpublish', 'btn-secondary', 'Remove this item from the public website?');
    }
    if ($btns) {
        echo '<div class="panel panel-flat"><div class="btn-row">' . $btns . '</div></div>';
    }
}

function crud_workflow_action(array $m): void
{
    if (!is_post() || !$m['workflow']) {
        abort(405);
    }
    $to = get('to');
    if (!in_array($to, ['draft', 'in_review', 'published'], true)) {
        abort(400);
    }
    if ($to === 'published') {
        crud_require($m, 'publish');
    } elseif ($to === 'in_review') {
        crud_require($m, 'edit');
    } else {
        if (!crud_can($m, 'publish') && !crud_can($m, 'review_content')) {
            deny('Workflow change');
        }
    }
    $id = get_int('id');
    $row = crud_fetch($m, $id);
    if (!$row || $row['archived_at']) {
        abort(404);
    }
    $data = ['status' => $to, 'updated_at' => now(), 'updated_by' => uid()];
    if ($to === 'published') {
        $data['reviewed_by'] = uid();
        if (!$row['published_at']) {
            $data['published_at'] = $row['publish_at'] ?: now();
        }
    }
    q_update($m['table'], $data, $id);
    revision_log($m['key'], $id, 'status_' . $to, null);
    audit_log($to === 'published' ? 'publish' : ($to === 'in_review' ? 'submit_review' : 'unpublish'), $m['key'], $id, 'Status changed to ' . $to);
    if ($to === 'in_review') {
        notify_perm($m['wf_perm'] . '.publish', 'approval_pending', ucfirst($m['singular']) . ' submitted for review', '', crud_url($m, ['action' => 'view', 'id' => $id]), null, uid());
    }
    if ($to === 'published' && isset($m['on_publish'])) {
        ($m['on_publish'])(array_merge($row, $data));
    }
    flash('success', ['published' => 'Published.', 'in_review' => 'Submitted for review.', 'draft' => 'Moved back to draft.'][$to]);
    redirect(crud_url($m, ['action' => 'view', 'id' => $id]));
}

function crud_custom(array $m): void
{
    if (!is_post()) {
        abort(405);
    }
    $key = get('do');
    $ca = $m['custom_actions'][$key] ?? null;
    if (!$ca) {
        abort(404);
    }
    require_login();
    if (!can($ca['perm'])) {
        deny('Custom action ' . $key);
    }
    $id = get_int('id');
    $row = crud_fetch($m, $id);
    if (!$row || !($ca['show'])($row)) {
        abort(404);
    }
    $msg = ($ca['run'])($row, $m);
    flash('success', $msg ?: 'Done.');
    redirect(crud_url($m, ['action' => 'view', 'id' => $id]));
}

// ------------------------------------------------------------------ export
function crud_export(array $m): void
{
    crud_require($m, 'export');
    $params = [];
    $where = crud_where($m, $params);
    $cols = $m['export_columns'] ?? null;
    if (!$cols) {
        $cols = [];
        foreach ($m['fields'] as $n => $f) {
            if (in_array($f['type'] ?? 'text', ['media', 'richtext', 'static'], true)) {
                continue;
            }
            $cols[$f['label'] ?? label_from_key($n)] = fn($r) => excerpt(strip_tags(crud_display_value($m, $n, $f, $r)), 2000);
        }
    }
    $max = 50000;
    $rows = q_all("SELECT {$m['select']} FROM {$m['from']} WHERE {$where} ORDER BY " . crud_order($m) . " LIMIT {$max}", $params);
    $sensitive = $m['sensitive'];
    $filters = [];
    foreach ($_GET as $k => $v) {
        if (is_string($v) && $v !== '' && !in_array($k, ['action', 'page'], true)) {
            $filters[] = $k . '=' . mb_substr($v, 0, 40);
        }
    }
    q_insert('export_logs', ['user_id' => uid(), 'module' => $m['key'], 'filters' => mb_substr(implode('; ', $filters), 0, 500), 'row_count' => count($rows),
        'is_sensitive' => $sensitive ? 1 : 0, 'ip_address' => client_ip(), 'created_at' => now()]);
    audit_log('export', $m['key'], null, 'Exported ' . count($rows) . ' rows' . ($sensitive ? ' (sensitive)' : ''));
    if ($sensitive && count($rows) >= 500) {
        security_event('large_sensitive_export', 'medium', 'Exported ' . count($rows) . ' rows from ' . $m['key']);
    }
    $fname = $m['key'] . '-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    no_cache_headers();
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_map('csv_safe', array_keys($cols)));
    foreach ($rows as $r) {
        $line = [];
        foreach ($cols as $fn) {
            $line[] = csv_safe(html_entity_decode((string) $fn($r), ENT_QUOTES, 'UTF-8'));
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}
