<?php
// Messages sent through the public contact form
$st = ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'];
return [
    'key' => 'messages', 'perm' => 'messages', 'page' => 'admin/messages.php', 'table' => 'contact_messages', 'alias' => 'x',
    'perm_map' => ['view' => 'messages.view', 'edit' => 'messages.view'],
    'title' => 'Contact messages', 'singular' => 'message', 'export' => false, 'soft_delete' => false, 'no_create' => true, 'revisions' => true,
    'search' => ['x.reference_code', 'x.full_name', 'x.subject'],
    'sorts' => ['date' => 'x.created_at'], 'default_sort' => 'date', 'default_dir' => 'desc',
    'filters' => ['status' => ['label' => 'Status', 'col' => 'x.message_status', 'options' => $st]],
    'columns' => [
        ['label' => 'Subject', 'value' => fn($r) => e($r['subject']) . '<span class="sub">' . e($r['reference_code']) . '</span>'],
        ['label' => 'From', 'value' => fn($r) => e($r['full_name'])],
        ['label' => 'Received', 'sort' => 'date', 'value' => fn($r) => e(fmt_datetime($r['created_at']))],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['message_status'])],
    ],
    'fields' => [
        'full_name' => ['label' => 'Name', 'hide_form' => true],
        'email' => ['label' => 'Email', 'type' => 'email', 'column' => 'email_enc', 'encrypt' => true, 'hide_form' => true],
        'subject' => ['label' => 'Subject', 'hide_form' => true],
        'message' => ['label' => 'Message', 'type' => 'textarea', 'hide_form' => true],
        'message_status' => ['label' => 'Status', 'type' => 'select', 'options' => $st, 'required' => true],
    ],
    'title_of' => fn($r) => $r['subject'],
    'view_panels' => function (array $row) {
        if ($row['message_status'] === 'new') {
            q("UPDATE contact_messages SET message_status = 'read', handled_by = ?, updated_at = ? WHERE id = ?", [uid(), now(), $row['id']]);
        }
        $email = decrypt_value($row['email_enc']);
        if ($email) {
            echo '<div class="panel panel-flat"><a class="btn btn-secondary" href="mailto:' . e($email) . '?subject=' . e(rawurlencode('Re: ' . $row['subject'] . ' [' . $row['reference_code'] . ']')) . '">Reply by email</a></div>';
        }
    },
];
