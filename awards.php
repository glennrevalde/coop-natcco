<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$recip = ['officer' => 'COOP NATCCO officer', 'cooperative' => 'Primary cooperative', 'organization' => 'Recognized organization', 'individual' => 'Individual'];
$slug = get('slug');
if ($slug !== '') {
    $p = [$slug];
    $a = q_one('SELECT x.*, cc.name AS category_name FROM awards x LEFT JOIN content_categories cc ON cc.id = x.category_id WHERE x.slug = ? AND ' . pub_where('x', $p), $p);
    if (!$a) {
        not_found();
    }
    $doc = media_row($a['document_media_id'] ? (int) $a['document_media_id'] : null);
    public_start($a['title'], ['crumbs' => [['Awards and recognition', url('awards.php')], [excerpt($a['title'], 50), null]], 'sub' => e($a['recipient_name'])]);
    echo '<div class="wrap section split"><article class="article">' . ($a['cover_media_id'] ? '<figure class="article-cover"><img src="' . e(media_url((int) $a['cover_media_id'])) . '" alt=""></figure>' : '')
        . '<div class="prose">' . ($a['description'] ?: '<p class="muted">No description published.</p>') . '</div>'
        . ($doc ? '<p><a class="file-dl" href="' . e(media_url((int) $doc['id'])) . '">' . icon('download') . 'Supporting document</a></p>' : '') . '</article>'
        . '<aside><div class="box"><dl class="dl-pub"><dt>Recipient</dt><dd>' . e($a['recipient_name']) . '<br><span class="muted small">' . e($recip[$a['recipient_type']]) . '</span></dd><dt>Awarded by</dt><dd>' . e($a['awarding_institution']) . '</dd>'
        . ($a['award_date'] ? '<dt>Date</dt><dd>' . e(fmt_date($a['award_date'])) . '</dd>' : '') . ($a['category_name'] ? '<dt>Category</dt><dd>' . e($a['category_name']) . '</dd>' : '') . '</dl></div></aside></div>';
    public_end();
    exit;
}
$p = [];
$w = [pub_where('x', $p)];
if (isset($recip[get('recipient')])) {
    $w[] = 'x.recipient_type = ?';
    $p[] = get('recipient');
}
if (get_int('category')) {
    $w[] = 'x.category_id = ?';
    $p[] = get_int('category');
}
$rows = q_all('SELECT x.* FROM awards x WHERE ' . implode(' AND ', $w) . ' ORDER BY x.award_date DESC, x.id DESC LIMIT 200', $p);
$byYear = [];
foreach ($rows as $r) {
    $byYear[$r['award_date'] ? date('Y', strtotime($r['award_date'])) : 'Undated'][] = $r;
}
public_start('Awards and recognition', ['crumbs' => [['Awards and recognition', null]], 'sub' => 'Recognition received by COOP NATCCO officers, primary cooperatives and partner organizations.']);
echo '<div class="wrap section"><form class="pub-filters" method="get"><div class="filter"><label for="aw_r">Recipient</label>' . select_html('recipient', $recip, get('recipient'), ['id' => 'aw_r'], 'All recipients') . '</div>'
    . '<div class="filter"><label for="aw_c">Category</label>' . select_html('category', q_pairs("SELECT id, name FROM content_categories WHERE scope = 'award' AND is_active = 1 ORDER BY sort_order"), get('category'), ['id' => 'aw_c'], 'All categories') . '</div><button class="btn btn-primary" type="submit">Filter</button></form>';
if (!$rows) {
    echo empty_state('No awards published yet', 'Awards and recognition will be listed here.');
}
foreach ($byYear as $year => $list) {
    echo '<div class="mt">' . section_head((string) $year) . '<ul class="item-list">';
    foreach ($list as $a) {
        echo '<li><a class="il-title" href="' . e(url('awards.php', ['slug' => $a['slug']])) . '">' . e($a['title']) . '</a><div class="il-meta"><span><strong>' . e($a['recipient_name']) . '</strong></span><span>' . e($recip[$a['recipient_type']]) . '</span><span>Awarded by ' . e($a['awarding_institution']) . '</span>'
            . ($a['award_date'] ? '<span>' . e(fmt_date($a['award_date'])) . '</span>' : '') . '</div></li>';
    }
    echo '</ul></div>';
}
echo '</div>';
public_end();
