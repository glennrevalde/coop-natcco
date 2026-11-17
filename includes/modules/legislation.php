<?php
// Legislative portal: verified official information is kept apart from organizational commentary
$measureOpts = ['house_bill' => 'House Bill', 'senate_bill' => 'Senate Bill', 'house_resolution' => 'House Resolution', 'senate_resolution' => 'Senate Resolution',
    'joint_resolution' => 'Joint Resolution', 'republic_act' => 'Republic Act', 'executive_issuance' => 'Executive issuance', 'other' => 'Other measure'];
$statusOpts = legislative_status_options();
return [
    'key' => 'legislation', 'perm' => 'content', 'page' => 'admin/legislation.php', 'table' => 'legislative_updates', 'alias' => 'x',
    'title' => 'Legislative updates', 'singular' => 'legislative record', 'workflow' => true, 'wf_perm' => 'content', 'export' => false,
    'search' => ['x.title', 'x.measure_number', 'x.sponsors'],
    'sorts' => ['filed' => 'x.filing_date', 'title' => 'x.title', 'number' => 'x.measure_number'], 'default_sort' => 'filed', 'default_dir' => 'desc',
    'filters' => [
        'measure' => ['label' => 'Measure type', 'col' => 'x.measure_type', 'options' => $measureOpts],
        'lstatus' => ['label' => 'Legislative status', 'col' => 'x.legislative_status', 'options' => $statusOpts],
        'verified' => ['label' => 'Official information', 'options' => ['1' => 'Verified', '0' => 'Not yet verified'], 'where' => fn($v) => ['x.is_officially_verified = ?', [(int) $v]]],
    ],
    'columns' => [
        ['label' => 'Measure', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . '<span class="sub">' . e(trim(($measureOpts[$r['measure_type']] ?? '') . ' ' . $r['measure_number'])) . ($r['congress'] ? ', ' . e($r['congress']) : '') . '</span>'],
        ['label' => 'Legislative status', 'value' => fn($r) => e($statusOpts[$r['legislative_status']] ?? '')],
        ['label' => 'Filed', 'sort' => 'filed', 'value' => fn($r) => e(fmt_date($r['filing_date']))],
        ['label' => 'Official info', 'value' => fn($r) => $r['is_officially_verified'] ? status_text('verified') : status_text('pending', 'Not verified')],
        ['label' => 'Publishing', 'value' => fn($r) => status_text($r['status'])],
    ],
    'fields' => [
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'slug' => ['label' => 'Web address (slug)', 'max' => 160, 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'pattern_message' => 'Use lowercase letters, numbers and hyphens only.', 'help' => 'Leave blank to create one from the title.'],
        'measure_type' => ['label' => 'Measure type', 'type' => 'select', 'options' => $measureOpts, 'required' => true],
        'measure_number' => ['label' => 'Measure number', 'max' => 60, 'help' => 'For example: HB 1234.'],
        'congress' => ['label' => 'Congress', 'max' => 40, 'help' => 'For example: 20th Congress.'],
        'category_id' => ['label' => 'Subject area', 'type' => 'lookup_sql', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'legislative' AND is_active = 1 ORDER BY sort_order"],
        'legislative_status' => ['label' => 'Legislative status', 'type' => 'select', 'options' => $statusOpts, 'required' => true, 'default' => 'filed'],
        'filing_date' => ['label' => 'Filing date', 'type' => 'date'],
        'approval_date' => ['label' => 'Approval or enactment date', 'type' => 'date'],
        'sponsors' => ['label' => 'Principal authors or sponsors', 'type' => 'textarea', 'max' => 2000, 'rows' => 2, 'help' => 'Only names verified against the official record.'],
        'official_summary' => ['label' => 'Official summary', 'type' => 'richtext', 'help' => 'Taken from the official record (bill text, explanatory note or official digest).'],
        'official_source_url' => ['label' => 'Official source link', 'type' => 'url', 'max' => 255, 'help' => 'Link to the House of Representatives, Senate or Official Gazette record.'],
        'document_media_id' => ['label' => 'Official document', 'type' => 'media', 'kind' => 'document', 'visibility' => 'public'],
        'is_officially_verified' => ['label' => 'The official information above was checked against the official record', 'type' => 'bool', 'perm' => 'content.publish'],
        'verification_note' => ['label' => 'Verification note', 'max' => 255, 'perm' => 'content.publish', 'help' => 'How and when it was verified.'],
        'commentary' => ['label' => 'Organizational commentary', 'type' => 'richtext', 'help' => 'Shown separately and clearly labeled as COOP NATCCO Partylist commentary.'],
        'reference_links' => ['label' => 'Other reference links', 'type' => 'textarea', 'max' => 3000, 'rows' => 3, 'help' => 'One https:// link per line.'],
        'publish_at' => ['label' => 'Publish on', 'type' => 'datetime'],
    ],
    'sections' => [
        'Measure' => ['title', 'slug', 'measure_type', 'measure_number', 'congress', 'category_id', 'legislative_status', 'filing_date', 'approval_date', 'sponsors'],
        'Verified official information' => ['official_summary', 'official_source_url', 'document_media_id', 'is_officially_verified', 'verification_note'],
        'Organizational commentary and references' => ['commentary', 'reference_links', 'publish_at'],
    ],
    'public_url' => fn($r) => url('legislation.php', ['slug' => $r['slug']]),
    'validate' => function (array &$data, ?array $old, array &$errors) {
        unique_slug('legislative_updates', $data, $old, $errors);
        if (!empty($data['reference_links'])) {
            foreach (preg_split('/\R/', $data['reference_links']) as $line) {
                $line = trim($line);
                if ($line !== '' && (!preg_match('#^https?://#i', $line) || !filter_var($line, FILTER_VALIDATE_URL))) {
                    $errors['reference_links'] = 'Each reference link must be a full web address on its own line.';
                    break;
                }
            }
        }
        if (!empty($data['is_officially_verified']) && empty($data['official_source_url']) && empty($old['document_media_id']) && empty($_FILES['document_media_id__file']['name'])) {
            $errors['is_officially_verified'] = 'Add the official source link or document before marking the information as verified.';
        }
    },
];
