<?php
// Security incident and personal data breach documentation
$sev = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
$st = ['open' => 'Open', 'contained' => 'Contained', 'resolved' => 'Resolved', 'closed' => 'Closed'];
return [
    'key' => 'incidents', 'perm' => 'security', 'page' => 'admin/incidents.php', 'table' => 'security_incidents', 'alias' => 'x',
    'perm_map' => ['view' => 'security.view', 'create' => 'security.manage', 'edit' => 'security.manage', 'archive' => 'security.manage'],
    'title' => 'Security incidents', 'singular' => 'incident', 'export' => false,
    'list_intro' => 'For a personal data breach, the National Privacy Commission and affected data subjects must be notified within 72 hours of knowledge of the breach when notification is required.',
    'search' => ['x.title', 'x.description'],
    'sorts' => ['detected' => 'x.detected_at', 'severity' => "FIELD(x.severity,'critical','high','medium','low')"], 'default_sort' => 'detected', 'default_dir' => 'desc',
    'filters' => ['severity' => ['label' => 'Severity', 'col' => 'x.severity', 'options' => $sev], 'status' => ['label' => 'Status', 'col' => 'x.incident_status', 'options' => $st]],
    'columns' => [
        ['label' => 'Incident', 'value' => fn($r) => e($r['title']) . ($r['is_personal_data_breach'] ? '<span class="sub st st-bad">Personal data breach</span>' : '')],
        ['label' => 'Detected', 'sort' => 'detected', 'value' => fn($r) => e(fmt_datetime($r['detected_at']))],
        ['label' => 'Severity', 'sort' => 'severity', 'value' => fn($r) => status_text($r['severity'])],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['incident_status'])],
    ],
    'fields' => [
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'severity' => ['label' => 'Severity', 'type' => 'select', 'options' => $sev, 'required' => true, 'default' => 'medium'],
        'detected_at' => ['label' => 'Detected on', 'type' => 'datetime', 'required' => true],
        'incident_status' => ['label' => 'Status', 'type' => 'select', 'options' => $st, 'required' => true, 'default' => 'open'],
        'related_event_id' => ['label' => 'Related security event number', 'type' => 'number', 'min' => 1],
        'description' => ['label' => 'What happened', 'type' => 'textarea', 'required' => true, 'max' => 5000],
        'affected_systems' => ['label' => 'Affected systems', 'max' => 255, 'width' => 'full'],
        'is_personal_data_breach' => ['label' => 'This is a personal data breach', 'type' => 'bool'],
        'affected_data' => ['label' => 'Personal data involved and number of data subjects', 'type' => 'textarea', 'max' => 3000],
        'actions_taken' => ['label' => 'Containment and remediation actions', 'type' => 'textarea', 'max' => 5000],
        'npc_notified_at' => ['label' => 'National Privacy Commission notified on', 'type' => 'datetime'],
        'subjects_notified_at' => ['label' => 'Data subjects notified on', 'type' => 'datetime'],
    ],
    'sections' => [
        'Incident' => ['title', 'severity', 'detected_at', 'incident_status', 'related_event_id', 'description', 'affected_systems'],
        'Personal data breach' => ['is_personal_data_breach', 'affected_data', 'npc_notified_at', 'subjects_notified_at'],
        'Response' => ['actions_taken'],
    ],
    'after_save' => function (int $id, array $data, ?array $old) {
        if (!$old) {
            notify_perm('security.manage', 'security', 'A security incident was recorded: ' . ($data['title'] ?? ''), 'Severity: ' . ($data['severity'] ?? ''), url('admin/incidents.php', ['action' => 'view', 'id' => $id]), null, uid());
        }
    },
];
