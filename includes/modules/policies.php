<?php
// Policies, procedures, guidelines, circulars, manuals and forms with versioning
$docTypes = doc_type_options();
return [
    'key' => 'policies', 'perm' => 'content', 'page' => 'admin/policies.php', 'table' => 'policies', 'alias' => 'x',
    'title' => 'Policies and procedures', 'singular' => 'document', 'workflow' => true, 'wf_perm' => 'content', 'export' => false,
    'from' => 'policies x LEFT JOIN content_categories cc ON cc.id = x.category_id LEFT JOIN policy_versions pv ON pv.id = x.current_version_id',
    'select' => 'x.*, cc.name AS category_name, pv.version_label',
    'search' => ['x.title', 'x.reference_no', 'x.summary'],
    'sorts' => ['title' => 'x.title', 'effective' => 'x.effective_date'], 'default_sort' => 'title', 'default_dir' => 'asc',
    'filters' => [
        'doctype' => ['label' => 'Document type', 'col' => 'x.doc_type', 'options' => $docTypes],
        'visibility' => ['label' => 'Visibility', 'col' => 'x.visibility', 'options' => ['public' => 'Public', 'internal' => 'Internal']],
    ],
    'columns' => [
        ['label' => 'Document', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . '<span class="sub">' . e($docTypes[$r['doc_type']]) . ($r['reference_no'] ? ', ' . e($r['reference_no']) : '') . '</span>'],
        ['label' => 'Current version', 'value' => fn($r) => $r['version_label'] ? e($r['version_label']) : '<span class="muted">None yet</span>'],
        ['label' => 'Effective', 'sort' => 'effective', 'value' => fn($r) => e(fmt_date($r['effective_date']))],
        ['label' => 'Visibility', 'value' => fn($r) => e(ucfirst($r['visibility']))],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['status'])],
    ],
    'fields' => [
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'slug' => ['label' => 'Web address (slug)', 'max' => 160, 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'pattern_message' => 'Use lowercase letters, numbers and hyphens only.', 'help' => 'Leave blank to create one from the title.'],
        'doc_type' => ['label' => 'Document type', 'type' => 'select', 'options' => $docTypes, 'required' => true],
        'category_id' => ['label' => 'Category', 'type' => 'lookup_sql', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'document' AND is_active = 1 ORDER BY sort_order"],
        'reference_no' => ['label' => 'Reference number', 'max' => 60],
        'visibility' => ['label' => 'Visibility', 'type' => 'select', 'options' => ['public' => 'Public website', 'internal' => 'Internal (signed-in users only)'], 'required' => true, 'default' => 'public'],
        'summary' => ['label' => 'Summary', 'type' => 'textarea', 'max' => 500, 'rows' => 3],
        'publish_at' => ['label' => 'Publish on', 'type' => 'datetime'],
        'effective_date' => ['label' => 'Effective date of current version', 'type' => 'date', 'computed' => true],
    ],
    'view_sections' => ['Document' => ['title', 'doc_type', 'category_id', 'reference_no', 'visibility', 'summary', 'effective_date', 'publish_at']],
    'public_url' => fn($r) => url('policy.php', ['slug' => $r['slug']]),
    'validate' => function (array &$data, ?array $old, array &$errors) {
        unique_slug('policies', $data, $old, $errors);
    },
    'view_panels' => function (array $row) {
        $versions = q_all('SELECT v.*, u.full_name FROM policy_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.policy_id = ? ORDER BY v.id DESC', [$row['id']]);
        related_table('Versions', ['Version', 'Effective', 'Change notes', 'File', 'Added'], array_map(fn($v) => [
            e($v['version_label']) . ((int) $v['id'] === (int) $row['current_version_id'] ? '<span class="sub st st-ok">Current</span>' : ($v['superseded_at'] ? '<span class="sub muted">Superseded</span>' : '')),
            e(fmt_date($v['effective_date'])), e($v['change_notes']), $v['document_media_id'] ? '<a href="' . e(media_url((int) $v['document_media_id'])) . '">Download</a>' : '<span class="muted">Text only</span>',
            e(fmt_date($v['created_at'])) . '<span class="sub">' . e($v['full_name']) . '</span>'], $versions), 'No versions yet. Add the first version below.');
        if (can('content.edit') && !$row['archived_at']) {
            echo '<form method="post" enctype="multipart/form-data" class="panel" action="' . e(url('admin/policies.php', ['action' => 'add_version', 'id' => $row['id']])) . '">' . csrf_field()
                . '<h2 class="panel-title">Add a new version</h2><p class="muted">The new version becomes current; earlier versions stay available in the revision list.</p><div class="grid">'
                . form_field('version_label', ['label' => 'Version label', 'required' => true, 'max' => 30, 'width' => 'third', 'help' => 'For example: v2 or Rev. 2026-01'], '')
                . form_field('effective_date', ['label' => 'Effective date', 'type' => 'date', 'required' => true, 'width' => 'third'], '')
                . form_field('change_notes', ['label' => 'What changed', 'max' => 500, 'width' => 'third'], '')
                . form_field('body', ['label' => 'Document text', 'type' => 'richtext', 'help' => 'Optional when a file is uploaded.'], '')
                . form_field('document', ['label' => 'Document file', 'type' => 'media', 'kind' => 'document'], null)
                . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">Add version</button></div></form>';
        }
    },
    'custom_page' => [
        'add_version' => function (array $m) {
            if (!is_post()) {
                abort(405);
            }
            crud_require($m, 'edit');
            $id = get_int('id');
            $row = crud_fetch($m, $id);
            if (!$row || $row['archived_at']) {
                abort(404);
            }
            $label = mb_substr(post('version_label'), 0, 30);
            $eff = post('effective_date');
            $body = sanitize_html(post('body'));
            try {
                if ($label === '' || !valid_date($eff)) {
                    throw new ValidationError('Enter a version label and a valid effective date.');
                }
                $media = upload_present('document__file') ? media_upload($_FILES['document__file'], 'document', $row['visibility'] === 'public' ? 'public' : 'private', $row['title'] . ' ' . $label) : null;
                if (!$media && $body === '') {
                    throw new ValidationError('Add the document text or upload a file.');
                }
                tx(function () use ($id, $label, $eff, $body, $media, $row) {
                    q('UPDATE policy_versions SET superseded_at = ? WHERE policy_id = ? AND superseded_at IS NULL', [now(), $id]);
                    $vid = q_insert('policy_versions', ['policy_id' => $id, 'version_label' => $label, 'effective_date' => $eff, 'change_notes' => mb_substr(post('change_notes'), 0, 500) ?: null,
                        'body' => $body ?: null, 'document_media_id' => $media, 'created_by' => uid(), 'created_at' => now()]);
                    q('UPDATE policies SET current_version_id = ?, effective_date = ?, updated_at = ?, updated_by = ? WHERE id = ?', [$vid, $eff, now(), uid(), $id]);
                    revision_log('policies', $id, 'new_version', ['version' => ['from' => $row['version_label'], 'to' => $label]]);
                    audit_log('new_version', 'policies', $id, 'Added version ' . $label);
                });
                flash('success', 'Version ' . $label . ' added and set as current.');
            } catch (ValidationError $e) {
                flash('error', $e->getMessage());
            }
            redirect(url('admin/policies.php', ['action' => 'view', 'id' => $id]));
        },
    ],
];
