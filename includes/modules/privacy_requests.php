<?php
// Data subject requests under RA 10173 (access, correction, deletion, objection)
$types = ['access' => 'Access to personal data', 'correction' => 'Correction', 'deletion' => 'Deletion or blocking', 'objection' => 'Objection to processing', 'portability' => 'Data portability', 'other' => 'Other'];
$st = ['received' => 'Received', 'in_review' => 'In review', 'completed' => 'Completed', 'denied' => 'Denied'];
return [
    'key' => 'privacy_requests', 'perm' => 'privacy', 'page' => 'admin/privacy-requests.php', 'table' => 'privacy_requests', 'alias' => 'x',
    'perm_map' => ['view' => 'privacy.view', 'create' => 'privacy.manage', 'edit' => 'privacy.manage', 'archive' => 'privacy.manage'],
    'title' => 'Privacy requests', 'singular' => 'privacy request', 'export' => false, 'log_view' => true,
    'from' => 'privacy_requests x LEFT JOIN members mb ON mb.id = x.member_id',
    'select' => 'x.*, mb.member_ref',
    'search' => ['x.reference_code', 'x.requester_name'],
    'sorts' => ['received' => 'x.created_at', 'due' => 'x.due_date'], 'default_sort' => 'received', 'default_dir' => 'desc',
    'filters' => ['type' => ['label' => 'Type', 'col' => 'x.request_type', 'options' => $types], 'status' => ['label' => 'Status', 'col' => 'x.request_status', 'options' => $st]],
    'columns' => [
        ['label' => 'Request', 'value' => fn($r) => e($r['reference_code']) . '<span class="sub">' . e($types[$r['request_type']]) . '</span>'],
        ['label' => 'Requester', 'value' => fn($r) => e($r['requester_name'])],
        ['label' => 'Received', 'sort' => 'received', 'value' => fn($r) => e(fmt_date($r['created_at']))],
        ['label' => 'Due', 'sort' => 'due', 'value' => fn($r) => e(fmt_date($r['due_date'])) . ($r['due_date'] && $r['due_date'] < today() && !in_array($r['request_status'], ['completed', 'denied'], true) ? '<span class="sub st st-bad">Overdue</span>' : '')],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['request_status'])],
    ],
    'fields' => [
        'requester_name' => ['label' => 'Requester name', 'required' => true, 'max' => 150],
        'requester_email' => ['label' => 'Requester email', 'type' => 'email', 'column' => 'requester_email_enc', 'encrypt' => true, 'sensitive_perm' => 'privacy.manage', 'required' => true],
        'request_type' => ['label' => 'Request type', 'type' => 'select', 'options' => $types, 'required' => true],
        'details' => ['label' => 'Details', 'type' => 'textarea', 'required' => true, 'max' => 5000],
        'member_lookup' => ['label' => 'Related member reference', 'virtual' => true, 'max' => 30, 'help' => 'Internal member reference (CNPL-MEM-…) if the request concerns a member record.', 'hide_view' => true],
        'request_status' => ['label' => 'Status', 'type' => 'select', 'options' => $st, 'required' => true, 'default' => 'received'],
        'due_date' => ['label' => 'Response due', 'type' => 'date'],
        'resolution' => ['label' => 'Resolution and actions taken', 'type' => 'textarea', 'max' => 5000],
        'reference_code' => ['label' => 'Reference', 'computed' => true],
        'member_id' => ['label' => 'Related member', 'computed' => true, 'display' => fn($r) => $r['member_ref'] && can('members.view') ? '<a href="' . e(url('admin/members.php', ['action' => 'view', 'id' => $r['member_id']])) . '">' . e($r['member_ref']) . '</a>' : e($r['member_ref'] ?? '')],
    ],
    'view_sections' => ['Request' => ['reference_code', 'requester_name', 'requester_email', 'request_type', 'details', 'member_id'], 'Handling' => ['request_status', 'due_date', 'resolution']],
    'title_of' => fn($r) => $r['reference_code'],
    'validate' => function (array &$data, ?array $old, array &$errors, array $values) {
        $ref = trim((string) ($values['member_lookup'] ?? ''));
        if ($ref !== '') {
            $mid = q_val('SELECT id FROM members WHERE member_ref = ?', [$ref]);
            if (!$mid) {
                $errors['member_lookup'] = 'No member has this reference.';
            } else {
                $data['member_id'] = (int) $mid;
            }
        }
    },
    'before_save' => function (array &$data, ?array $old) {
        if (!$old) {
            $data['reference_code'] = 'PR-' . date('Y') . '-' . random_code(6);
            if (empty($data['due_date'])) {
                $data['due_date'] = date('Y-m-d', strtotime('+' . setting_int('privacy_request_days', 30) . ' days'));
            }
        }
        if (isset($data['request_status']) && (!$old || $old['request_status'] !== $data['request_status'])) {
            $data['handled_by'] = uid();
        }
    },
];
