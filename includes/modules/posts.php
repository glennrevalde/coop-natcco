<?php
// News, announcements, achievements, newsletters, programs, featured activities and publications
$types = ['news' => 'News article', 'announcement' => 'Announcement', 'achievement' => 'Achievement', 'newsletter' => 'Newsletter',
    'program' => 'Program', 'activity' => 'Featured activity', 'publication' => 'Publication'];
$plural = ['news' => 'News', 'announcement' => 'Announcements', 'achievement' => 'Achievements', 'newsletter' => 'Newsletters',
    'program' => 'Programs', 'activity' => 'Featured activities', 'publication' => 'Publications'];
$type = isset($types[$ARGS['type'] ?? '']) ? $ARGS['type'] : null;
return [
    'key' => 'posts', 'perm' => 'content', 'page' => 'admin/posts.php', 'table' => 'posts', 'alias' => 'x',
    'page_params' => $type ? ['type' => $type] : [],
    'title' => $type ? $plural[$type] : 'News and updates', 'singular' => $type ? strtolower($types[$type]) : 'post',
    'workflow' => true, 'wf_perm' => 'content', 'export' => false,
    'from' => 'posts x LEFT JOIN content_categories cc ON cc.id = x.category_id',
    'select' => 'x.*, cc.name AS category_name',
    'base_where' => $type ? ['x.post_type = ?', [$type]] : null,
    'search' => ['x.title', 'x.summary'],
    'sorts' => ['date' => 'COALESCE(x.publish_at, x.published_at, x.created_at)', 'title' => 'x.title', 'views' => 'x.view_count'],
    'default_sort' => 'date', 'default_dir' => 'desc',
    'filters' => array_filter([
        'type' => $type ? null : ['label' => 'Type', 'col' => 'x.post_type', 'options' => $types],
        'category' => ['label' => 'Category', 'col' => 'x.category_id', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'post' ORDER BY sort_order"],
    ]),
    'columns' => [
        ['label' => 'Title', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . '<span class="sub">' . e($types[$r['post_type']]) . ($r['category_name'] ? ', ' . e($r['category_name']) : '') . ($r['is_featured'] ? ', featured' : '') . '</span>'],
        ['label' => 'Date', 'sort' => 'date', 'value' => fn($r) => e(fmt_date($r['publish_at'] ?: ($r['published_at'] ?: $r['created_at'])))],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['status'], $r['status'] === 'published' && $r['publish_at'] && strtotime($r['publish_at']) > time() ? 'Scheduled' : null)],
        ['label' => 'Views', 'sort' => 'views', 'class' => 'num', 'value' => fn($r) => fmt_num($r['view_count'])],
    ],
    'fields' => [
        'post_type' => ['label' => 'Type', 'type' => 'select', 'options' => $types, 'required' => true, 'default' => $type ?? 'news'],
        'category_id' => ['label' => 'Category', 'type' => 'lookup_sql', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'post' AND is_active = 1 ORDER BY sort_order"],
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'slug' => ['label' => 'Web address (slug)', 'max' => 160, 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'pattern_message' => 'Use lowercase letters, numbers and hyphens only.', 'help' => 'Leave blank to create one from the title.'],
        'author_name' => ['label' => 'Author or office', 'max' => 120],
        'summary' => ['label' => 'Summary', 'type' => 'textarea', 'max' => 500, 'rows' => 3, 'help' => 'Shown in lists and search results.'],
        'body' => ['label' => 'Content', 'type' => 'richtext'],
        'cover_media_id' => ['label' => 'Cover image', 'type' => 'media', 'kind' => 'image', 'visibility' => 'public'],
        'attachment_media_id' => ['label' => 'Downloadable file', 'type' => 'media', 'kind' => 'document', 'visibility' => 'public', 'help' => 'For newsletters and publications (PDF, Word, Excel or PowerPoint).'],
        'video_url' => ['label' => 'Video link', 'type' => 'url', 'max' => 255, 'help' => 'YouTube or Vimeo link. Other sites are not embedded.'],
        'event_id' => ['label' => 'Related event', 'type' => 'lookup_sql', 'options_sql' => 'SELECT id, CONCAT(title, \' (\', DATE_FORMAT(starts_at, \'%b %e, %Y\'), \')\') FROM events WHERE archived_at IS NULL ORDER BY starts_at DESC'],
        'is_featured' => ['label' => 'Feature on the home page', 'type' => 'bool'],
        'publish_at' => ['label' => 'Publish on', 'type' => 'datetime', 'help' => 'Leave blank to publish immediately. A future date schedules the publication.'],
    ],
    'sections' => [
        'Content' => ['post_type', 'category_id', 'title', 'slug', 'author_name', 'summary', 'body'],
        'Media and attachments' => ['cover_media_id', 'attachment_media_id', 'video_url'],
        'Publishing' => ['event_id', 'is_featured', 'publish_at'],
    ],
    'prefill' => ['event_id'],
    'public_url' => fn($r) => url('post.php', ['slug' => $r['slug']]),
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if (!empty($data['video_url']) && !video_embed_url($data['video_url'])) {
            $errors['video_url'] = 'Use a YouTube or Vimeo link.';
        }
        unique_slug('posts', $data, $old, $errors);
    },
    'after_save' => function (int $id, array $data, ?array $old) {
        if (($data['status'] ?? '') === 'published' && ($old['status'] ?? '') !== 'published' && ($data['post_type'] ?? '') === 'announcement') {
            posts_notify_announcement($id);
        }
    },
    'on_publish' => function (array $row) {
        if ($row['post_type'] === 'announcement') {
            posts_notify_announcement((int) $row['id']);
        }
    },
];
