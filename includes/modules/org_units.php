<?php
$typeOpts = ['national' => 'National leadership', 'department' => 'Department', 'committee' => 'Committee', 'office' => 'Office', 'regional' => 'Regional structure', 'provincial' => 'Provincial structure', 'local' => 'Local structure'];
return [
    'key' => 'org_units', 'perm' => 'structure', 'page' => 'admin/org-units.php', 'table' => 'organizational_units', 'alias' => 'x',
    'perm_map' => ['view' => 'structure.manage', 'create' => 'structure.manage', 'edit' => 'structure.manage', 'archive' => 'structure.manage'],
    'title' => 'Organizational structure', 'singular' => 'organizational unit', 'export' => false,
    'list_intro' => 'Units shown here build the public organizational chart. Officers appear under a unit through their current appointments.',
    'from' => 'organizational_units x LEFT JOIN organizational_units pu ON pu.id = x.parent_id LEFT JOIN regions r ON r.id = x.region_id',
    'select' => "x.*, pu.name AS parent_name, r.short_name AS region_name, (SELECT COUNT(*) FROM officer_appointments oa WHERE oa.org_unit_id = x.id AND oa.appointment_status = 'current' AND oa.archived_at IS NULL) AS current_count",
    'search' => ['x.name'],
    'sorts' => ['order' => 'x.sort_order', 'name' => 'x.name'], 'default_sort' => 'order', 'default_dir' => 'asc',
    'filters' => ['type' => ['label' => 'Type', 'col' => 'x.unit_type', 'options' => $typeOpts]],
    'columns' => [
        ['label' => 'Unit', 'sort' => 'name', 'value' => fn($r) => e($r['name']) . '<span class="sub">' . e($typeOpts[$r['unit_type']]) . ($r['parent_name'] ? ' under ' . e($r['parent_name']) : '') . '</span>'],
        ['label' => 'Region', 'value' => fn($r) => e($r['region_name'])],
        ['label' => 'Current officers', 'class' => 'num', 'value' => fn($r) => fmt_num($r['current_count'])],
        ['label' => 'Public', 'value' => fn($r) => $r['is_public'] ? 'Shown' : '<span class="muted">Hidden</span>'],
        ['label' => 'Order', 'sort' => 'order', 'class' => 'num', 'value' => fn($r) => (int) $r['sort_order']],
    ],
    'fields' => [
        'name' => ['label' => 'Name', 'required' => true, 'max' => 150],
        'unit_type' => ['label' => 'Type', 'type' => 'select', 'options' => $typeOpts, 'required' => true],
        'parent_id' => ['label' => 'Reports to', 'type' => 'lookup_sql', 'options_sql' => 'SELECT id, name FROM organizational_units WHERE archived_at IS NULL ORDER BY sort_order, name'],
        'region_id' => ['label' => 'Region', 'type' => 'region', 'geo_group' => 'g', 'width' => 'third'],
        'province_id' => ['label' => 'Province', 'type' => 'province', 'geo_group' => 'g', 'width' => 'third'],
        'sort_order' => ['label' => 'Display order', 'type' => 'number', 'default' => 0, 'width' => 'third'],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'max' => 2000],
        'is_public' => ['label' => 'Show on the public structure page', 'type' => 'bool', 'default' => 1],
        'unit_status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'required' => true, 'default' => 'active'],
    ],
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if ($old && !empty($data['parent_id'])) {
            $p = (int) $data['parent_id'];
            $guard = 0;
            while ($p && $guard++ < 50) {
                if ($p === (int) $old['id']) {
                    $errors['parent_id'] = 'A unit cannot report to itself or to one of its own sub-units.';
                    break;
                }
                $p = (int) q_val('SELECT parent_id FROM organizational_units WHERE id = ?', [$p]);
            }
        }
    },
];
