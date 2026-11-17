<?php
// History timeline. Every milestone must cite a verified, approved source.
return [
    'key' => 'history', 'perm' => 'content', 'page' => 'admin/history.php', 'table' => 'history_milestones', 'alias' => 'x',
    'title' => 'History milestones', 'singular' => 'milestone', 'workflow' => true, 'wf_perm' => 'content', 'export' => false,
    'list_intro' => 'Each milestone must cite a verified, approved source. Do not publish details that have not been confirmed.',
    'search' => ['x.title', 'x.date_label'],
    'sorts' => ['date' => 'COALESCE(x.milestone_date, x.created_at)', 'order' => 'x.sort_order'], 'default_sort' => 'date', 'default_dir' => 'asc',
    'columns' => [
        ['label' => 'Milestone', 'value' => fn($r) => e($r['title']) . '<span class="sub">' . e($r['date_label']) . '</span>'],
        ['label' => 'Source', 'value' => fn($r) => e(excerpt($r['source_reference'], 60))],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['status'])],
    ],
    'fields' => [
        'date_label' => ['label' => 'Date as displayed', 'required' => true, 'max' => 60, 'help' => 'Only as precise as the source, for example "2009" or "March 2013".'],
        'milestone_date' => ['label' => 'Date used for ordering', 'type' => 'date'],
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'max' => 5000],
        'media_id' => ['label' => 'Archival photograph or document image', 'type' => 'media', 'kind' => 'image', 'visibility' => 'public'],
        'source_reference' => ['label' => 'Verified source', 'required' => true, 'max' => 255, 'width' => 'full', 'help' => 'Document, record or official publication that confirms this milestone.'],
        'sort_order' => ['label' => 'Order (same date)', 'type' => 'number', 'default' => 0],
        'publish_at' => ['label' => 'Publish on', 'type' => 'datetime'],
    ],
    'public_url' => fn($r) => url('history.php'),
];
