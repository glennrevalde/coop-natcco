<?php
// Officer appointments with effective dates; ending an appointment preserves it as history.
$statusOpts = ['current' => 'Current', 'ended' => 'Ended', 'suspended' => 'Suspended'];
return [
    'key' => 'appointments', 'perm' => 'officers', 'page' => 'admin/appointments.php', 'table' => 'officer_appointments', 'alias' => 'x',
    'title' => 'Officer appointments', 'singular' => 'appointment',
    'from' => 'officer_appointments x JOIN officers o ON o.id = x.officer_id JOIN persons pe ON pe.id = o.person_id JOIN positions ps ON ps.id = x.position_id
               LEFT JOIN organizational_units ou ON ou.id = x.org_unit_id LEFT JOIN regions r ON r.id = x.region_id',
    'select' => 'x.*, pe.first_name, pe.middle_name, pe.last_name, pe.suffix, ps.title AS position_title, ou.name AS unit_name, r.short_name AS region_name, o.region_id AS officer_region',
    'scope' => ['region' => 'o.region_id'],
    'scope_of' => function ($d) {
        $o = q_one('SELECT region_id FROM officers WHERE id = ?', [(int) ($d['officer_id'] ?? 0)]);
        return [$o['region_id'] ?? null, null, null, null];
    },
    'attachments' => true,
    'search' => ['pe.first_name', 'pe.last_name', 'ps.title', 'x.appointment_reference'],
    'sorts' => ['start' => 'x.start_date', 'name' => 'pe.last_name', 'position' => 'ps.rank_order'],
    'default_sort' => 'start', 'default_dir' => 'desc',
    'filters' => [
        'status' => ['label' => 'Status', 'col' => 'x.appointment_status', 'options' => $statusOpts],
        'position' => ['label' => 'Position', 'col' => 'x.position_id', 'options_sql' => 'SELECT id, title FROM positions WHERE archived_at IS NULL ORDER BY rank_order, title'],
        'unit' => ['label' => 'Unit', 'col' => 'x.org_unit_id', 'options_sql' => 'SELECT id, name FROM organizational_units WHERE archived_at IS NULL ORDER BY sort_order, name'],
    ],
    'columns' => [
        ['label' => 'Officer', 'sort' => 'name', 'value' => fn($r) => e(full_name($r))],
        ['label' => 'Position', 'sort' => 'position', 'value' => fn($r) => e($r['position_title']) . ($r['unit_name'] ? '<span class="sub">' . e($r['unit_name']) . '</span>' : '')],
        ['label' => 'Period', 'sort' => 'start', 'value' => fn($r) => e(fmt_date($r['start_date'])) . ' to ' . ($r['end_date'] ? e(fmt_date($r['end_date'])) : 'present')],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['appointment_status'])],
    ],
    'fields' => [
        'officer_id' => ['label' => 'Officer', 'type' => 'lookup_sql', 'required' => true,
            'options_sql' => "SELECT o.id, CONCAT(pe.last_name, ', ', pe.first_name, ' (', o.officer_code, ')') FROM officers o JOIN persons pe ON pe.id = o.person_id WHERE o.archived_at IS NULL ORDER BY pe.last_name"],
        'position_id' => ['label' => 'Official position', 'type' => 'lookup_sql', 'required' => true, 'options_sql' => "SELECT id, title FROM positions WHERE archived_at IS NULL AND position_status = 'active' ORDER BY rank_order, title"],
        'org_unit_id' => ['label' => 'Department, committee or office', 'type' => 'lookup_sql', 'options_sql' => 'SELECT id, name FROM organizational_units WHERE archived_at IS NULL ORDER BY sort_order, name'],
        'region_id' => ['label' => 'Region (regional appointments)', 'type' => 'region'],
        'start_date' => ['label' => 'Effective from', 'type' => 'date', 'required' => true],
        'end_date' => ['label' => 'Effective until', 'type' => 'date'],
        'appointment_status' => ['label' => 'Appointment status', 'type' => 'select', 'options' => $statusOpts, 'required' => true, 'default' => 'current'],
        'appointment_reference' => ['label' => 'Appointment reference', 'max' => 100, 'help' => 'Board resolution or appointment paper number.'],
        'document_media_id' => ['label' => 'Appointment document', 'type' => 'media', 'kind' => 'document', 'visibility' => 'private'],
        'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 2000],
    ],
    'prefill' => ['officer_id'],
    'title_of' => fn($r) => full_name($r) . ', ' . $r['position_title'],
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if (!empty($data['start_date']) && !empty($data['end_date']) && $data['end_date'] < $data['start_date']) {
            $errors['end_date'] = 'The end date cannot be earlier than the start date.';
        }
        if (($data['appointment_status'] ?? '') === 'current' && !empty($data['end_date']) && $data['end_date'] < today()) {
            $errors['appointment_status'] = 'An appointment that has ended cannot be marked current.';
        }
    },
    'custom_actions' => [
        'end' => ['label' => 'End appointment', 'perm' => 'officers.edit', 'class' => 'btn-secondary', 'confirm' => 'End this appointment today? It will remain in the officer history.',
            'show' => fn($r) => $r['appointment_status'] === 'current' && !$r['archived_at'],
            'run' => function ($r) {
                q("UPDATE officer_appointments SET appointment_status = 'ended', end_date = ?, updated_at = ?, updated_by = ? WHERE id = ?", [today(), now(), uid(), $r['id']]);
                revision_log('appointments', (int) $r['id'], 'updated', ['appointment_status' => ['from' => 'current', 'to' => 'ended']]);
                audit_log('end_appointment', 'appointments', (int) $r['id'], 'Ended appointment');
                return 'Appointment ended and kept in the history.';
            }],
    ],
];
