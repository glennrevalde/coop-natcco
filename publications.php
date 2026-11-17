<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

[$page, $per, $offset] = pager_params(20);
$type = in_array(get('type'), ['publication', 'newsletter'], true) ? get('type') : '';
$q = mb_substr(get('q'), 0, 80);
$p = [];
$w = [pub_where('x', $p), 'x.attachment_media_id IS NOT NULL'];
$w[] = $type ? 'x.post_type = ?' : "x.post_type IN ('publication','newsletter','achievement','news','announcement')";
if ($type) {
    $p[] = $type;
}
if ($q !== '') {
    $w[] = '(x.title LIKE ? OR x.summary LIKE ?)';
    array_push($p, like_escape($q), like_escape($q));
}
$where = implode(' AND ', $w);
$total = (int) q_val("SELECT COUNT(*) FROM posts x WHERE {$where}", $p);
$rows = q_all("SELECT x.*, m.extension, m.file_size, m.id AS mid FROM posts x JOIN media_assets m ON m.id = x.attachment_media_id WHERE {$where} ORDER BY COALESCE(x.publish_at, x.published_at) DESC LIMIT {$per} OFFSET {$offset}", $p);
$pp = [];
$docs = q_all("SELECT x.slug, x.title, x.doc_type, x.effective_date FROM policies x WHERE x.visibility = 'public' AND x.current_version_id IS NOT NULL AND " . pub_where('x', $pp) . ' ORDER BY x.title LIMIT 12', $pp);
public_start('Publications and downloads', ['crumbs' => [['Publications and downloads', null]], 'sub' => 'Official publications, newsletters and downloadable documents.']);
echo '<div class="wrap section split"><div>';
echo '<form class="pub-filters" method="get" role="search"><div class="filter"><label for="pb_q">Search</label><input type="search" id="pb_q" name="q" value="' . e($q) . '" maxlength="80"></div>'
    . '<div class="filter"><label for="pb_t">Type</label>' . select_html('type', ['publication' => 'Publications', 'newsletter' => 'Newsletters'], $type, ['id' => 'pb_t'], 'All downloads') . '</div><button class="btn btn-primary" type="submit">Filter</button></form>';
if (!$rows) {
    echo empty_state('No downloads yet', 'Official publications will be available here.');
} else {
    echo '<ul class="item-list">';
    foreach ($rows as $r) {
        echo '<li><a class="il-title" href="' . e(url('post.php', ['slug' => $r['slug']])) . '">' . e($r['title']) . '</a><div class="il-meta"><span>' . e(post_type_labels()[$r['post_type']]) . '</span><span>' . e(fmt_date($r['publish_at'] ?: $r['published_at'])) . '</span>'
            . '<a class="file-dl" href="' . e(media_url((int) $r['mid'])) . '">' . icon('download') . 'Download ' . e(strtoupper($r['extension'])) . ', ' . e(human_size((int) $r['file_size'])) . '</a></div>'
            . ($r['summary'] ? '<p class="small muted">' . e(excerpt($r['summary'], 180)) . '</p>' : '') . '</li>';
    }
    echo '</ul>' . public_pager($total, $page, $per);
}
echo '</div><aside><div class="box aside-box"><h2 class="h3">Policies, forms and manuals</h2>';
if ($docs) {
    $types = doc_type_options();
    echo '<ul class="foot-list related-list">';
    foreach ($docs as $d) {
        echo '<li><a href="' . e(url('policy.php', ['slug' => $d['slug']])) . '">' . e($d['title']) . '</a><br><span class="muted small">' . e($types[$d['doc_type']]) . ($d['effective_date'] ? ', effective ' . e(fmt_date($d['effective_date'])) : '') . '</span></li>';
    }
    echo '</ul>';
} else {
    echo '<p class="muted">No public documents yet.</p>';
}
echo '<p><a class="more" href="' . e(url('policies.php')) . '">Document repository' . icon('arrow') . '</a></p></div></aside></div>';
public_end();
