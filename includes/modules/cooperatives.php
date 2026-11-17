<?php
// Primary cooperative registry
return [
    'key' => 'cooperatives', 'perm' => 'cooperatives', 'page' => 'admin/cooperatives.php', 'table' => 'cooperatives', 'alias' => 'c',
    'title' => 'Primary cooperatives', 'singular' => 'cooperative',
    'from' => 'cooperatives c LEFT JOIN regions r ON r.id = c.region_id LEFT JOIN provinces p ON p.id = c.province_id
               LEFT JOIN cities_municipalities cm ON cm.id = c.city_id LEFT JOIN cooperative_types ct ON ct.id = c.cooperative_type_id',
    'select' => 'c.*, r.short_name AS region_name, p.name AS province_name, cm.name AS city_name, ct.name AS type_name',
    'scope' => ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'],
    'scope_of' => fn($d) => [$d['region_id'] ?? null, $d['province_id'] ?? null, $d['city_id'] ?? null, $d['id'] ?? null],
    'review' => true, 'attachments' => true, 'import' => true,
    'search' => ['c.name', 'c.reference_no', 'c.cda_registration_no', 'c.contact_person'],
    'sorts' => ['name' => 'c.name', 'ref' => 'c.reference_no', 'region' => 'r.sort_order', 'created' => 'c.created_at', 'status' => 'c.org_status'],
    'default_sort' => 'name', 'default_dir' => 'asc',
    'filters' => [
        'region' => ['label' => 'Region', 'type' => 'region', 'col' => 'c.region_id'],
        'province' => ['label' => 'Province', 'type' => 'province', 'col' => 'c.province_id'],
        'city' => ['label' => 'City/municipality', 'type' => 'city', 'col' => 'c.city_id'],
        'type' => ['label' => 'Type', 'col' => 'c.cooperative_type_id', 'options_sql' => 'SELECT id, name FROM cooperative_types ORDER BY name'],
        'status' => ['label' => 'Status', 'col' => 'c.org_status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'dissolved' => 'Dissolved']],
        'verification' => ['label' => 'Verification', 'col' => 'c.verification_status', 'options' => ['unverified' => 'Unverified', 'verified' => 'Verified', 'needs_update' => 'Needs update']],
    ],
    'columns' => [
        ['label' => 'Cooperative', 'sort' => 'name', 'value' => fn($r) => e($r['name']) . '<span class="sub">' . e($r['reference_no']) . '</span>'],
        ['label' => 'Type', 'value' => fn($r) => e($r['type_name'] ?? '')],
        ['label' => 'Location', 'sort' => 'region', 'value' => fn($r) => e(geo_label($r))],
        ['label' => 'Status', 'sort' => 'status', 'value' => fn($r) => status_text($r['org_status'])],
        ['label' => 'Verification', 'value' => fn($r) => status_text($r['verification_status']) . ($r['review_status'] !== 'approved' ? '<span class="sub">' . status_text($r['review_status'], OPT_REVIEW[$r['review_status']]) . '</span>' : '')],
    ],
    'fields' => [
        'name' => ['label' => 'Cooperative name', 'required' => true, 'max' => 191, 'width' => 'full'],
        'cda_registration_no' => ['label' => 'CDA registration number', 'max' => 60, 'unique' => true, 'help' => 'As shown on the Certificate of Registration, where applicable.'],
        'cooperative_type_id' => ['label' => 'Cooperative type', 'type' => 'lookup_sql', 'options_sql' => 'SELECT id, name FROM cooperative_types WHERE is_active = 1 ORDER BY sort_order, name'],
        'category' => ['label' => 'Category', 'type' => 'select', 'options' => ['primary' => 'Primary', 'secondary' => 'Secondary', 'tertiary' => 'Tertiary'], 'default' => 'primary', 'required' => true],
        'date_registered' => ['label' => 'Date registered', 'type' => 'date'],
        'registered_address' => ['label' => 'Registered address', 'max' => 255, 'width' => 'full'],
        'operating_address' => ['label' => 'Operating address', 'max' => 255, 'width' => 'full', 'help' => 'Leave blank if the same as the registered address.'],
    ] + geo_fields('g', true) + [
        'contact_person' => ['label' => 'Authorized contact person', 'max' => 150],
        'contact_email' => ['label' => 'Contact email', 'type' => 'email'],
        'contact_phone' => ['label' => 'Contact phone', 'type' => 'tel', 'max' => 40],
        'website' => ['label' => 'Website', 'type' => 'url', 'max' => 191],
        'reported_member_count' => ['label' => 'Reported number of members', 'type' => 'number', 'min' => 0, 'help' => 'Aggregate count reported by the cooperative.'],
        'org_status' => ['label' => 'Organizational status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'dissolved' => 'Dissolved'], 'default' => 'active', 'required' => true],
        'is_public_listing' => ['label' => 'List in the public cooperative directory', 'type' => 'bool', 'help' => 'Only the name, type and location are shown publicly, and only after verification.'],
        'notes' => ['label' => 'Administrative notes', 'type' => 'textarea', 'max' => 3000],
        'reference_no' => ['label' => 'Reference number', 'computed' => true],
        'verification_status' => ['label' => 'Verification', 'computed' => true, 'type' => 'select', 'options' => ['unverified' => 'Unverified', 'verified' => 'Verified', 'needs_update' => 'Needs update']],
    ],
    'sections' => [
        'Identification' => ['name', 'cda_registration_no', 'cooperative_type_id', 'category', 'date_registered', 'reported_member_count'],
        'Address and location' => ['registered_address', 'operating_address', 'region_id', 'province_id', 'city_id'],
        'Authorized contact' => ['contact_person', 'contact_email', 'contact_phone', 'website'],
        'Status' => ['org_status', 'is_public_listing', 'notes'],
    ],
    'view_sections' => [
        'Identification' => ['reference_no', 'name', 'cda_registration_no', 'cooperative_type_id', 'category', 'date_registered', 'reported_member_count'],
        'Address and location' => ['registered_address', 'operating_address', 'region_id', 'province_id', 'city_id'],
        'Authorized contact' => ['contact_person', 'contact_email', 'contact_phone', 'website'],
        'Status' => ['org_status', 'verification_status', 'is_public_listing', 'notes'],
    ],
    'title_of' => fn($r) => $r['name'],
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if (!empty($data['name'])) {
            $key = coop_name_key($data['name']);
            $p = [$key];
            $sql = 'SELECT id, name, reference_no FROM cooperatives WHERE name_key = ? AND archived_at IS NULL';
            if (!empty($data['city_id'])) {
                $sql .= ' AND city_id = ?';
                $p[] = (int) $data['city_id'];
            } elseif (!empty($data['region_id'])) {
                $sql .= ' AND region_id = ? AND city_id IS NULL';
                $p[] = (int) $data['region_id'];
            }
            if ($old) {
                $sql .= ' AND id <> ?';
                $p[] = (int) $old['id'];
            }
            if ($dup = q_one($sql . ' LIMIT 1', $p)) {
                $errors['name'] = 'Possible duplicate: ' . $dup['name'] . ' (' . $dup['reference_no'] . ') is already registered in the same location.';
            }
        }
    },
    'before_save' => function (array &$data, ?array $old) {
        if (isset($data['name'])) {
            $data['name_key'] = coop_name_key($data['name']);
        }
        if (!$old) {
            $data['reference_no'] = 'TMP-' . bin2hex(random_bytes(6));
            $data['verification_status'] = 'unverified';
        } elseif (array_intersect_key($data, array_flip(['name', 'cda_registration_no', 'region_id', 'city_id', 'registered_address'])) && $old['verification_status'] === 'verified') {
            foreach (['name', 'cda_registration_no', 'region_id', 'city_id', 'registered_address'] as $k) {
                if (array_key_exists($k, $data) && (string) $data[$k] !== (string) $old[$k]) {
                    $data['verification_status'] = 'needs_update';
                    break;
                }
            }
        }
    },
    'after_save' => function (int $id, array $data, ?array $old) {
        if (!$old) {
            q('UPDATE cooperatives SET reference_no = ? WHERE id = ?', ['CNPL-COOP-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), $id]);
        }
    },
    'import_key' => fn($d) => coop_name_key($d['name'] ?? '') . '|' . ($d['city_id'] ?? $d['region_id'] ?? ''),
    'import_duplicate' => function ($d) {
        if (!empty($d['cda_registration_no']) && ($id = q_val('SELECT id FROM cooperatives WHERE cda_registration_no = ?', [$d['cda_registration_no']]))) {
            return (int) $id;
        }
        $p = [coop_name_key($d['name'] ?? '')];
        $sql = 'SELECT id FROM cooperatives WHERE name_key = ? AND archived_at IS NULL';
        if (!empty($d['city_id'])) {
            $sql .= ' AND city_id = ?';
            $p[] = (int) $d['city_id'];
        }
        return ($id = q_val($sql . ' LIMIT 1', $p)) ? (int) $id : null;
    },
    'custom_actions' => [
        'verify' => ['label' => 'Mark as verified', 'perm' => 'cooperatives.approve', 'class' => 'btn-secondary', 'confirm' => 'Confirm that the registration details were checked against supporting documents.',
            'show' => fn($r) => $r['verification_status'] !== 'verified' && $r['review_status'] === 'approved' && !$r['archived_at'],
            'run' => function ($r) {
                q("UPDATE cooperatives SET verification_status = 'verified', verified_by = ?, verified_at = ? WHERE id = ?", [uid(), now(), $r['id']]);
                revision_log('cooperatives', (int) $r['id'], 'verified', null);
                audit_log('verify', 'cooperatives', (int) $r['id'], 'Marked cooperative as verified');
                return 'Cooperative marked as verified.';
            }],
        'suspend' => ['label' => 'Suspend', 'perm' => 'cooperatives.approve', 'class' => 'btn-danger-ghost', 'confirm' => 'Suspend this cooperative? Its status changes to Suspended.',
            'show' => fn($r) => $r['org_status'] === 'active' && !$r['archived_at'],
            'run' => function ($r) {
                q("UPDATE cooperatives SET org_status = 'suspended', updated_at = ?, updated_by = ? WHERE id = ?", [now(), uid(), $r['id']]);
                revision_log('cooperatives', (int) $r['id'], 'updated', ['org_status' => ['from' => $r['org_status'], 'to' => 'suspended']]);
                audit_log('suspend', 'cooperatives', (int) $r['id'], 'Suspended cooperative');
                return 'Cooperative suspended.';
            }],
    ],
    'view_panels' => function (array $row) {
        $id = (int) $row['id'];
        if (can('personnel.view')) {
            $rows = q_all('SELECT x.*, pe.first_name, pe.middle_name, pe.last_name, pe.suffix FROM cooperative_personnel x JOIN persons pe ON pe.id = x.person_id WHERE x.cooperative_id = ? AND x.archived_at IS NULL ORDER BY x.employment_status = \'active\' DESC, x.personnel_type, x.position_title', [$id]);
            related_table('Officers and staff', ['Name', 'Position', 'Type', 'Status', 'Since'], array_map(fn($r) => [
                '<a href="' . e(url('admin/personnel.php', ['action' => 'view', 'id' => $r['id']])) . '">' . e(full_name($r)) . '</a>', e($r['position_title']) . ($r['department'] ? '<span class="sub">' . e($r['department']) . '</span>' : ''),
                e(ucfirst($r['personnel_type'])), status_text($r['employment_status']), e(fmt_date($r['start_date']))], $rows),
                'No officers or staff recorded.', can('personnel.create') ? url('admin/personnel.php', ['action' => 'create', 'cooperative_id' => $id]) : '', 'Add officer or staff');
        }
        if (can('point_persons.view')) {
            $rows = q_all('SELECT x.*, pe.first_name, pe.middle_name, pe.last_name, pe.suffix FROM cnpl_point_persons x JOIN persons pe ON pe.id = x.person_id WHERE x.cooperative_id = ? AND x.archived_at IS NULL ORDER BY x.assignment_status = \'active\' DESC, x.start_date DESC', [$id]);
            related_table('CNPL point persons', ['Name', 'Role', 'Status', 'Period'], array_map(fn($r) => [
                '<a href="' . e(url('admin/point-persons.php', ['action' => 'view', 'id' => $r['id']])) . '">' . e(full_name($r)) . '</a>', e($r['role_title']), status_text($r['assignment_status']),
                e(fmt_date($r['start_date'])) . ($r['end_date'] ? ' to ' . e(fmt_date($r['end_date'])) : '')], $rows),
                'No point person assigned.', can('point_persons.create') ? url('admin/point-persons.php', ['action' => 'create', 'cooperative_id' => $id]) : '', 'Assign point person');
        }
        if (can('members.view')) {
            $counts = q_pairs('SELECT membership_status, COUNT(*) FROM members WHERE cooperative_id = ? AND archived_at IS NULL GROUP BY membership_status', [$id]);
            echo '<section class="panel"><div class="panel-head"><h2 class="panel-title">Regular members</h2><a class="btn btn-secondary btn-sm" href="' . e(url('admin/members.php', ['f_coop' => $id])) . '">Open member registry</a></div><div class="stat-row compact">';
            foreach (['active' => 'Active', 'inactive' => 'Inactive', 'withdrawn' => 'Withdrawn', 'terminated' => 'Terminated'] as $k => $l) {
                echo '<div class="stat"><span class="stat-num">' . fmt_num($counts[$k] ?? 0) . '</span><span class="stat-label">' . $l . '</span></div>';
            }
            echo '</div></section>';
        }
        if (can('activities.view')) {
            $rows = q_all('SELECT * FROM activities WHERE cooperative_id = ? AND archived_at IS NULL ORDER BY activity_date DESC LIMIT 10', [$id]);
            related_table('Recent activities', ['Date', 'Activity', 'Type'], array_map(fn($r) => [e(fmt_date($r['activity_date'])),
                '<a href="' . e(url('admin/activities.php', ['action' => 'view', 'id' => $r['id']])) . '">' . e($r['title']) . '</a>', e(label_from_key($r['activity_type']))], $rows),
                'No activities recorded.', can('activities.create') ? url('admin/activities.php', ['action' => 'create', 'cooperative_id' => $id]) : '', 'Record activity');
        }
    },
    'export_columns' => [
        'Reference no.' => fn($r) => $r['reference_no'], 'Cooperative name' => fn($r) => $r['name'], 'CDA registration no.' => fn($r) => $r['cda_registration_no'],
        'Type' => fn($r) => $r['type_name'], 'Category' => fn($r) => $r['category'], 'Region' => fn($r) => $r['region_name'], 'Province' => fn($r) => $r['province_name'],
        'City/Municipality' => fn($r) => $r['city_name'], 'Registered address' => fn($r) => $r['registered_address'], 'Contact person' => fn($r) => $r['contact_person'],
        'Contact email' => fn($r) => $r['contact_email'], 'Contact phone' => fn($r) => $r['contact_phone'], 'Reported members' => fn($r) => $r['reported_member_count'],
        'Status' => fn($r) => $r['org_status'], 'Verification' => fn($r) => $r['verification_status'], 'Review' => fn($r) => $r['review_status'],
    ],
];
