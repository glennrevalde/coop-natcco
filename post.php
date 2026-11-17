<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$slug = get('slug');
$p = [$slug];
$w = pub_where('x', $p);
$post = q_one("SELECT x.*, cc.name AS category_name FROM posts x LEFT JOIN content_categories cc ON cc.id = x.category_id WHERE x.slug = ? AND {$w}", $p);
if (!$post) {
    not_found();
}
if ($post['status'] === 'published' && empty($_SESSION['viewed'][$post['id']])) {
    $_SESSION['viewed'][$post['id']] = 1;
    q('UPDATE posts SET view_count = view_count + 1 WHERE id = ?', [$post['id']]);
}
$types = post_type_labels(true);
$listPage = ['news' => 'news.php', 'announcement' => 'announcements.php', 'achievement' => 'achievements.php', 'newsletter' => 'newsletters.php', 'program' => 'programs.php', 'activity' => 'programs.php', 'publication' => 'publications.php'][$post['post_type']];
$date = $post['publish_at'] ?: ($post['published_at'] ?: $post['created_at']);
$attach = media_row($post['attachment_media_id'] ? (int) $post['attachment_media_id'] : null);
$event = $post['event_id'] ? q_one("SELECT * FROM events x WHERE x.id = ? AND x.visibility = 'public' AND " . public_published_sql('x'), [$post['event_id'], now()]) : null;
$rp = [$post['post_type'], $post['id']];
$related = q_all("SELECT x.* FROM posts x WHERE x.post_type = ? AND x.id <> ? AND " . public_published_sql('x') . " ORDER BY COALESCE(x.publish_at, x.published_at) DESC LIMIT 3", array_merge($rp, [now()]));
$video = video_embed_url($post['video_url']);

public_start($post['title'], ['crumbs' => [[$types[$post['post_type']], url($listPage)], [excerpt($post['title'], 50), null]], 'description' => $post['summary'] ?: excerpt($post['body'], 160),
    'image' => $post['cover_media_id'] ? media_url((int) $post['cover_media_id']) : null]);
?>
<div class="wrap section split">
    <article class="article">
        <?php if ($post['cover_media_id']): $cm = media_row((int) $post['cover_media_id']); ?><figure class="article-cover"><img src="<?= e(media_url((int) $post['cover_media_id'])) ?>" alt="<?= e($cm['alt_text'] ?? '') ?>"></figure><?php endif; ?>
        <p class="article-meta"><?= e(post_type_labels()[$post['post_type']]) ?> published <time datetime="<?= e(date('c', strtotime($date))) ?>"><?= e(fmt_date($date)) ?></time><?= $post['author_name'] ? ' by ' . e($post['author_name']) : '' ?><?= $post['category_name'] ? ', filed under ' . e($post['category_name']) : '' ?></p>
        <?php if ($post['summary']): ?><p class="lead"><?= e($post['summary']) ?></p><?php endif; ?>
        <?php if ($video): ?><div class="video-wrap"><iframe src="<?= e($video) ?>" title="Video: <?= e($post['title']) ?>" loading="lazy" allow="encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div><?php endif; ?>
        <div class="prose"><?= $post['body'] ?></div>
        <?php if ($attach): ?><p><a class="btn btn-primary" href="<?= e(media_url((int) $attach['id'])) ?>"><?= icon('download') ?> Download <?= e(strtoupper($attach['extension'])) ?> (<?= e(human_size((int) $attach['file_size'])) ?>)</a></p><?php endif; ?>
    </article>
    <aside>
        <?php if ($event): ?><div class="box aside-box"><h2 class="h3">Related event</h2><ul class="event-list"><?= event_item($event) ?></ul></div><?php endif; ?>
        <div class="box aside-box"><h2 class="h3">More <?= e(strtolower($types[$post['post_type']])) ?></h2>
            <?php if ($related): ?><ul class="foot-list related-list"><?php foreach ($related as $r): ?><li><a href="<?= e(url('post.php', ['slug' => $r['slug']])) ?>"><?= e($r['title']) ?></a><br><span class="muted small"><?= e(fmt_date($r['publish_at'] ?: $r['published_at'])) ?></span></li><?php endforeach; ?></ul>
            <?php else: ?><p class="muted">No other items yet.</p><?php endif; ?>
            <p><a class="more" href="<?= e(url($listPage)) ?>">See all<?= icon('arrow') ?></a></p>
        </div>
    </aside>
</div>
<?php public_end();
