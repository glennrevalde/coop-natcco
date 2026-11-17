<?php
// Confidential registry of regular members of primary cooperatives.
// Membership is never treated as political affiliation; no voter or preference data is stored.
$statusOpts = ['active' => 'Active', 'inactive' => 'Inactive', 'withdrawn' => 'Withdrawn', 'terminated' => 'Terminated', 'deceased' => 'Deceased'];
return [
    'key' => 'members', 'perm' => 'members', 'page' => 'admin/members.php', 'table' => 'members', 'alias' => 'x',
    'title' => 'Regular members', 'singular' => 'member', 'sensitive' => true, 'log_view' => true,
    'list_intro' => 'Confidential. Records are kept only on a documented lawful basis, contact details are encrypted and shown masked unless you have explicit access, and every record you open is logged.',
    'from' => 'members x JOIN cooperatives c ON c.id = x.cooperative_id LEFT JOIN regions r ON r.id = c.region_id',
    'select' => 'x.*, c.name AS coop_name, c.reference_no AS coop_ref, r.short_name AS region_name',
    'scope' => ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'],
    'scope_of' => fn($d) => [null, null, null, $d['cooperative_id'] ?? null],
    'review' => true, 'import' => true,
    'search' => ['x.member_ref', 'x.last_name', 'x.first_name', 'x.coop_member_no'],
    'sorts' => ['name' => 'x.last_name', 'ref' => 'x.member_ref', 'coop' => 'c.name', 'admitted' => 'x.date_admitted'],
    'default_sort' => 'name', 'default_dir' => 'asc',
    'filters' => [
        'coop' => ['label' => 'Cooperative', 'type' => 'hidden', 'col' => 'x.cooperative_id', 'describe' => fn($v) => lookup_label('coop', $v)],
        'region' => ['label' => 'Region', 'type' => 'region', 'col' => 'c.region_id'],
        'status' => ['label' => 'Status', 'col' => 'x.membership_status', 'options' => $statusOpts],
        'basis' => ['label' => 'Lawful basis', 'col' => 'x.lawful_basis', 'options' => ['contract' => 'Contract (membership agreement)', 'legal_obligation' => 'Legal obligation', 'legitimate_interest' => 'Legitimate interest', 'consent' => 'Consent', 'public_task' => 'Public task']],
    ],
    'columns' => [
        ['label' => 'Member', 'sort' => 'name', 'value' => fn($r) => e(full_name($r)) . '<span class="sub">' . e($r['member_ref']) . '</span>'],
        ['label' => 'Cooperative', 'sort' => 'coop', 'value' => fn($r) => e($r['coop_name']) . ($r['coop_member_no'] ? '<span class="sub">Member no. ' . e($r['coop_member_no']) . '</span>' : '')],
        ['label' => 'Region', 'value' => fn($r) => e($r['region_name'])],
        ['label' => 'Admitted', 'sort' => 'admitted', 'value' => fn($r) => e(fmt_date($r['date_admitted']))],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['membership_status']) . ($r['review_status'] !== 'approved' ? '<span class="sub">' . status_text($r['review_status'], OPT_REVIEW[$r['review_status']]) . '</span>' : '')],
    ],
    'fields' => [
        'cooperative_id' => ['label' => 'Primary cooperative', 'type' => 'coop', 'required' => true],
        'coop_member_no' => ['label' => "Cooperative's member number", 'max' => 60],
        'first_name' => ['label' => 'First name', 'required' => true, 'max' => 80, 'width' => 'third'],
        'middle_name' => ['label' => 'Middle name', 'max' => 80, 'width' => 'third'],
        'last_name' => ['label' => 'Last name', 'required' => true, 'max' => 80, 'width' => 'third'],
        'suffix' => ['label' => 'Suffix', 'max' => 20, 'width' => 'third'],
        'email' => ['label' => 'Email', 'type' => 'email', 'column' => 'email_enc', 'encrypt' => true, 'sensitive_perm' => 'members.view_sensitive', 'width' => 'third',
            'help' => 'Stored encrypted. Shown masked to users without access to contact details.'],
        'phone' => ['label' => 'Phone', 'type' => 'tel', 'column' => 'phone_enc', 'encrypt' => true, 'sensitive_perm' => 'members.view_sensitive', 'width' => 'third'],
        'address' => ['label' => 'Contact address', 'column' => 'address_enc', 'encrypt' => true, 'sensitive_perm' => 'members.view_sensitive', 'max' => 255, 'width' => 'full'],
        'membership_type' => ['label' => 'Membership type', 'type' => 'select', 'options' => ['regular' => 'Regular', 'associate' => 'Associate'], 'default' => 'regular', 'required' => true],
        'membership_status' => ['label' => 'Membership status', 'type' => 'select', 'options' => $statusOpts, 'default' => 'active', 'required' => true],
        'date_admitted' => ['label' => 'Date admitted', 'type' => 'date'],
        'date_ended' => ['label' => 'Date membership ended', 'type' => 'date'],
        'lawful_basis' => ['label' => 'Lawful basis for processing', 'type' => 'select', 'required' => true, 'default' => 'contract',
            'options' => ['contract' => 'Contract (membership agreement)', 'legal_obligation' => 'Legal obligation', 'legitimate_interest' => 'Legitimate interest', 'consent' => 'Consent', 'public_task' => 'Public task'],
            'help' => 'Required by the Data Privacy Act. Consent requires a consent reference.'],
        'consent_recorded_at' => ['label' => 'Consent recorded on', 'type' => 'datetime'],
        'consent_reference' => ['label' => 'Consent reference', 'max' => 150, 'help' => 'Form number or document reference showing consent.'],
        'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 1000, 'help' => 'Do not record political preferences, voting intentions or other sensitive personal information.'],
        'member_ref' => ['label' => 'Internal member reference', 'computed' => true],
    ],
    'sections' => [
        'Membership' => ['cooperative_id', 'coop_member_no', 'membership_type', 'membership_status', 'date_admitted', 'date_ended'],
        'Member' => ['first_name', 'middle_name', 'last_name', 'suffix', 'email', 'phone', 'address'],
        'Lawful processing' => ['lawful_basis', 'consent_recorded_at', 'consent_reference', 'notes'],
    ],
    'view_sections' => [
        'Membership' => ['member_ref', 'cooperative_id', 'coop_member_no', 'membership_type', 'membership_status', 'date_admitted', 'date_ended'],
        'Member' => ['first_name', 'middle_name', 'last_name', 'suffix', 'email', 'phone', 'address'],
        'Lawful processing' => ['lawful_basis', 'consent_recorded_at', 'consent_reference', 'notes'],
    ],
    'prefill' => ['cooperative_id'],
    'title_of' => fn($r) => full_name($r),
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if (($data['lawful_basis'] ?? '') === 'consent' && empty($data['consent_reference']) && empty($old['consent_reference'])) {
            $errors['consent_reference'] = 'Enter the consent reference when the lawful basis is consent.';
        }
        if (!empty($data['date_admitted']) && !empty($data['date_ended']) && $data['date_ended'] < $data['date_admitted']) {
            $errors['date_ended'] = 'The end date cannot be earlier than the admission date.';
        }
        if (!empty($data['coop_member_no']) && !empty($data['cooperative_id'])) {
            $p = [(int) $data['cooperative_id'], $data['coop_member_no']];
            $sql = 'SELECT id FROM members WHERE cooperative_id = ? AND coop_member_no = ?';
            if ($old) {
                $sql .= ' AND id <> ?';
                $p[] = (int) $old['id'];
            }
            if (q_val($sql, $p)) {
                $errors['coop_member_no'] = 'This member number is already used in the same cooperative.';
            }
        }
    },
    'before_save' => function (array &$data, ?array $old) {
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $data['name_key'] = name_key($data['first_name'] ?? $old['first_name'], $data['last_name'] ?? $old['last_name'], $data['middle_name'] ?? $old['middle_name'] ?? '');
        }
        if (!$old) {
            $data['member_ref'] = 'TMP-' . bin2hex(random_bytes(6));
        }
        if (($data['lawful_basis'] ?? '') === 'consent' && empty($data['consent_recorded_at']) && empty($old['consent_recorded_at'])) {
            $data['consent_recorded_at'] = now();
        }
    },
    'after_save' => function (int $id, array $data, ?array $old) {
        if (!$old) {
            q('UPDATE members SET member_ref = ? WHERE id = ?', ['CNPL-MEM-' . str_pad((string) $id, 7, '0', STR_PAD_LEFT), $id]);
        }
        $row = q_one('SELECT * FROM members WHERE id = ?', [$id]);
        $changed = !$old || (int) $old['cooperative_id'] !== (int) $row['cooperative_id'] || $old['membership_status'] !== $row['membership_status'] || $old['membership_type'] !== $row['membership_type'];
        if ($changed) {
            if ($old) {
                q("UPDATE cooperative_memberships SET end_date = ? WHERE member_id = ? AND end_date IS NULL", [today(), $id]);
            }
            q_insert('cooperative_memberships', ['member_id' => $id, 'cooperative_id' => $row['cooperative_id'], 'coop_member_no' => $row['coop_member_no'],
                'membership_type' => $row['membership_type'], 'membership_status' => $row['membership_status'], 'start_date' => $old ? today() : ($row['date_admitted'] ?: today()),
                'end_date' => in_array($row['membership_status'], ['withdrawn', 'terminated', 'deceased'], true) ? ($row['date_ended'] ?: today()) : null,
                'change_reason' => $old ? 'Membership details changed' : 'Initial record', 'recorded_by' => uid(), 'created_at' => now()]);
        }
    },
    'import_key' => fn($d) => ($d['cooperative_id'] ?? '') . '|' . (($d['coop_member_no'] ?? '') !== '' ? 'n:' . $d['coop_member_no'] : name_key($d['first_name'] ?? '', $d['last_name'] ?? '', $d['middle_name'] ?? '')),
    'import_duplicate' => function ($d) {
        if (!empty($d['coop_member_no']) && ($id = q_val('SELECT id FROM members WHERE cooperative_id = ? AND coop_member_no = ?', [(int) $d['cooperative_id'], $d['coop_member_no']]))) {
            return (int) $id;
        }
        $id = q_val('SELECT id FROM members WHERE cooperative_id = ? AND name_key = ? AND archived_at IS NULL LIMIT 1', [(int) ($d['cooperative_id'] ?? 0), name_key($d['first_name'] ?? '', $d['last_name'] ?? '', $d['middle_name'] ?? '')]);
        return $id ? (int) $id : null;
    },
    'view_panels' => function (array $row) {
        $hist = q_all('SELECT h.*, c.name AS coop_name FROM cooperative_memberships h JOIN cooperatives c ON c.id = h.cooperative_id WHERE h.member_id = ? ORDER BY h.id DESC', [$row['id']]);
        related_table('Membership history', ['Cooperative', 'Type', 'Status', 'From', 'Until', 'Note'], array_map(fn($h) => [e($h['coop_name']), e(ucfirst($h['membership_type'])), status_text($h['membership_status']),
            e(fmt_date($h['start_date'])), e(fmt_date($h['end_date'])), e($h['change_reason'])], $hist), 'No history yet.');
        if (can('privacy.view')) {
            $reqs = q_all('SELECT * FROM privacy_requests WHERE member_id = ? ORDER BY id DESC', [$row['id']]);
            related_table('Privacy requests about this member', ['Reference', 'Type', 'Status', 'Received'], array_map(fn($q) => ['<a href="' . e(url('admin/privacy-requests.php', ['action' => 'view', 'id' => $q['id']])) . '">' . e($q['reference_code']) . '</a>',
                e(ucfirst($q['request_type'])), status_text($q['request_status']), e(fmt_date($q['created_at']))], $reqs), 'No privacy requests.');
        }
    },
    'export_columns' => [
        'Member ref.' => fn($r) => $r['member_ref'], 'Last name' => fn($r) => $r['last_name'], 'First name' => fn($r) => $r['first_name'], 'Middle name' => fn($r) => $r['middle_name'],
        'Cooperative' => fn($r) => $r['coop_name'], 'Cooperative ref.' => fn($r) => $r['coop_ref'], "Coop member no." => fn($r) => $r['coop_member_no'],
        'Email' => fn($r) => can('members.view_sensitive') ? decrypt_value($r['email_enc']) : mask_email(decrypt_value($r['email_enc'])),
        'Phone' => fn($r) => can('members.view_sensitive') ? decrypt_value($r['phone_enc']) : mask_phone(decrypt_value($r['phone_enc'])),
        'Type' => fn($r) => $r['membership_type'], 'Status' => fn($r) => $r['membership_status'], 'Admitted' => fn($r) => $r['date_admitted'], 'Ended' => fn($r) => $r['date_ended'],
        'Lawful basis' => fn($r) => $r['lawful_basis'],
    ],
];
