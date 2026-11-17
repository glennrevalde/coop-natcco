<?php
// COOP NATCCO Partylist officers. Appointment history is kept in officer_appointments.
return [
    'key' => 'officers', 'perm' => 'officers', 'page' => 'admin/officers.php', 'table' => 'officers', 'alias' => 'x',
    'title' => 'CNPL officers', 'singular' => 'officer',
    'from' => "officers x JOIN persons pe ON pe.id = x.person_id LEFT JOIN regions r ON r.id = x.region_id
               LEFT JOIN (SELECT oa.officer_id, GROUP_CONCAT(ps.title ORDER BY ps.rank_order SEPARATOR ', ') AS current_positions FROM officer_appointments oa JOIN positions ps ON ps.id = oa.position_id
                          WHERE oa.appointment_status = 'current' AND oa.archived_at IS NULL GROUP BY oa.officer_id) cur ON cur.officer_id = x.id",
    'select' => 'x.*, pe.first_name, pe.middle_name, pe.last_name, pe.suffix, r.short_name AS region_name, cur.current_positions',
    'scope' => ['region' => 'x.region_id'],
    'scope_of' => fn($d) => [$d['region_id'] ?? null, null, null, null],
    'review' => true, 'attachments' => true, 'import' => true,
    'search' => ['pe.first_name', 'pe.last_name', 'x.officer_code', 'x.office_assignment', 'x.public_title'],
    'sorts' => ['name' => 'pe.last_name', 'code' => 'x.officer_code', 'region' => 'r.sort_order'],
    'default_sort' => 'name', 'default_dir' => 'asc',
    'filters' => [
        'region' => ['label' => 'Regional assignment', 'type' => 'region', 'col' => 'x.region_id'],
        'status' => ['label' => 'Status', 'col' => 'x.officer_status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
        'current' => ['label' => 'Appointment', 'options' => ['current' => 'Has a current appointment', 'none' => 'No current appointment'],
            'where' => fn($v) => [$v === 'current' ? 'cur.current_positions IS NOT NULL' : 'cur.current_positions IS NULL', []]],
    ],
    'columns' => [
        ['label' => 'Officer', 'sort' => 'name', 'value' => fn($r) => e(full_name($r)) . '<span class="sub">' . e($r['officer_code']) . '</span>'],
        ['label' => 'Current positions', 'value' => fn($r) => $r['current_positions'] ? e($r['current_positions']) : '<span class="muted">None</span>'],
        ['label' => 'Office or region', 'sort' => 'region', 'value' => fn($r) => e(trim(($r['office_assignment'] ?? '') . ($r['region_name'] ? ' (' . $r['region_name'] . ')' : '')))],
        ['label' => 'Public profile', 'value' => fn($r) => $r['is_public_profile'] ? 'Shown' : '<span class="muted">Hidden</span>'],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['officer_status']) . ($r['review_status'] !== 'approved' ? '<span class="sub">' . status_text($r['review_status'], OPT_REVIEW[$r['review_status']]) . '</span>' : '')],
    ],
    'fields' => [
        'person_id' => ['label' => 'Person', 'type' => 'person', 'required' => true, 'create_only' => true],
        'public_title' => ['label' => 'Display title', 'max' => 150, 'help' => 'Shown on the public leadership page, for example "Chairperson".'],
        'office_assignment' => ['label' => 'Office assignment', 'max' => 150],
        'region_id' => ['label' => 'Regional assignment', 'type' => 'region', 'help' => 'Leave blank for national officers.'],
        'responsibilities' => ['label' => 'Organizational responsibilities', 'type' => 'textarea', 'max' => 3000],
        'contact_email' => ['label' => 'Official contact email', 'type' => 'email'],
        'contact_phone' => ['label' => 'Official contact phone', 'type' => 'tel', 'max' => 40],
        'is_public_profile' => ['label' => 'Show on the public website', 'type' => 'bool', 'help' => 'Only approved public information (name, title, photo and biography) is shown.'],
        'photo_media_id' => ['label' => 'Official photo', 'type' => 'media', 'kind' => 'image', 'visibility' => 'public'],
        'bio' => ['label' => 'Public biography', 'type' => 'richtext', 'help' => 'Use verified, approved information only.'],
        'officer_status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'required' => true, 'default' => 'active'],
        'admin_notes' => ['label' => 'Administrative notes', 'type' => 'textarea', 'max' => 3000],
        'officer_code' => ['label' => 'Officer code', 'computed' => true],
    ],
    'sections' => [
        'Officer' => ['person_id', 'public_title', 'office_assignment', 'region_id', 'officer_status'],
        'Responsibilities and contact' => ['responsibilities', 'contact_email', 'contact_phone', 'admin_notes'],
        'Public profile' => ['is_public_profile', 'photo_media_id', 'bio'],
    ],
    'view_sections' => [
        'Officer' => ['officer_code', 'person_id', 'public_title', 'office_assignment', 'region_id', 'officer_status'],
        'Responsibilities and contact' => ['responsibilities', 'contact_email', 'contact_phone', 'admin_notes'],
        'Public profile' => ['is_public_profile', 'photo_media_id', 'bio'],
    ],
    'title_of' => fn($r) => full_name($r),
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if (!$old && !empty($data['person_id']) && q_val('SELECT id FROM officers WHERE person_id = ?', [(int) $data['person_id']])) {
            $errors['person_id'] = 'This person already has an officer record. Add a new appointment to the existing record instead.';
        }
    },
    'before_save' => function (array &$data, ?array $old) {
        if (!$old) {
            $data['officer_code'] = 'TMP-' . bin2hex(random_bytes(6));
        }
    },
    'after_save' => function (int $id, array $data, ?array $old) {
        if (!$old) {
            q('UPDATE officers SET officer_code = ? WHERE id = ?', ['CNPL-OFF-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT), $id]);
        }
    },
    'view_panels' => function (array $row) {
        $apps = q_all('SELECT oa.*, ps.title AS position_title, ou.name AS unit_name, r.short_name AS region_name FROM officer_appointments oa JOIN positions ps ON ps.id = oa.position_id
                       LEFT JOIN organizational_units ou ON ou.id = oa.org_unit_id LEFT JOIN regions r ON r.id = oa.region_id WHERE oa.officer_id = ? AND oa.archived_at IS NULL ORDER BY oa.start_date DESC', [$row['id']]);
        related_table('Appointments (current and historical)', ['Position', 'Unit', 'Period', 'Status', 'Reference'], array_map(fn($a) => [
            '<a href="' . e(url('admin/appointments.php', ['action' => 'view', 'id' => $a['id']])) . '">' . e($a['position_title']) . '</a>', e(trim(($a['unit_name'] ?? '') . ($a['region_name'] ? ' (' . $a['region_name'] . ')' : ''))),
            e(fmt_date($a['start_date'])) . ' to ' . ($a['end_date'] ? e(fmt_date($a['end_date'])) : 'present'), status_text($a['appointment_status']), e($a['appointment_reference'])], $apps),
            'No appointments recorded.', can('officers.create') ? url('admin/appointments.php', ['action' => 'create', 'officer_id' => $row['id']]) : '', 'Add appointment');
        $awards = q_all("SELECT * FROM awards WHERE officer_id = ? AND archived_at IS NULL ORDER BY award_date DESC", [$row['id']]);
        if ($awards) {
            related_table('Awards and recognition', ['Award', 'Institution', 'Date', 'Status'], array_map(fn($a) => [e($a['title']), e($a['awarding_institution']), e(fmt_date($a['award_date'])), status_text($a['status'])], $awards), '');
        }
    },
];
