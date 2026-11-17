<?php
// Cooperative officers and staff, linked to people and cooperatives
$coopFilter = ['label' => 'Cooperative', 'type' => 'hidden', 'col' => 'x.cooperative_id', 'describe' => fn($v) => lookup_label('coop', $v)];
return [
    'key' => 'personnel', 'perm' => 'personnel', 'page' => 'admin/personnel.php', 'table' => 'cooperative_personnel', 'alias' => 'x',
    'title' => 'Cooperative officers and staff', 'singular' => 'officer or staff record',
    'from' => 'cooperative_personnel x JOIN cooperatives c ON c.id = x.cooperative_id JOIN persons pe ON pe.id = x.person_id
               LEFT JOIN regions r ON r.id = c.region_id LEFT JOIN cities_municipalities cm ON cm.id = c.city_id',
    'select' => 'x.*, c.name AS coop_name, c.reference_no AS coop_ref, pe.first_name, pe.middle_name, pe.last_name, pe.suffix, r.short_name AS region_name, cm.name AS city_name',
    'scope' => ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'],
    'scope_of' => fn($d) => [null, null, null, $d['cooperative_id'] ?? null],
    'review' => true, 'attachments' => true, 'import' => true,
    'search' => ['pe.first_name', 'pe.last_name', 'x.position_title', 'x.department', 'c.name'],
    'sorts' => ['name' => 'pe.last_name', 'coop' => 'c.name', 'position' => 'x.position_title', 'start' => 'x.start_date'],
    'default_sort' => 'name', 'default_dir' => 'asc',
    'filters' => [
        'coop' => $coopFilter,
        'region' => ['label' => 'Region', 'type' => 'region', 'col' => 'c.region_id'],
        'type' => ['label' => 'Type', 'col' => 'x.personnel_type', 'options' => ['officer' => 'Officer', 'staff' => 'Staff']],
        'status' => ['label' => 'Status', 'col' => 'x.employment_status', 'options' => ['active' => 'Active', 'on_leave' => 'On leave', 'ended' => 'Ended', 'resigned' => 'Resigned', 'terminated' => 'Terminated']],
    ],
    'columns' => [
        ['label' => 'Name', 'sort' => 'name', 'value' => fn($r) => e(full_name($r))],
        ['label' => 'Position', 'sort' => 'position', 'value' => fn($r) => e($r['position_title']) . ($r['department'] ? '<span class="sub">' . e($r['department']) . '</span>' : '')],
        ['label' => 'Cooperative', 'sort' => 'coop', 'value' => fn($r) => e($r['coop_name']) . '<span class="sub">' . e(trim(($r['city_name'] ?? '') . ', ' . ($r['region_name'] ?? ''), ', ')) . '</span>'],
        ['label' => 'Type', 'value' => fn($r) => e(ucfirst($r['personnel_type']))],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['employment_status']) . ($r['review_status'] !== 'approved' ? '<span class="sub">' . status_text($r['review_status'], OPT_REVIEW[$r['review_status']]) . '</span>' : '')],
    ],
    'fields' => [
        'cooperative_id' => ['label' => 'Cooperative', 'type' => 'coop', 'required' => true],
        'person_id' => ['label' => 'Person', 'type' => 'person', 'required' => true, 'width' => 'full'],
        'personnel_type' => ['label' => 'Type', 'type' => 'select', 'options' => ['officer' => 'Officer', 'staff' => 'Staff'], 'required' => true, 'default' => 'officer'],
        'position_title' => ['label' => 'Position', 'required' => true, 'max' => 120],
        'department' => ['label' => 'Department or committee', 'max' => 120],
        'employment_status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'on_leave' => 'On leave', 'ended' => 'Ended', 'resigned' => 'Resigned', 'terminated' => 'Terminated'], 'required' => true, 'default' => 'active'],
        'start_date' => ['label' => 'Effective from', 'type' => 'date'],
        'end_date' => ['label' => 'Effective until', 'type' => 'date', 'help' => 'Set when the position ends; the record stays in the history.'],
        'system_role_note' => ['label' => 'Authorized administrative role', 'max' => 150, 'help' => 'For example: authorized signatory, data encoder for the cooperative.'],
        'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 2000],
    ],
    'sections' => [
        'Assignment' => ['cooperative_id', 'person_id'],
        'Position' => ['personnel_type', 'position_title', 'department', 'employment_status', 'start_date', 'end_date', 'system_role_note', 'notes'],
    ],
    'prefill' => ['cooperative_id'],
    'title_of' => fn($r) => full_name($r) . ', ' . $r['position_title'],
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if (!empty($data['start_date']) && !empty($data['end_date']) && $data['end_date'] < $data['start_date']) {
            $errors['end_date'] = 'The end date cannot be earlier than the start date.';
        }
    },
    'import_key' => fn($d) => ($d['cooperative_id'] ?? '') . '|' . mb_strtolower($d['position_title'] ?? '') . '|' . name_key($d['__new_person']['np_first_name'] ?? '', $d['__new_person']['np_last_name'] ?? ''),
    'view_panels' => function (array $row) {
        $hist = q_all('SELECT x.*, c.name AS coop_name FROM cooperative_personnel x JOIN cooperatives c ON c.id = x.cooperative_id WHERE x.person_id = ? AND x.id <> ? ORDER BY x.start_date DESC', [$row['person_id'], $row['id']]);
        $rows = [];
        foreach ($hist as $h) {
            if (!scope_allows_coop((int) $h['cooperative_id'])) {
                continue;
            }
            $rows[] = ['<a href="' . e(url('admin/personnel.php', ['action' => 'view', 'id' => $h['id']])) . '">' . e($h['position_title']) . '</a>', e($h['coop_name']), status_text($h['employment_status']),
                e(fmt_date($h['start_date'])) . ($h['end_date'] ? ' to ' . e(fmt_date($h['end_date'])) : '')];
        }
        related_table('Other positions held by this person', ['Position', 'Cooperative', 'Status', 'Period'], $rows, 'No other positions on record.');
    },
    'export_columns' => [
        'Name' => fn($r) => full_name($r), 'Position' => fn($r) => $r['position_title'], 'Department' => fn($r) => $r['department'], 'Type' => fn($r) => $r['personnel_type'],
        'Cooperative' => fn($r) => $r['coop_name'], 'Cooperative ref.' => fn($r) => $r['coop_ref'], 'Region' => fn($r) => $r['region_name'], 'Status' => fn($r) => $r['employment_status'],
        'Effective from' => fn($r) => $r['start_date'], 'Effective until' => fn($r) => $r['end_date'],
    ],
];
