<?php
// People directory: one record per person, referenced by every registry.
$base = null;
if (!scope_is_national()) {
    $p1 = [];
    $coop = scope_sql(['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'], $p1);
    $p2 = [];
    $amb = scope_sql(['region' => 'a.region_id', 'province' => 'a.province_id', 'city' => 'a.city_id', 'coop' => 'a.cooperative_id'], $p2);
    $base = ["(x.created_by = ? OR EXISTS (SELECT 1 FROM cooperative_personnel cp JOIN cooperatives c ON c.id = cp.cooperative_id WHERE cp.person_id = x.id AND {$coop})
              OR EXISTS (SELECT 1 FROM cnpl_point_persons pp JOIN cooperatives c ON c.id = pp.cooperative_id WHERE pp.person_id = x.id AND {$coop})
              OR EXISTS (SELECT 1 FROM ambassadors_focal_persons a WHERE a.person_id = x.id AND {$amb}))", array_merge([(int) uid()], $p1, $p1, $p2)];
}
return [
    'key' => 'persons', 'perm' => 'persons', 'page' => 'admin/persons.php', 'table' => 'persons', 'alias' => 'x',
    'title' => 'People directory', 'singular' => 'person', 'export' => false,
    'list_intro' => 'Each real person has one record here. Officer, staff, point-person and ambassador records link to it, so a person can hold different roles over time without duplicate records.',
    'base_where' => $base,
    'search' => ['x.last_name', 'x.first_name', 'x.middle_name'],
    'sorts' => ['name' => 'x.last_name', 'created' => 'x.created_at'],
    'default_sort' => 'name', 'default_dir' => 'asc',
    'columns' => [
        ['label' => 'Name', 'sort' => 'name', 'value' => fn($r) => e(full_name($r))],
        ['label' => 'Email', 'value' => function ($r) {
            $v = decrypt_value($r['email_enc']);
            return $v ? e(can('persons.view_sensitive') ? $v : mask_email($v)) : '<span class="muted">None</span>';
        }],
        ['label' => 'Roles on record', 'value' => function ($r) {
            $c = q_one('SELECT (SELECT COUNT(*) FROM officers WHERE person_id = ?) o, (SELECT COUNT(*) FROM cooperative_personnel WHERE person_id = ?) cp,
                        (SELECT COUNT(*) FROM cnpl_point_persons WHERE person_id = ?) pp, (SELECT COUNT(*) FROM ambassadors_focal_persons WHERE person_id = ?) a', [$r['id'], $r['id'], $r['id'], $r['id']]);
            $bits = [];
            if ($c['o']) {
                $bits[] = 'CNPL officer';
            }
            if ($c['cp']) {
                $bits[] = $c['cp'] . ' cooperative position' . ($c['cp'] > 1 ? 's' : '');
            }
            if ($c['pp']) {
                $bits[] = 'point person';
            }
            if ($c['a']) {
                $bits[] = 'ambassador or focal person';
            }
            return $bits ? e(implode(', ', $bits)) : '<span class="muted">None</span>';
        }],
        ['label' => 'Added', 'sort' => 'created', 'value' => fn($r) => e(fmt_date($r['created_at']))],
    ],
    'fields' => [
        'first_name' => ['label' => 'First name', 'required' => true, 'max' => 80, 'width' => 'third'],
        'middle_name' => ['label' => 'Middle name', 'max' => 80, 'width' => 'third'],
        'last_name' => ['label' => 'Last name', 'required' => true, 'max' => 80, 'width' => 'third'],
        'suffix' => ['label' => 'Suffix', 'max' => 20, 'width' => 'third'],
        'email' => ['label' => 'Email', 'type' => 'email', 'column' => 'email_enc', 'encrypt' => true, 'bidx' => 'email_bidx', 'sensitive_perm' => 'persons.view_sensitive', 'width' => 'third'],
        'phone' => ['label' => 'Phone', 'type' => 'tel', 'column' => 'phone_enc', 'encrypt' => true, 'sensitive_perm' => 'persons.view_sensitive', 'width' => 'third'],
        'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 2000, 'help' => 'Operational notes only. Do not record sensitive personal information.'],
        'confirm_different' => ['label' => 'I checked the directory and this is a different person from any similar name', 'type' => 'bool', 'virtual' => true, 'hide_view' => true, 'import' => false],
    ],
    'title_of' => fn($r) => full_name($r),
    'validate' => function (array &$data, ?array $old, array &$errors, array $values) {
        if (!empty($data['first_name']) && !empty($data['last_name'])) {
            $key = name_key($data['first_name'], $data['last_name'], $data['middle_name'] ?? '');
            $p = [$key];
            $sql = 'SELECT id FROM persons WHERE name_key = ? AND archived_at IS NULL';
            if ($old) {
                $sql .= ' AND id <> ?';
                $p[] = (int) $old['id'];
            }
            if (($dup = q_val($sql . ' LIMIT 1', $p)) && empty($values['confirm_different'])) {
                $errors['first_name'] = 'A person with the same name already exists (' . lookup_label('person', $dup) . '). Tick the confirmation box if this is a different person.';
            }
        }
        if (!empty($data['email_bidx'])) {
            $p = [$data['email_bidx']];
            $sql = 'SELECT id FROM persons WHERE email_bidx = ?';
            if ($old) {
                $sql .= ' AND id <> ?';
                $p[] = (int) $old['id'];
            }
            if (q_val($sql, $p)) {
                $errors['email'] = 'Another person record already uses this email address.';
            }
        }
    },
    'before_save' => function (array &$data, ?array $old) {
        $data['name_key'] = name_key($data['first_name'] ?? $old['first_name'], $data['last_name'] ?? $old['last_name'], $data['middle_name'] ?? ($old['middle_name'] ?? ''));
    },
    'view_panels' => function (array $row) {
        $id = (int) $row['id'];
        $rows = [];
        if ($o = q_one('SELECT id, officer_code FROM officers WHERE person_id = ?', [$id])) {
            if (can('officers.view')) {
                $rows[] = ['CNPL officer', '<a href="' . e(url('admin/officers.php', ['action' => 'view', 'id' => $o['id']])) . '">' . e($o['officer_code']) . '</a>', ''];
            }
        }
        foreach (q_all('SELECT x.id, x.position_title, x.employment_status, x.cooperative_id, c.name FROM cooperative_personnel x JOIN cooperatives c ON c.id = x.cooperative_id WHERE x.person_id = ?', [$id]) as $p) {
            if (can('personnel.view') && scope_allows_coop((int) $p['cooperative_id'])) {
                $rows[] = ['Cooperative ' . e($p['position_title']), '<a href="' . e(url('admin/personnel.php', ['action' => 'view', 'id' => $p['id']])) . '">' . e($p['name']) . '</a>', status_text($p['employment_status'])];
            }
        }
        foreach (q_all('SELECT x.id, x.assignment_status, x.cooperative_id, c.name FROM cnpl_point_persons x JOIN cooperatives c ON c.id = x.cooperative_id WHERE x.person_id = ?', [$id]) as $p) {
            if (can('point_persons.view') && scope_allows_coop((int) $p['cooperative_id'])) {
                $rows[] = ['CNPL point person', '<a href="' . e(url('admin/point-persons.php', ['action' => 'view', 'id' => $p['id']])) . '">' . e($p['name']) . '</a>', status_text($p['assignment_status'])];
            }
        }
        foreach (q_all('SELECT id, designation_title, designation_status FROM ambassadors_focal_persons WHERE person_id = ?', [$id]) as $p) {
            if (can('ambassadors.view')) {
                $rows[] = [e($p['designation_title']), '<a href="' . e(url('admin/ambassadors.php', ['action' => 'view', 'id' => $p['id']])) . '">Designation #' . (int) $p['id'] . '</a>', status_text($p['designation_status'])];
            }
        }
        related_table('Roles held over time', ['Role', 'Record', 'Status'], $rows, 'This person has no linked roles yet.');
    },
];
