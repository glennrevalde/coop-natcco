<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$page = public_page_row('history');
$p = [];
$milestones = q_all('SELECT x.* FROM history_milestones x WHERE ' . pub_where('x', $p) . ' ORDER BY COALESCE(x.milestone_date, x.created_at), x.sort_order', $p);
$p = [];
$galleries = q_all('SELECT x.* FROM galleries x WHERE x.is_archival = 1 AND ' . pub_where('x', $p) . ' ORDER BY x.created_at DESC', $p);
public_start('COOP NATCCO history', ['crumbs' => [['About', url('about.php')], ['History', null]], 'sub' => e($page['summary'] ?? 'Establishment, milestones and achievements, based on verified records.')]);
echo '<div class="wrap section">';
if ($page && $page['body']) {
    echo '<article class="article"><div class="prose">' . $page['body'] . '</div></article>';
}
echo '<div class="mt">' . section_head('Timeline of major events');
if (!$milestones) {
    echo empty_state('The official history is being prepared', 'Milestones are published here once they have been verified against approved sources.');
} else {
    echo '<ol class="timeline-pub">';
    foreach ($milestones as $m) {
        echo '<li><div class="box"><div class="tl-card"><div><div class="tl-date">' . e($m['date_label']) . '</div><h3>' . e($m['title']) . '</h3>' . nl2p($m['description'])
            . '<p class="tl-source">Source: ' . e($m['source_reference']) . '</p></div>'
            . ($m['media_id'] ? '<a href="' . e(media_url((int) $m['media_id'])) . '" data-lightbox data-caption="' . e($m['title']) . '"><img src="' . e(media_url((int) $m['media_id'], true)) . '" alt="' . e($m['title']) . '" loading="lazy"></a>' : '') . '</div></div></li>';
    }
    echo '</ol>';
}
echo '</div>';
if ($galleries) {
    echo '<div class="mt">' . section_head('Archival photographs and documents', url('gallery.php'), 'All galleries') . '<ul class="gallery-grid">';
    foreach ($galleries as $g) {
        echo '<li><a href="' . e(url('gallery.php', ['slug' => $g['slug']])) . '">' . ($g['cover_media_id'] ? '<img src="' . e(media_url((int) $g['cover_media_id'], true)) . '" alt="" loading="lazy">' : '') . '<span>' . e($g['title']) . '</span></a></li>';
    }
    echo '</ul></div>';
}
echo '</div><dialog class="lightbox" id="lightbox"><form method="dialog"><button class="btn btn-secondary btn-sm" aria-label="Close">Close</button></form><img src="" alt=""><p></p></dialog>';
public_end();
