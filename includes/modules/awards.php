<?php
$recipOpts = ['officer' => 'COOP NATCCO officer', 'cooperative' => 'Primary cooperative', 'organization' => 'Recognized organization', 'individual' => 'Individual'];
return [
    'key' => 'awards', 'perm' => 'content', 'page' => 'admin/awards.php', 'table' => 'awards', 'alias' => 'x',
    'title' => 'Awards and recognition', 'singular' => 'award', 'workflow' => true, 'wf_perm' => 'content', 'export' => false,
    'from' => 'awards x LEFT JOIN content_categories cc ON cc.id = x.category_id',
    'select' => 'x.*, cc.name AS category_name',
    'search' => ['x.title', 'x.recipient_name', 'x.awarding_institution'],
    'sorts' => ['date' => 'x.award_date', 'title' => 'x.title'], 'default_sort' => 'date', 'default_dir' => 'desc',
    'filters' => [
        'recipient' => ['label' => 'Recipient type', 'col' => 'x.recipient_type', 'options' => $recipOpts],
        'category' => ['label' => 'Category', 'col' => 'x.category_id', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'award' ORDER BY sort_order"],
    ],
    'columns' => [
        ['label' => 'Award', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . '<span class="sub">' . e($r['awarding_institution']) . '</span>'],
        ['label' => 'Recipient', 'value' => fn($r) => e($r['recipient_name']) . '<span class="sub">' . e($recipOpts[$r['recipient_type']]) . '</span>'],
        ['label' => 'Date', 'sort' => 'date', 'value' => fn($r) => e(fmt_date($r['award_date']))],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['status'])],
    ],
    'fields' => [
        'title' => ['label' => 'Award title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'slug' => ['label' => 'Web address (slug)', 'max' => 160, 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'pattern_message' => 'Use lowercase letters, numbers and hyphens only.', 'help' => 'Leave blank to create one from the title.'],
        'category_id' => ['label' => 'Award category', 'type' => 'lookup_sql', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'award' AND is_active = 1 ORDER BY sort_order"],
        'awarding_institution' => ['label' => 'Awarding institution', 'required' => true, 'max' => 191],
        'award_date' => ['label' => 'Award date', 'type' => 'date'],
        'recipient_type' => ['label' => 'Recipient type', 'type' => 'select', 'options' => $recipOpts, 'required' => true],
        'recipient_name' => ['label' => 'Recipient name as it should appear', 'required' => true, 'max' => 191],
        'officer_id' => ['label' => 'Linked CNPL officer', 'type' => 'lookup_sql', 'options_sql' => "SELECT o.id, CONCAT(pe.last_name, ', ', pe.first_name) FROM officers o JOIN persons pe ON pe.id = o.person_id WHERE o.archived_at IS NULL ORDER BY pe.last_name"],
        'cooperative_id' => ['label' => 'Linked cooperative', 'type' => 'coop'],
        'description' => ['label' => 'Description', 'type' => 'richtext'],
        'cover_media_id' => ['label' => 'Photo', 'type' => 'media', 'kind' => 'image', 'visibility' => 'public'],
        'document_media_id' => ['label' => 'Supporting document', 'type' => 'media', 'kind' => 'document', 'visibility' => 'public'],
        'publish_at' => ['label' => 'Publish on', 'type' => 'datetime'],
    ],
    'public_url' => fn($r) => url('awards.php', ['slug' => $r['slug']]),
    'validate' => function (array &$data, ?array $old, array &$errors) {
        unique_slug('awards', $data, $old, $errors);
    },
];
