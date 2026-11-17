<?php
// CNPL ambassadors and focal persons. A designation does not grant access to member data.
$type = in_array($ARGS['type'] ?? '', ['ambassador', 'focal_person'], true) ? $ARGS['type'] : null;
$statusOpts = ['active' => 'Active', 'inactive' => 'Inactive', 'ended' => 'Ended', 'suspended' => 'Suspended'];
$typeOpts = ['ambassador' => 'Ambassador', 'focal_person' => 'Focal person'];
$ownOnly = (has_role('ambassador') || has_role('focal_person')) && !scope_is_national() && !can('ambassadors.edit');
return [
    'key' => 'ambassadors', 'perm' => 'ambassadors', 'page' => 'admin/ambassadors.php', 'table' => 'ambassadors_focal_persons', 'alias' => 'x',
    'title' => 'Ambassadors and focal persons', 'singular' => 'designation',
    'from' => 'ambassadors_focal_persons x JOIN persons pe ON pe.id = x.person_id LEFT JOIN regions r ON r.id = x.region_id LEFT JOIN provinces p ON p.id = x.province_id
               LEFT JOIN cities_municipalities cm ON cm.id = x.city_id LEFT JOIN cooperatives c ON c.id = x.cooperative_id',
    'select' => 'x.*, pe.first_name, pe.middle_name, pe.last_name, pe.suffix, pe.email_enc, pe.phone_enc, r.short_name AS region_name, p.name AS province_name, cm.name AS city_name, c.name AS coop_name',
    'scope' => ['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id', 'coop' => 'x.cooperative_id'],
    'scope_of' => fn($d) => [$d['region_id'] ?? null, $d['province_id'] ?? null, $d['city_id'] ?? null, $d['cooperative_id'] ?? null],
    'base_where' => $ownOnly ? ['x.user_id = ?', [(int) uid()]] : null,
    'review' => true, 'attachments' => true, 'import' => true,
    'search' => ['pe.first_name', 'pe.last_name', 'x.designation_title', 'x.affiliation'],
    'sorts' => ['name' => 'pe.last_name', 'region' => 'r.sort_order', 'date' => 'x.appointment_date'],
    'default_sort' => 'name', 'default_dir' => 'asc',
    'filters' => [
        'type' => ['label' => 'Designation', 'col' => 'x.designation_type', 'options' => $typeOpts],
        'region' => ['label' => 'Region', 'type' => 'region', 'col' => 'x.region_id'],
        'status' => ['label' => 'Status', 'col' => 'x.designation_status', 'options' => $statusOpts],
    ],
    'columns' => [
        ['label' => 'Name', 'sort' => 'name', 'value' => fn($r) => e(full_name($r)) . '<span class="sub">' . e($r['designation_title']) . '</span>'],
        ['label' => 'Designation', 'value' => fn($r) => e($typeOpts[$r['designation_type']] ?? '')],
        ['label' => 'Assigned area', 'sort' => 'region', 'value' => fn($r) => e(geo_label($r) ?: 'Not set')],
        ['label' => 'Affiliation', 'value' => fn($r) => e($r['coop_name'] ?: $r['affiliation'])],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['designation_status']) . ($r['review_status'] !== 'approved' ? '<span class="sub">' . status_text($r['review_status'], OPT_REVIEW[$r['review_status']]) . '</span>' : '')],
    ],
    'fields' => [
        'designation_type' => ['label' => 'Designation', 'type' => 'select', 'options' => $typeOpts, 'required' => true, 'default' => $type ?? 'ambassador'],
        'designation_title' => ['label' => 'Official designation title', 'required' => true, 'max' => 120],
        'person_id' => ['label' => 'Person', 'type' => 'person', 'required' => true],
        'cooperative_id' => ['label' => 'Affiliated cooperative', 'type' => 'coop'],
        'affiliation' => ['label' => 'Other organizational affiliation', 'max' => 191],
        'user_id' => ['label' => 'Linked login account', 'type' => 'user', 'import' => false, 'help' => 'Lets this person sign in. Access is limited to the assigned area and their own activities.'],
    ] + geo_fields('g', true, '', '') + [
        'scope_notes' => ['label' => 'Scope notes', 'max' => 255, 'width' => 'full'],
        'appointment_date' => ['label' => 'Appointment date', 'type' => 'date'],
        'end_date' => ['label' => 'End date', 'type' => 'date'],
        'appointment_reference' => ['label' => 'Appointment reference', 'max' => 100],
        'designation_status' => ['label' => 'Status', 'type' => 'select', 'options' => $statusOpts, 'required' => true, 'default' => 'active'],
        'responsibilities' => ['label' => 'Administrative responsibilities', 'type' => 'textarea', 'max' => 3000],
        'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 2000],
    ],
    'sections' => [
        'Designation' => ['designation_type', 'designation_title', 'person_id', 'cooperative_id', 'affiliation', 'user_id'],
        'Assigned geographical scope' => ['region_id', 'province_id', 'city_id', 'scope_notes'],
        'Appointment' => ['appointment_date', 'end_date', 'appointment_reference', 'designation_status', 'responsibilities', 'notes'],
    ],
    'title_of' => fn($r) => full_name($r) . ', ' . $r['designation_title'],
    'view_panels' => function (array $row) {
        $acts = q_all('SELECT * FROM activities WHERE ambassador_id = ? AND archived_at IS NULL ORDER BY activity_date DESC LIMIT 20', [$row['id']]);
        related_table('Activities and reports', ['Date', 'Activity', 'Type', 'Participants'], array_map(fn($a) => [e(fmt_date($a['activity_date'])),
            '<a href="' . e(url('admin/activities.php', ['action' => 'view', 'id' => $a['id']])) . '">' . e($a['title']) . '</a>', e(label_from_key($a['activity_type'])), e(fmt_num($a['participants_count']))], $acts),
            'No activities reported yet.', can('activities.create') ? url('admin/activities.php', ['action' => 'create', 'ambassador_id' => $row['id']]) : '', 'Report activity');
    },
];
