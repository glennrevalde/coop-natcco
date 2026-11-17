<?php
$typeOpts = ['comelec_official' => 'COMELEC official release', 'certified_copy' => 'Certified true copy', 'canvass_report' => 'Canvass report', 'other' => 'Other documented source'];
return [
    'key' => 'election_sources', 'perm' => 'elections', 'page' => 'admin/election-sources.php', 'table' => 'election_sources', 'alias' => 'x',
    'title' => 'Official election sources', 'singular' => 'source', 'export' => false,
    'search' => ['x.title', 'x.issuing_body', 'x.reference_no'],
    'sorts' => ['date' => 'x.date_issued', 'title' => 'x.title'], 'default_sort' => 'date', 'default_dir' => 'desc',
    'filters' => ['type' => ['label' => 'Type', 'col' => 'x.source_type', 'options' => $typeOpts]],
    'select' => 'x.*, (SELECT COUNT(*) FROM election_results er WHERE er.source_id = x.id) AS used_by',
    'columns' => [
        ['label' => 'Source', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . '<span class="sub">' . e($typeOpts[$r['source_type']]) . '</span>'],
        ['label' => 'Issued by', 'value' => fn($r) => e($r['issuing_body'])],
        ['label' => 'Date issued', 'sort' => 'date', 'value' => fn($r) => e(fmt_date($r['date_issued']))],
        ['label' => 'Results citing it', 'class' => 'num', 'value' => fn($r) => fmt_num($r['used_by'])],
    ],
    'fields' => [
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'source_type' => ['label' => 'Type', 'type' => 'select', 'options' => $typeOpts, 'required' => true],
        'issuing_body' => ['label' => 'Issuing body', 'max' => 191],
        'reference_no' => ['label' => 'Reference number', 'max' => 100],
        'date_issued' => ['label' => 'Date issued', 'type' => 'date'],
        'reference_url' => ['label' => 'Official link', 'type' => 'url', 'max' => 255],
        'document_media_id' => ['label' => 'Source document', 'type' => 'media', 'kind' => 'document', 'visibility' => 'private'],
        'notes' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 3000],
    ],
    'validate' => function (array &$data, ?array $old, array &$errors) {
        if (empty($data['reference_url']) && empty($data['reference_no']) && empty($_FILES['document_media_id__file']['name']) && empty($old['document_media_id'])) {
            $errors['reference_no'] = 'Document the source with a reference number, an official link or an uploaded document.';
        }
    },
];
