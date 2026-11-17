<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$types = doc_type_options();
$p = [];
$w = [pub_where('x', $p), 'x.current_version_id IS NOT NULL'];
if (!current_user()) {
    $w[] = "x.visibility = 'public'";
}
if (isset($types[get('type')])) {
    $w[] = 'x.doc_type = ?';
    $p[] = get('type');
}
if (get_int('category')) {
    $w[] = 'x.category_id = ?';
    $p[] = get_int('category');
}
if (($q = mb_substr(get('q'), 0, 80)) !== '') {
    $w[] = '(x.title LIKE ? OR x.reference_no LIKE ? OR x.summary LIKE ?)';
    array_push($p, like_escape($q), like_escape($q), like_escape($q));
}
$rows = q_all('SELECT x.*, v.version_label FROM policies x JOIN policy_versions v ON v.id = x.current_version_id WHERE ' . implode(' AND ', $w) . ' ORDER BY x.doc_type, x.title LIMIT 300', $p);
public_start('Policies and procedures', ['crumbs' => [['About', url('about.php')], ['Policies and procedures', null]], 'sub' => 'Official policies, procedures, guidelines, circulars, manuals and forms, with their effective dates and revisions.']);
echo '<div class="wrap section"><form class="pub-filters" method="get" role="search"><div class="filter"><label for="po_q">Search</label><input type="search" id="po_q" name="q" value="' . e(get('q')) . '" maxlength="80"></div>'
    . '<div class="filter"><label for="po_t">Type</label>' . select_html('type', $types, get('type'), ['id' => 'po_t'], 'All types') . '</div>'
    . '<div class="filter"><label for="po_c">Category</label>' . select_html('category', q_pairs("SELECT id, name FROM content_categories WHERE scope = 'document' AND is_active = 1 ORDER BY sort_order"), get('category'), ['id' => 'po_c'], 'All categories') . '</div><button class="btn btn-primary" type="submit">Filter</button></form>';
if (!$rows) {
    echo empty_state('No documents found', 'Official documents will be listed here once published.');
} else {
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Document</th><th scope="col">Type</th><th scope="col">Reference</th><th scope="col">Version</th><th scope="col">Effective</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td class="td-primary" data-label="Document"><a class="row-link" href="' . e(url('policy.php', ['slug' => $r['slug']])) . '">' . e($r['title']) . '</a>' . ($r['visibility'] === 'internal' ? '<span class="sub">Internal</span>' : '') . '</td><td data-label="Type">' . e($types[$r['doc_type']]) . '</td>'
            . '<td data-label="Reference">' . e($r['reference_no']) . '</td><td data-label="Version">' . e($r['version_label']) . '</td><td data-label="Effective">' . e(fmt_date($r['effective_date'])) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</div>';
public_end();
