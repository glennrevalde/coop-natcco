<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$slug = get('slug');
if ($slug !== '') {
    $p = [$slug];
    $g = q_one('SELECT x.* FROM galleries x WHERE x.slug = ? AND ' . pub_where('x', $p), $p);
    if (!$g) {
        not_found();
    }
    $items = q_all('SELECT gi.*, m.alt_text FROM gallery_items gi JOIN media_assets m ON m.id = gi.media_id WHERE gi.gallery_id = ? AND m.archived_at IS NULL ORDER BY gi.sort_order, gi.id', [$g['id']]);
    public_start($g['title'], ['crumbs' => [['Photos and videos', url('gallery.php')], [excerpt($g['title'], 50), null]], 'sub' => e($g['description'] ?? '')]);
    echo '<div class="wrap section">';
    if (!$items) {
        echo empty_state('No photos yet', 'Photos will appear here once added.');
    } else {
        echo '<ul class="gallery-grid">';
        foreach ($items as $it) {
            $cap = $it['caption'] ?: ($it['alt_text'] ?: $g['title']);
            echo '<li><a href="' . e(media_url((int) $it['media_id'])) . '" data-lightbox data-caption="' . e($cap) . '"><img src="' . e(media_url((int) $it['media_id'], true)) . '" alt="' . e($cap) . '" loading="lazy">' . ($it['caption'] ? '<span>' . e($it['caption']) . '</span>' : '') . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</div><dialog class="lightbox" id="lightbox"><form method="dialog"><button class="btn btn-secondary btn-sm">Close</button></form><img src="" alt=""><p></p></dialog>';
    public_end();
    exit;
}
$p = [];
$galleries = q_all('SELECT x.*, (SELECT COUNT(*) FROM gallery_items gi WHERE gi.gallery_id = x.id) n FROM galleries x WHERE ' . pub_where('x', $p) . ' ORDER BY COALESCE(x.publish_at, x.published_at, x.created_at) DESC LIMIT 60', $p);
$p = [];
$videos = q_all("SELECT x.* FROM posts x WHERE x.video_url IS NOT NULL AND x.video_url <> '' AND " . pub_where('x', $p) . ' ORDER BY COALESCE(x.publish_at, x.published_at) DESC LIMIT 12', $p);
public_start('Photos and videos', ['crumbs' => [['Photos and videos', null]], 'sub' => 'Photo galleries and videos of COOP NATCCO Partylist activities.']);
echo '<div class="wrap section"><nav class="tabs" aria-label="Content types">';
foreach (UPDATE_TABS as $file => $label) {
    echo '<a href="' . e(url($file)) . '"' . ($file === 'gallery.php' ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
}
echo '</nav>' . section_head('Photo galleries');
if (!$galleries) {
    echo empty_state('No galleries yet', 'Photo galleries will be published here.');
} else {
    echo '<ul class="gallery-grid">';
    foreach ($galleries as $g) {
        echo '<li><a href="' . e(url('gallery.php', ['slug' => $g['slug']])) . '">' . ($g['cover_media_id'] ? '<img src="' . e(media_url((int) $g['cover_media_id'], true)) . '" alt="" loading="lazy">' : '<span class="card-img card-img-empty"><img src="' . e(asset('img/logo.jpg')) . '" alt=""></span>')
            . '<span><strong>' . e($g['title']) . '</strong><br>' . (int) $g['n'] . ' photos</span></a></li>';
    }
    echo '</ul>';
}
if ($videos) {
    echo '<div class="mt">' . section_head('Videos') . '<div class="grid-3">';
    foreach ($videos as $v) {
        echo post_card($v);
    }
    echo '</div></div>';
}
echo '</div>';
public_end();
