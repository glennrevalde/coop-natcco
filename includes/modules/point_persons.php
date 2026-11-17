<?php
// CNPL point persons: one active assignment per cooperative, with replacement history
$statusOpts = ['active' => 'Active', 'replaced' => 'Replaced', 'ended' => 'Ended', 'suspended' => 'Suspended'];
return [
    'key' => 'point_persons', 'perm' => 'point_persons', 'page' => 'admin/point-persons.php', 'table' => 'cnpl_point_persons', 'alias' => 'x',
    'title' => 'CNPL point persons', 'singular' => 'point-person assignment',
    'from' => 'cnpl_point_persons x JOIN cooperatives c ON c.id = x.cooperative_id JOIN persons pe ON pe.id = x.person_id LEFT JOIN regions r ON r.id = c.region_id
               LEFT JOIN users u ON u.id = x.user_id',
    'select' => 'x.*, c.name AS coop_name, c.reference_no AS coop_ref, pe.first_name, pe.middle_name, pe.last_name, pe.suffix, pe.email_enc, pe.phone_enc, r.short_name AS region_name, u.username',
    'scope' => ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'],
    'scope_of' => fn($d) => [null, null, null, $d['cooperative_id'] ?? null],
    'review' => true, 'attachments' => true, 'import' => true,
    'search' => ['pe.first_name', 'pe.last_name', 'c.name', 'x.role_title'],
    'sorts' => ['name' => 'pe.last_name', 'coop' => 'c.name', 'start' => 'x.start_date'],
    'default_sort' => 'coop', 'default_dir' => 'asc',
    'filters' => [
        'coop' => ['label' => 'Cooperative', 'type' => 'hidden', 'col' => 'x.cooperative_id', 'describe' => fn($v) => lookup_label('coop', $v)],
        'region' => ['label' => 'Region', 'type' => 'region', 'col' => 'c.region_id'],
        'status' => ['label' => 'Status', 'col' => 'x.assignment_status', 'options' => $statusOpts],
    ],
    'columns' => [
        ['label' => 'Point person', 'sort' => 'name', 'value' => fn($r) => e(full_name($r)) . '<span class="sub">' . e($r['role_title']) . '</span>'],
        ['label' => 'Assigned cooperative', 'sort' => 'coop', 'value' => fn($r) => e($r['coop_name']) . '<span class="sub">' . e($r['region_name']) . '</span>'],
        ['label' => 'Period', 'sort' => 'start', 'value' => fn($r) => e(fmt_date($r['start_date'])) . ($r['end_date'] ? ' to ' . e(fmt_date($r['end_date'])) : '')],
        ['label' => 'Login account', 'value' => fn($r) => $r['username'] ? e($r['username']) : '<span class="muted">None</span>'],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['assignment_status']) . ($r['review_status'] !== 'approved' ? '<span class="sub">' . status_text($r['review_status'], OPT_REVIEW[$r['review_status']]) . '</span>' : '')],
    ],
    'fields' => [
        'cooperative_id' => ['label' => 'Assigned cooperative', 'type' => 'coop', 'required' => true],
        'role_title' => ['label' => 'Official role', 'required' => true, 'max' => 120, 'default' => 'CNPL Point Person'],
        'person_id' => ['label' => 'Person', 'type' => 'person', 'required' => true],
        'user_id' => ['label' => 'Linked login account', 'type' => 'user', 'help' => 'Lets this person sign in with the CNPL Point Person role. Their access is limited to this assignment.', 'import' => false],
    ] + geo_fields('cov', false, 'coverage_', 'Coverage: ') + [
        'coverage_notes' => ['label' => 'Coverage notes', 'max' => 255, 'width' => 'full'],
        'start_date' => ['label' => 'Appointment date', 'type' => 'date'],
        'end_date' => ['label' => 'End date', 'type' => 'date'],
        'assignment_status' => ['label' => 'Status', 'type' => 'select', 'options' => $statusOpts, 'required' => true, 'default' => 'active'],
        'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 2000],
    ],
    'sections' => [
        'Assignment' => ['cooperative_id', 'role_title', 'person_id', 'user_id'],
        'Coverage' => ['coverage_region_id', 'coverage_province_id', 'coverage_city_id', 'coverage_notes'],
        'Status' => ['start_date', 'end_date', 'assignment_status', 'notes'],
    ],
    'prefill' => ['cooperative_id'],
    'title_of' => fn($r) => full_name($r) . ' for ' . $r['coop_name'],
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if (($data['assignment_status'] ?? '') === 'active' && !empty($data['cooperative_id'])) {
            $p = [(int) $data['cooperative_id']];
            $sql = "SELECT x.id FROM cnpl_point_persons x WHERE x.cooperative_id = ? AND x.assignment_status = 'active' AND x.archived_at IS NULL";
            if ($old) {
                $sql .= ' AND x.id <> ?';
                $p[] = (int) $old['id'];
            }
            if (q_val($sql, $p)) {
                $errors['cooperative_id'] = 'This cooperative already has an active point person. Use "Replace point person" on the current assignment.';
            }
        }
    },
    'custom_actions' => [
        'end' => ['label' => 'End assignment', 'perm' => 'point_persons.edit', 'class' => 'btn-secondary', 'confirm' => 'End this assignment today? It stays in the assignment history.',
            'show' => fn($r) => $r['assignment_status'] === 'active' && !$r['archived_at'],
            'run' => function ($r) {
                q("UPDATE cnpl_point_persons SET assignment_status = 'ended', end_date = ?, updated_at = ?, updated_by = ? WHERE id = ?", [today(), now(), uid(), $r['id']]);
                revision_log('point_persons', (int) $r['id'], 'updated', ['assignment_status' => ['from' => 'active', 'to' => 'ended']]);
                audit_log('end_assignment', 'point_persons', (int) $r['id'], 'Ended point-person assignment');
                return 'Assignment ended. You can now assign a new point person.';
            }],
    ],
    'view_panels' => function (array $row) {
        echo '<section class="panel"><h2 class="panel-title">Contact</h2><dl class="dl">';
        $email = decrypt_value($row['email_enc']);
        $phone = decrypt_value($row['phone_enc']);
        $show = can('persons.view_sensitive') || (int) $row['user_id'] === uid();
        echo '<div class="dl-item"><dt>Email</dt><dd>' . ($email ? e($show ? $email : mask_email($email)) : '<span class="muted">Not set</span>') . '</dd></div>';
        echo '<div class="dl-item"><dt>Phone</dt><dd>' . ($phone ? e($show ? $phone : mask_phone($phone)) : '<span class="muted">Not set</span>') . '</dd></div></dl></section>';
        if ($row['assignment_status'] === 'active' && can('point_persons.create') && !$row['archived_at']) {
            echo '<div class="panel panel-flat"><p>To replace this point person, end this assignment and then <a href="' . e(url('admin/point-persons.php', ['action' => 'create', 'cooperative_id' => $row['cooperative_id']])) . '">create a new assignment</a> for the same cooperative. The history is kept.</p></div>';
        }
        $hist = q_all('SELECT x.*, pe.first_name, pe.middle_name, pe.last_name, pe.suffix FROM cnpl_point_persons x JOIN persons pe ON pe.id = x.person_id WHERE x.cooperative_id = ? AND x.id <> ? ORDER BY x.start_date DESC', [$row['cooperative_id'], $row['id']]);
        related_table('Assignment and replacement history for this cooperative', ['Point person', 'Status', 'Period'], array_map(fn($h) => ['<a href="' . e(url('admin/point-persons.php', ['action' => 'view', 'id' => $h['id']])) . '">' . e(full_name($h)) . '</a>',
            status_text($h['assignment_status']), e(fmt_date($h['start_date'])) . ($h['end_date'] ? ' to ' . e(fmt_date($h['end_date'])) : '')], $hist), 'No earlier assignments.');
        $tasks = q_all('SELECT * FROM point_person_tasks WHERE point_person_id = ? AND archived_at IS NULL ORDER BY task_status IN (\'done\',\'cancelled\'), due_date', [$row['id']]);
        related_table('Tasks and correspondence', ['Title', 'Type', 'Due', 'Status'], array_map(fn($t) => ['<a href="' . e(url('admin/tasks.php', ['action' => 'view', 'id' => $t['id']])) . '">' . e($t['title']) . '</a>', e(ucfirst($t['task_type'])),
            e(fmt_date($t['due_date'])), status_text($t['task_status'])], $tasks), 'No tasks assigned.', can('point_persons.tasks') ? url('admin/tasks.php', ['action' => 'create', 'point_person_id' => $row['id']]) : '', 'Add task');
    },
    'after_save' => function (int $id, array $data, ?array $old) {
        if (!empty($data['user_id']) && (!$old || (int) $old['user_id'] !== (int) $data['user_id'])) {
            notify_user((int) $data['user_id'], 'task', 'You were linked to a CNPL point-person assignment', '', url('admin/point-persons.php', ['action' => 'view', 'id' => $id]));
        }
    },
];
