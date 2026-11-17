<?php
/**
 * Generic CMS page by slug (also used by privacy.php, terms.php and accessibility.php).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/public.php';

$slug = $PAGE_SLUG ?? get('slug');
$page = public_page_row((string) $slug);
if (!$page) {
    not_found();
}
public_start($page['title'], ['crumbs' => [[$page['title'], null]], 'sub' => e($page['summary'] ?? ''), 'description' => $page['summary'] ?? '']);
echo '<div class="wrap section split"><article class="article"><div class="prose">' . ($page['body'] ?: '<p class="muted">This page has no content yet.</p>') . '</div>'
    . '<p class="article-meta mt">Last updated ' . e(fmt_date($page['updated_at'] ?: ($page['published_at'] ?: $page['created_at']))) . '</p></article><aside>';
if ($slug === 'privacy-notice') {
    $dpo = setting('dpo_name');
    $dpoEmail = setting('dpo_email');
    echo '<div class="box aside-box"><h2 class="h3">Data Protection Officer</h2>' . ($dpo || $dpoEmail ? '<p>' . e($dpo) . ($dpoEmail ? '<br><a href="mailto:' . e($dpoEmail) . '">' . e($dpoEmail) . '</a>' : '') . '</p>' : '<p class="muted">Contact details will be posted here.</p>')
        . '<a class="btn btn-primary" href="' . e(url('privacy-request.php')) . '">Make a privacy request</a></div>';
}
echo '<div class="box aside-box mt"><h2 class="h3">Related</h2><ul class="foot-list related-list">';
foreach (public_menu('footer') as $f) {
    echo '<li><a href="' . e(menu_href($f['url'])) . '">' . e($f['label']) . '</a></li>';
}
echo '</ul></div></aside></div>';
public_end();
