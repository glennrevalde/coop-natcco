<?php
return [
    'key' => 'faqs', 'perm' => 'content', 'page' => 'admin/faqs.php', 'table' => 'faqs', 'alias' => 'x',
    'title' => 'Frequently asked questions', 'singular' => 'question', 'workflow' => true, 'wf_perm' => 'content', 'export' => false,
    'from' => 'faqs x LEFT JOIN content_categories cc ON cc.id = x.category_id',
    'select' => 'x.*, cc.name AS category_name',
    'search' => ['x.question', 'x.answer'],
    'sorts' => ['order' => 'x.sort_order', 'question' => 'x.question'], 'default_sort' => 'order', 'default_dir' => 'asc',
    'filters' => ['category' => ['label' => 'Category', 'col' => 'x.category_id', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'faq' ORDER BY sort_order"]],
    'columns' => [
        ['label' => 'Question', 'sort' => 'question', 'value' => fn($r) => e($r['question']) . '<span class="sub">' . e($r['category_name'] ?: 'General') . '</span>'],
        ['label' => 'Order', 'sort' => 'order', 'class' => 'num', 'value' => fn($r) => (int) $r['sort_order']],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['status'])],
    ],
    'fields' => [
        'question' => ['label' => 'Question', 'required' => true, 'max' => 255, 'width' => 'full'],
        'category_id' => ['label' => 'Category', 'type' => 'lookup_sql', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'faq' AND is_active = 1 ORDER BY sort_order"],
        'sort_order' => ['label' => 'Display order', 'type' => 'number', 'default' => 0],
        'answer' => ['label' => 'Answer', 'type' => 'textarea', 'required' => true, 'max' => 5000, 'rows' => 6],
        'publish_at' => ['label' => 'Publish on', 'type' => 'datetime'],
    ],
    'title_of' => fn($r) => $r['question'],
    'public_url' => fn($r) => url('faq.php'),
];
