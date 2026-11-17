<?php
// Organizational activities and field reports
$typeOpts = ['meeting' => 'Meeting', 'training' => 'Training', 'outreach' => 'Outreach', 'assembly' => 'Assembly', 'consultation' => 'Consultation', 'coordination' => 'Coordination', 'field_report' => 'Field report', 'other' => 'Other'];
$scopeCols = ['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id', 'coop' => 'x.cooperative_id'];
$own = (has_role('ambassador') || has_role('focal_person')) && !scope_is_national();
$ambSql = "SELECT a.id, CONCAT(pe.first_name, ' ', pe.last_name, ' - ', a.designation_title) FROM ambassadors_focal_persons a JOIN persons pe ON pe.id = a.person_id WHERE a.archived_at IS NULL AND a.designation_status = 'active'" . ($own ? ' AND a.user_id = ' . (int) uid() : '') . ' ORDER BY pe.last_name';
$ppParams = [];
$ppScope = scope_sql(['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'], $ppParams);
return [
    'key' => 'activities', 'perm' => 'activities', 'page' => 'admin/activities.php', 'table' => 'activities', 'alias' => 'x',
    'title' => 'Organizational activities', 'singular' => 'activity',
    'from' => 'activities x LEFT JOIN cooperatives c ON c.id = x.cooperative_id LEFT JOIN regions r ON r.id = x.region_id LEFT JOIN provinces p ON p.id = x.province_id LEFT JOIN cities_municipalities cm ON cm.id = x.city_id',
    'select' => 'x.*, c.name AS coop_name, r.short_name AS region_name, p.name AS province_name, cm.name AS city_name',
    'scope' => $scopeCols,
    'scope_of' => fn($d) => [$d['region_id'] ?? null, $d['province_id'] ?? null, $d['city_id'] ?? null, $d['cooperative_id'] ?? null],
    'review' => true, 'attachments' => true, 'import' => true,
    'search' => ['x.title', 'x.venue', 'c.name'],
    'sorts' => ['date' => 'x.activity_date', 'title' => 'x.title', 'region' => 'r.sort_order'],
    'default_sort' => 'date', 'default_dir' => 'desc',
    'filters' => [
        'type' => ['label' => 'Type', 'col' => 'x.activity_type', 'options' => $typeOpts],
        'region' => ['label' => 'Region', 'type' => 'region', 'col' => 'x.region_id'],
        'from' => ['label' => 'From', 'type' => 'date_from', 'col' => 'x.activity_date'],
        'to' => ['label' => 'To', 'type' => 'date_to', 'col' => 'x.activity_date'],
    ],
    'columns' => [
        ['label' => 'Activity', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . '<span class="sub">' . e($typeOpts[$r['activity_type']] ?? '') . '</span>'],
        ['label' => 'Date', 'sort' => 'date', 'value' => fn($r) => e(fmt_date($r['activity_date']))],
        ['label' => 'Location', 'sort' => 'region', 'value' => fn($r) => e($r['coop_name'] ?: geo_label($r))],
        ['label' => 'Participants', 'value' => fn($r) => e(fmt_num($r['participants_count']))],
        ['label' => 'Review', 'value' => fn($r) => status_text($r['review_status'], OPT_REVIEW[$r['review_status']])],
    ],
    'fields' => [
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'activity_type' => ['label' => 'Type', 'type' => 'select', 'options' => $typeOpts, 'required' => true, 'default' => 'meeting'],
        'activity_date' => ['label' => 'Date', 'type' => 'date', 'required' => true],
        'end_date' => ['label' => 'End date', 'type' => 'date'],
        'cooperative_id' => ['label' => 'Cooperative', 'type' => 'coop'],
        'ambassador_id' => ['label' => 'Reported by ambassador or focal person', 'type' => 'lookup_sql', 'options_sql' => $ambSql, 'import' => false],
        'point_person_id' => ['label' => 'Point-person assignment', 'type' => 'lookup_sql', 'import' => false,
            'options_sql' => "SELECT pp.id, CONCAT(pe.first_name, ' ', pe.last_name, ' - ', c.name) FROM cnpl_point_persons pp JOIN persons pe ON pe.id = pp.person_id JOIN cooperatives c ON c.id = pp.cooperative_id WHERE pp.archived_at IS NULL AND {$ppScope} ORDER BY pe.last_name",
            'options_params' => $ppParams],
    ] + geo_fields('g', false) + [
        'venue' => ['label' => 'Venue', 'max' => 191],
        'participants_count' => ['label' => 'Number of participants', 'type' => 'number', 'min' => 0],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'max' => 5000],
        'outcome' => ['label' => 'Outcomes and next steps', 'type' => 'textarea', 'max' => 5000],
    ],
    'sections' => [
        'Activity' => ['title', 'activity_type', 'activity_date', 'end_date', 'venue', 'participants_count'],
        'Related to' => ['cooperative_id', 'ambassador_id', 'point_person_id', 'region_id', 'province_id', 'city_id'],
        'Report' => ['description', 'outcome'],
    ],
    'prefill' => ['cooperative_id', 'ambassador_id', 'point_person_id'],
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if (!empty($data['cooperative_id'])) {
            $g = coop_geo((int) $data['cooperative_id']);
            if ($g && empty($data['region_id'])) {
                $data['region_id'] = (int) $g['region_id'];
                $data['province_id'] = $g['province_id'] ? (int) $g['province_id'] : null;
                $data['city_id'] = $g['city_id'] ? (int) $g['city_id'] : null;
            }
        }
        if (empty($data['region_id']) && empty($data['cooperative_id'])) {
            $errors['region_id'] = 'Select a cooperative or at least a region.';
        }
        if (!empty($data['end_date']) && !empty($data['activity_date']) && $data['end_date'] < $data['activity_date']) {
            $errors['end_date'] = 'The end date cannot be earlier than the activity date.';
        }
    },
    'can_edit_row' => fn($r) => !((has_role('ambassador') || has_role('focal_person')) && !scope_is_national() && !can('activities.approve')) || (int) $r['created_by'] === uid(),
];
