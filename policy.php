<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$p = [get('slug')];
$d = q_one('SELECT x.*, cc.name AS category_name FROM policies x LEFT JOIN content_categories cc ON cc.id = x.category_id WHERE x.slug = ? AND ' . pub_where('x', $p), $p);
if (!$d || ($d['visibility'] === 'internal' && !current_user())) {
    not_found();
}
$versions = q_all('SELECT * FROM policy_versions WHERE policy_id = ? ORDER BY id DESC', [$d['id']]);
$cur = null;
foreach ($versions as $v) {
    if ((int) $v['id'] === (int) $d['current_version_id']) {
        $cur = $v;
    }
}
$types = doc_type_options();
public_start($d['title'], ['crumbs' => [['Policies and procedures', url('policies.php')], [excerpt($d['title'], 50), null]], 'sub' => e($d['summary'] ?? '')]);
echo '<div class="wrap section split"><article class="article">';
if (!$cur) {
    echo '<p class="muted">No version has been published yet.</p>';
} else {
    echo '<p class="article-meta">Version ' . e($cur['version_label']) . ($cur['effective_date'] ? ', effective ' . e(fmt_date($cur['effective_date'])) : '') . '</p>';
    if ($cur['document_media_id']) {
        $m = media_row((int) $cur['document_media_id']);
        echo '<p><a class="btn btn-primary" href="' . e(media_url((int) $m['id'])) . '">' . icon('download') . ' Download ' . e(strtoupper($m['extension'])) . ' (' . e(human_size((int) $m['file_size'])) . ')</a></p>';
    }
    if ($cur['body']) {
        echo '<div class="prose">' . $cur['body'] . '</div>';
    }
}
echo '</article><aside><div class="box aside-box"><dl class="dl-pub"><dt>Type</dt><dd>' . e($types[$d['doc_type']]) . '</dd>' . ($d['reference_no'] ? '<dt>Reference</dt><dd>' . e($d['reference_no']) . '</dd>' : '')
    . ($d['category_name'] ? '<dt>Category</dt><dd>' . e($d['category_name']) . '</dd>' : '') . '</dl></div>';
if (count($versions) > 1) {
    echo '<div class="box aside-box mt"><h2 class="h3">Revision history</h2><ul class="foot-list related-list">';
    foreach ($versions as $v) {
        echo '<li><strong>' . e($v['version_label']) . '</strong>' . ((int) $v['id'] === (int) $d['current_version_id'] ? ' (current)' : '') . '<br><span class="muted small">Effective ' . e(fmt_date($v['effective_date'])) . ($v['change_notes'] ? ': ' . e($v['change_notes']) : '') . '</span>'
            . ($v['document_media_id'] ? '<br><a class="small" href="' . e(media_url((int) $v['document_media_id'])) . '">Download this version</a>' : '') . '</li>';
    }
    echo '</ul></div>';
}
echo '</aside></div>';
public_end();
