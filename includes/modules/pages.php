<?php
// Editable website pages (About Us, History introduction, Privacy Notice, Terms, Accessibility and custom pages)
$publicMap = ['about-us' => 'about.php', 'history' => 'history.php', 'privacy-notice' => 'privacy.php', 'terms-of-use' => 'terms.php', 'accessibility-statement' => 'accessibility.php'];
return [
    'key' => 'pages', 'perm' => 'content', 'page' => 'admin/pages.php', 'table' => 'pages', 'alias' => 'x',
    'title' => 'Pages', 'singular' => 'page', 'workflow' => true, 'wf_perm' => 'content', 'export' => false,
    'search' => ['x.title', 'x.slug'],
    'sorts' => ['title' => 'x.title', 'updated' => 'COALESCE(x.updated_at, x.created_at)'], 'default_sort' => 'title', 'default_dir' => 'asc',
    'columns' => [
        ['label' => 'Page', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . '<span class="sub">/' . e($publicMap[$r['slug']] ?? 'page.php?slug=' . $r['slug']) . ($r['is_system'] ? ', built-in' : '') . '</span>'],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['status'])],
        ['label' => 'Last updated', 'sort' => 'updated', 'value' => fn($r) => e(fmt_date($r['updated_at'] ?: $r['created_at']))],
    ],
    'fields' => [
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'slug' => ['label' => 'Web address (slug)', 'max' => 120, 'create_only' => true, 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'pattern_message' => 'Use lowercase letters, numbers and hyphens only.', 'help' => 'Leave blank to create one from the title. Cannot be changed later.'],
        'summary' => ['label' => 'Summary', 'type' => 'textarea', 'max' => 500, 'rows' => 3],
        'body' => ['label' => 'Content', 'type' => 'richtext'],
        'show_in_footer' => ['label' => 'Link in the footer', 'type' => 'bool'],
        'publish_at' => ['label' => 'Publish on', 'type' => 'datetime'],
    ],
    'public_url' => fn($r) => url($publicMap[$r['slug']] ?? 'page.php', isset($publicMap[$r['slug']]) ? [] : ['slug' => $r['slug']]),
    'validate' => function (array &$data, ?array $old, array &$errors) {
        unique_slug('pages', $data, $old, $errors);
    },
    'can_archive_row' => fn($r) => (int) $r['is_system'] === 0,
];
