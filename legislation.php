<?php
/**
 * Legislative portal: verified official information is shown apart from organizational commentary.
 */
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$measures = measure_type_options();
$statuses = legislative_status_options();
$slug = get('slug');
if ($slug !== '') {
    $p = [$slug];
    $l = q_one('SELECT x.*, cc.name AS category_name FROM legislative_updates x LEFT JOIN content_categories cc ON cc.id = x.category_id WHERE x.slug = ? AND ' . pub_where('x', $p), $p);
    if (!$l) {
        not_found();
    }
    $doc = media_row($l['document_media_id'] ? (int) $l['document_media_id'] : null);
    $label = trim(($measures[$l['measure_type']] ?? '') . ' ' . $l['measure_number']);
    public_start($l['title'], ['crumbs' => [['Legislation', url('legislation.php')], [$label ?: excerpt($l['title'], 40), null]], 'description' => excerpt($l['official_summary'], 160)]);
    echo '<div class="wrap section split"><article class="article">';
    echo '<p class="measure-no">' . e($label) . ($l['congress'] ? ', ' . e($l['congress']) : '') . '</p>';
    echo '<div class="official-box"><h2>Official information</h2><p class="label-line">' . ($l['is_officially_verified'] ? status_text('verified', 'Verified against the official record') . ($l['verification_note'] ? ' <span class="muted">(' . e($l['verification_note']) . ')</span>' : '') : status_text('pending', 'Not yet verified against the official record')) . '</p>'
        . ($l['official_summary'] ? '<div class="prose">' . $l['official_summary'] . '</div>' : '<p class="muted">The official summary has not been published.</p>')
        . ($l['official_source_url'] ? '<p><a href="' . e($l['official_source_url']) . '" target="_blank" rel="noopener noreferrer">View the official record' . icon('arrow') . '</a></p>' : '')
        . ($doc ? '<p><a class="file-dl" href="' . e(media_url((int) $doc['id'])) . '">' . icon('download') . 'Download the official document (' . e(strtoupper($doc['extension'])) . ')</a></p>' : '') . '</div>';
    if ($l['commentary']) {
        echo '<div class="commentary-box"><h2>COOP NATCCO Partylist commentary</h2><p class="label-line">Organizational commentary, not part of the official record.</p><div class="prose">' . $l['commentary'] . '</div></div>';
    }
    $links = array_filter(array_map('trim', preg_split('/\R/', (string) $l['reference_links'])), fn($x) => preg_match('#^https?://#i', $x));
    if ($links) {
        echo '<h2 class="h3">Related references</h2><ul class="objectives">';
        foreach ($links as $lk) {
            echo '<li><a href="' . e($lk) . '" target="_blank" rel="noopener noreferrer">' . e(parse_url($lk, PHP_URL_HOST) . excerpt((string) parse_url($lk, PHP_URL_PATH), 50)) . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</article><aside><div class="box"><h2 class="h3">At a glance</h2><dl class="dl-pub">'
        . '<dt>Status</dt><dd>' . e($statuses[$l['legislative_status']] ?? '') . '</dd>'
        . ($l['filing_date'] ? '<dt>Filed</dt><dd>' . e(fmt_date($l['filing_date'])) . '</dd>' : '') . ($l['approval_date'] ? '<dt>Approved</dt><dd>' . e(fmt_date($l['approval_date'])) . '</dd>' : '')
        . ($l['sponsors'] ? '<dt>Authors or sponsors</dt><dd>' . nl2br(e($l['sponsors'])) . '</dd>' : '') . ($l['category_name'] ? '<dt>Subject</dt><dd>' . e($l['category_name']) . '</dd>' : '')
        . '<dt>Last updated</dt><dd>' . e(fmt_date($l['updated_at'] ?: $l['published_at'])) . '</dd></dl></div></aside></div>';
    public_end();
    exit;
}
[$page, $per, $offset] = pager_params(15);
$p = [];
$w = [pub_where('x', $p)];
if (isset($measures[get('type')])) {
    $w[] = 'x.measure_type = ?';
    $p[] = get('type');
}
if (isset($statuses[get('status')])) {
    $w[] = 'x.legislative_status = ?';
    $p[] = get('status');
}
if (get_int('category')) {
    $w[] = 'x.category_id = ?';
    $p[] = get_int('category');
}
if (get_int('year') >= 1987) {
    $w[] = 'YEAR(COALESCE(x.filing_date, x.published_at)) = ?';
    $p[] = get_int('year');
}
if (($q = mb_substr(get('q'), 0, 80)) !== '') {
    $w[] = '(x.title LIKE ? OR x.measure_number LIKE ? OR x.sponsors LIKE ?)';
    array_push($p, like_escape($q), like_escape($q), like_escape($q));
}
$where = implode(' AND ', $w);
$total = (int) q_val("SELECT COUNT(*) FROM legislative_updates x WHERE {$where}", $p);
$rows = q_all("SELECT x.* FROM legislative_updates x WHERE {$where} ORDER BY COALESCE(x.approval_date, x.filing_date, x.published_at) DESC LIMIT {$per} OFFSET {$offset}", $p);
$yp = [];
$years = q_col('SELECT DISTINCT YEAR(COALESCE(x.filing_date, x.published_at)) y FROM legislative_updates x WHERE ' . pub_where('x', $yp) . ' ORDER BY y DESC', $yp);
public_start('Legislative updates', ['crumbs' => [['Legislation', null]], 'sub' => 'Bills, resolutions and laws relevant to cooperatives, with their status and official sources.']);
echo '<div class="wrap section"><form class="pub-filters" method="get" role="search" aria-label="Search legislation">'
    . '<div class="filter"><label for="lg_q">Search</label><input type="search" id="lg_q" name="q" value="' . e(get('q')) . '" placeholder="Title, number or sponsor" maxlength="80"></div>'
    . '<div class="filter"><label for="lg_t">Measure</label>' . select_html('type', $measures, get('type'), ['id' => 'lg_t'], 'All measures') . '</div>'
    . '<div class="filter"><label for="lg_s">Status</label>' . select_html('status', $statuses, get('status'), ['id' => 'lg_s'], 'Any status') . '</div>'
    . '<div class="filter"><label for="lg_c">Subject</label>' . select_html('category', q_pairs("SELECT id, name FROM content_categories WHERE scope = 'legislative' AND is_active = 1 ORDER BY sort_order"), get('category'), ['id' => 'lg_c'], 'All subjects') . '</div>'
    . '<div class="filter"><label for="lg_y">Year</label>' . select_html('year', array_combine($years, $years) ?: [], get('year'), ['id' => 'lg_y'], 'All years') . '</div>'
    . '<button class="btn btn-primary" type="submit">Search</button></form>';
if (!$rows) {
    echo empty_state('No legislative records found', 'Try different filters. The archive grows as records are verified and published.');
} else {
    echo '<ul class="item-list">';
    foreach ($rows as $l) {
        echo '<li><span class="measure-no">' . e(trim(($measures[$l['measure_type']] ?? '') . ' ' . $l['measure_number'])) . '</span>' . ($l['congress'] ? ' <span class="muted small">' . e($l['congress']) . '</span>' : '') . '<br>'
            . '<a class="il-title" href="' . e(url('legislation.php', ['slug' => $l['slug']])) . '">' . e($l['title']) . '</a><div class="il-meta"><span>Status: <strong>' . e($statuses[$l['legislative_status']] ?? '') . '</strong></span>'
            . ($l['filing_date'] ? '<span>Filed ' . e(fmt_date($l['filing_date'])) . '</span>' : '') . ($l['approval_date'] ? '<span>Approved ' . e(fmt_date($l['approval_date'])) . '</span>' : '')
            . '<span>' . ($l['is_officially_verified'] ? status_text('verified', 'Official details verified') : status_text('pending', 'Verification in progress')) . '</span></div></li>';
    }
    echo '</ul>' . public_pager($total, $page, $per);
}
echo '</div>';
public_end();
