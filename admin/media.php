<?php
/**
 * Media library: images and documents used on the website.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';

require_perm('media.view');
if (is_post()) {
    $do = post('do');
    if ($do === 'upload') {
        require_perm('media.upload');
        $kind = post('kind') === 'document' ? 'document' : 'image';
        $vis = post('visibility') === 'private' ? 'private' : 'public';
        try {
            if (!upload_present('file')) {
                throw new ValidationError('Choose a file.');
            }
            $id = media_upload($_FILES['file'], $kind, $vis, mb_substr(post('title'), 0, 191) ?: null);
            if (post('alt_text') !== '') {
                q('UPDATE media_assets SET alt_text = ? WHERE id = ?', [mb_substr(post('alt_text'), 0, 255), $id]);
            }
            audit_log('upload', 'media', $id, 'Uploaded media file');
            flash('success', 'File uploaded. Copy its link to use it in content.');
        } catch (ValidationError $e) {
            flash('error', $e->getMessage());
        }
    } elseif ($do === 'meta') {
        require_perm('media.upload');
        $id = post_int('id');
        q('UPDATE media_assets SET title = ?, alt_text = ?, caption = ? WHERE id = ?', [mb_substr(post('title'), 0, 191) ?: null, mb_substr(post('alt_text'), 0, 255) ?: null, mb_substr(post('caption'), 0, 255) ?: null, $id]);
        audit_log('update', 'media', $id, 'Updated media details');
        flash('success', 'Details saved.');
    } elseif ($do === 'archive') {
        require_perm('media.archive');
        $id = post_int('id');
        q('UPDATE media_assets SET archived_at = ? WHERE id = ?', [now(), $id]);
        audit_log('archive', 'media', $id, 'Archived media file');
        flash('success', 'File archived. It is no longer served publicly.');
    }
    redirect(current_url());
}
$p = [];
$w = ['m.archived_at IS NULL', "m.id NOT IN (SELECT media_id FROM ticket_attachments)", "m.id NOT IN (SELECT media_id FROM record_attachments)"];
if (get('type') === 'image') {
    $w[] = "m.mime_type LIKE 'image/%'";
} elseif (get('type') === 'document') {
    $w[] = "m.mime_type NOT LIKE 'image/%'";
}
if (($q = mb_substr(get('q'), 0, 80)) !== '') {
    $w[] = '(m.original_name LIKE ? OR m.title LIKE ?)';
    array_push($p, like_escape($q), like_escape($q));
}
$where = implode(' AND ', $w);
[$page, $per, $offset] = pager_params(24);
$total = (int) q_val("SELECT COUNT(*) FROM media_assets m WHERE {$where}", $p);
$rows = q_all("SELECT m.*, u.full_name FROM media_assets m LEFT JOIN users u ON u.id = m.uploaded_by WHERE {$where} ORDER BY m.id DESC LIMIT {$per} OFFSET {$offset}", $p);
admin_start('Media library', [['Media library', null]]);
page_bar('Media library', fmt_num($total) . ' files. Images are resized and re-encoded on upload; metadata is removed.');
echo flash_render();
if (can('media.upload')) {
    echo '<form method="post" enctype="multipart/form-data" class="panel">' . csrf_field() . '<input type="hidden" name="do" value="upload"><h2 class="panel-title">Upload</h2><div class="grid">'
        . form_field('kind', ['label' => 'Type', 'type' => 'select', 'options' => ['image' => 'Image', 'document' => 'Document'], 'placeholder' => null, 'width' => 'third'], 'image')
        . form_field('visibility', ['label' => 'Visibility', 'type' => 'select', 'options' => ['public' => 'Public (website content)', 'private' => 'Private (signed-in staff)'], 'placeholder' => null, 'width' => 'third'], 'public')
        . '<div class="field field-third"><label for="m_file">File</label><input type="file" id="m_file" name="file" required accept="' . e(media_accept_attr('attachment')) . '"></div>'
        . form_field('title', ['label' => 'Title', 'max' => 191], '') . form_field('alt_text', ['label' => 'Text alternative (for images)', 'max' => 255, 'help' => 'Describe what the image shows for people who cannot see it.'], '')
        . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">Upload</button></div></form>';
}
?>
<form class="filters" method="get">
    <div class="filter filter-search"><label for="m_q">Search</label><input type="search" id="m_q" name="q" value="<?= e(get('q')) ?>"></div>
    <div class="filter"><label for="m_t">Type</label><?= select_html('type', ['image' => 'Images', 'document' => 'Documents'], get('type'), ['id' => 'm_t'], 'All') ?></div>
    <div class="filter filter-actions"><button class="btn btn-primary" type="submit">Apply</button></div>
</form>
<?php if (!$rows): ?><div class="empty"><h2>No files</h2><p>Uploaded images and documents will appear here.</p></div><?php else: ?>
<ul class="photo-grid">
<?php foreach ($rows as $m): $isImg = strpos($m['mime_type'], 'image/') === 0; ?>
    <li><?php if ($isImg): ?><img src="<?= e(media_url((int) $m['id'], true)) ?>" alt="<?= e($m['alt_text'] ?? '') ?>" loading="lazy"><?php else: ?><span class="code-box"><?= e(strtoupper($m['extension'])) ?></span><?php endif; ?>
        <strong><?= e(excerpt($m['title'] ?: $m['original_name'], 40)) ?></strong>
        <span class="muted"><?= e(human_size((int) $m['file_size'])) ?>, <?= e($m['visibility']) ?></span>
        <input type="text" readonly value="<?= e(media_url((int) $m['id'])) ?>" aria-label="Link to this file">
        <?php if (can('media.upload')): ?><details><summary class="small">Edit details</summary><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="meta"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <label class="small" for="mt<?= (int) $m['id'] ?>">Title</label><input type="text" id="mt<?= (int) $m['id'] ?>" name="title" value="<?= e($m['title']) ?>" maxlength="191">
            <label class="small" for="ma<?= (int) $m['id'] ?>">Text alternative</label><input type="text" id="ma<?= (int) $m['id'] ?>" name="alt_text" value="<?= e($m['alt_text']) ?>" maxlength="255">
            <label class="small" for="mc<?= (int) $m['id'] ?>">Caption</label><input type="text" id="mc<?= (int) $m['id'] ?>" name="caption" value="<?= e($m['caption']) ?>" maxlength="255">
            <button class="btn btn-secondary btn-sm" type="submit">Save</button></form></details><?php endif; ?>
        <?php if (can('media.archive')): ?><form method="post" data-confirm="Archive this file? Pages that use it will no longer show it."><?= csrf_field() ?><input type="hidden" name="do" value="archive"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="link-btn" type="submit">Archive</button></form><?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
<?= pager_html($total, $page, $per) ?>
<?php endif;
admin_end();
