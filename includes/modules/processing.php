<?php
// Records of processing activities and privacy impact assessment tracking
$pia = ['not_started' => 'Not started', 'in_progress' => 'In progress', 'completed' => 'Completed', 'not_required' => 'Not required'];
return [
    'key' => 'processing', 'perm' => 'privacy', 'page' => 'admin/processing.php', 'table' => 'processing_activities', 'alias' => 'x',
    'perm_map' => ['view' => 'privacy.view', 'create' => 'privacy.manage', 'edit' => 'privacy.manage', 'archive' => 'privacy.manage'],
    'title' => 'Records of processing activities', 'singular' => 'processing activity', 'export' => false,
    'search' => ['x.name', 'x.purpose'],
    'sorts' => ['name' => 'x.name', 'reviewed' => 'x.last_reviewed_at'], 'default_sort' => 'name', 'default_dir' => 'asc',
    'filters' => ['pia' => ['label' => 'Impact assessment', 'col' => 'x.pia_status', 'options' => $pia]],
    'columns' => [
        ['label' => 'Processing activity', 'sort' => 'name', 'value' => fn($r) => e($r['name']) . '<span class="sub">' . e(excerpt($r['purpose'], 80)) . '</span>'],
        ['label' => 'Lawful basis', 'value' => fn($r) => e($r['lawful_basis'])],
        ['label' => 'Impact assessment', 'value' => fn($r) => status_text(['completed' => 'completed', 'in_progress' => 'in_progress', 'not_required' => 'inactive'][$r['pia_status']] ?? 'pending', $pia[$r['pia_status']])],
        ['label' => 'Last reviewed', 'sort' => 'reviewed', 'value' => fn($r) => e(fmt_date($r['last_reviewed_at']))],
    ],
    'fields' => [
        'name' => ['label' => 'Processing activity', 'required' => true, 'max' => 191, 'width' => 'full'],
        'purpose' => ['label' => 'Purpose', 'type' => 'textarea', 'required' => true, 'max' => 3000],
        'lawful_basis' => ['label' => 'Lawful basis', 'required' => true, 'max' => 191],
        'data_subjects' => ['label' => 'Data subjects', 'required' => true, 'max' => 255],
        'data_categories' => ['label' => 'Personal data categories', 'type' => 'textarea', 'required' => true, 'max' => 3000],
        'includes_sensitive_pi' => ['label' => 'Includes sensitive personal information', 'type' => 'bool', 'help' => 'Must be reviewed for lawful processing before it is enabled.'],
        'recipients' => ['label' => 'Recipients', 'max' => 255],
        'retention_period' => ['label' => 'Retention period', 'required' => true, 'max' => 191],
        'security_measures' => ['label' => 'Security measures', 'type' => 'textarea', 'max' => 3000],
        'pia_status' => ['label' => 'Privacy impact assessment', 'type' => 'select', 'options' => $pia, 'required' => true, 'default' => 'not_started'],
        'pia_notes' => ['label' => 'Assessment notes', 'type' => 'textarea', 'max' => 5000],
        'data_owner' => ['label' => 'Process owner', 'max' => 150],
        'last_reviewed_at' => ['label' => 'Last reviewed', 'type' => 'date'],
        'activity_status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'suspended' => 'Suspended', 'retired' => 'Retired'], 'required' => true, 'default' => 'active'],
    ],
];
