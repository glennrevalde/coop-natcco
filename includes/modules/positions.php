<?php
return [
    'key' => 'positions', 'perm' => 'structure', 'page' => 'admin/positions.php', 'table' => 'positions', 'alias' => 'x',
    'perm_map' => ['view' => 'structure.manage', 'create' => 'structure.manage', 'edit' => 'structure.manage', 'archive' => 'structure.manage'],
    'title' => 'Official positions', 'singular' => 'position', 'export' => false,
    'from' => 'positions x LEFT JOIN organizational_units ou ON ou.id = x.org_unit_id',
    'select' => "x.*, ou.name AS unit_name, (SELECT COUNT(*) FROM officer_appointments oa WHERE oa.position_id = x.id AND oa.appointment_status = 'current' AND oa.archived_at IS NULL) AS holders",
    'search' => ['x.title'],
    'sorts' => ['rank' => 'x.rank_order', 'title' => 'x.title'], 'default_sort' => 'rank', 'default_dir' => 'asc',
    'columns' => [
        ['label' => 'Position', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . ($r['unit_name'] ? '<span class="sub">' . e($r['unit_name']) . '</span>' : '')],
        ['label' => 'Leadership', 'value' => fn($r) => $r['is_leadership'] ? 'Yes' : 'No'],
        ['label' => 'Current holders', 'class' => 'num', 'value' => fn($r) => fmt_num($r['holders'])],
        ['label' => 'Rank', 'sort' => 'rank', 'class' => 'num', 'value' => fn($r) => (int) $r['rank_order']],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['position_status'])],
    ],
    'fields' => [
        'title' => ['label' => 'Position title', 'required' => true, 'max' => 150],
        'org_unit_id' => ['label' => 'Usual unit', 'type' => 'lookup_sql', 'options_sql' => 'SELECT id, name FROM organizational_units WHERE archived_at IS NULL ORDER BY sort_order, name'],
        'rank_order' => ['label' => 'Rank (lower appears first)', 'type' => 'number', 'default' => 100, 'required' => true],
        'is_leadership' => ['label' => 'National leadership position (shown on the Leadership page)', 'type' => 'bool'],
        'description' => ['label' => 'Description', 'max' => 255, 'width' => 'full'],
        'position_status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'required' => true, 'default' => 'active'],
    ],
];
