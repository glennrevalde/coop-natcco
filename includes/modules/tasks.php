<?php
// Tasks and correspondence for CNPL point persons. Point persons only see their own assignments.
$statusOpts = ['open' => 'Open', 'in_progress' => 'In progress', 'done' => 'Done', 'cancelled' => 'Cancelled'];
$ownOnly = has_role('point_person') && !scope_is_national() && !can('point_persons.edit');
$ppParams = [];
$ppScope = scope_sql(['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'], $ppParams);
return [
    'key' => 'tasks', 'perm' => 'point_persons', 'page' => 'admin/tasks.php', 'table' => 'point_person_tasks', 'alias' => 'x',
    'title' => 'Point-person tasks and correspondence', 'singular' => 'task',
    'from' => 'point_person_tasks x JOIN cnpl_point_persons pp ON pp.id = x.point_person_id JOIN cooperatives c ON c.id = pp.cooperative_id JOIN persons pe ON pe.id = pp.person_id',
    'select' => 'x.*, c.name AS coop_name, pe.first_name, pe.middle_name, pe.last_name, pe.suffix, pp.user_id AS pp_user_id',
    'scope' => ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'],
    'scope_of' => function ($d) {
        $pp = q_one('SELECT cooperative_id FROM cnpl_point_persons WHERE id = ?', [(int) ($d['point_person_id'] ?? 0)]);
        return [null, null, null, $pp['cooperative_id'] ?? 0];
    },
    'base_where' => $ownOnly ? ['pp.user_id = ?', [(int) uid()]] : null,
    'search' => ['x.title', 'c.name', 'pe.last_name'],
    'sorts' => ['due' => 'x.due_date', 'title' => 'x.title', 'created' => 'x.created_at'],
    'default_sort' => 'due', 'default_dir' => 'asc',
    'filters' => [
        'status' => ['label' => 'Status', 'col' => 'x.task_status', 'options' => $statusOpts],
        'type' => ['label' => 'Type', 'col' => 'x.task_type', 'options' => ['task' => 'Task', 'correspondence' => 'Correspondence']],
    ],
    'columns' => [
        ['label' => 'Task', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . '<span class="sub">' . e(ucfirst($r['task_type'])) . '</span>'],
        ['label' => 'Point person', 'value' => fn($r) => e(full_name($r)) . '<span class="sub">' . e($r['coop_name']) . '</span>'],
        ['label' => 'Due', 'sort' => 'due', 'value' => fn($r) => e(fmt_date($r['due_date'])) . ($r['due_date'] && $r['due_date'] < today() && !in_array($r['task_status'], ['done', 'cancelled'], true) ? '<span class="sub st st-bad">Overdue</span>' : '')],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['task_status'])],
    ],
    'fields' => [
        'point_person_id' => ['label' => 'Point-person assignment', 'type' => 'lookup_sql', 'required' => true, 'width' => 'full',
            'options_sql' => "SELECT pp.id, CONCAT(pe.first_name, ' ', pe.last_name, ' - ', c.name) FROM cnpl_point_persons pp JOIN persons pe ON pe.id = pp.person_id JOIN cooperatives c ON c.id = pp.cooperative_id WHERE pp.assignment_status = 'active' AND pp.archived_at IS NULL AND {$ppScope} ORDER BY pe.last_name", 'options_params' => $ppParams],
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'task_type' => ['label' => 'Type', 'type' => 'select', 'options' => ['task' => 'Task', 'correspondence' => 'Correspondence'], 'required' => true, 'default' => 'task'],
        'due_date' => ['label' => 'Due date', 'type' => 'date'],
        'task_status' => ['label' => 'Status', 'type' => 'select', 'options' => $statusOpts, 'required' => true, 'default' => 'open'],
        'details' => ['label' => 'Details', 'type' => 'textarea', 'max' => 5000],
    ],
    'prefill' => ['point_person_id'],
    'can_edit_row' => fn($r) => can('point_persons.tasks') && (!has_role('point_person') || scope_is_national() || can('point_persons.edit') || (int) $r['pp_user_id'] === uid()),
    'after_save' => function (int $id, array $data, ?array $old) {
        if (!$old) {
            $pp = q_one('SELECT user_id FROM cnpl_point_persons WHERE id = ?', [(int) $data['point_person_id']]);
            if (!empty($pp['user_id']) && (int) $pp['user_id'] !== uid()) {
                notify_user((int) $pp['user_id'], 'task', 'A new task was assigned to you', '', url('admin/tasks.php', ['action' => 'view', 'id' => $id]));
            }
        }
    },
    'custom_actions' => [
        'done' => ['label' => 'Mark as done', 'perm' => 'point_persons.tasks', 'class' => 'btn-primary',
            'show' => fn($r) => !in_array($r['task_status'], ['done', 'cancelled'], true) && !$r['archived_at'] && (!has_role('point_person') || scope_is_national() || can('point_persons.edit') || (int) $r['pp_user_id'] === uid()),
            'run' => function ($r) {
                q("UPDATE point_person_tasks SET task_status = 'done', updated_at = ?, updated_by = ? WHERE id = ?", [now(), uid(), $r['id']]);
                revision_log('tasks', (int) $r['id'], 'updated', ['task_status' => ['from' => $r['task_status'], 'to' => 'done']]);
                audit_log('complete', 'tasks', (int) $r['id'], 'Task marked done');
                return 'Task marked as done.';
            }],
    ],
];
