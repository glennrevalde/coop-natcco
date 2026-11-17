<?php
// Photo galleries (including archival photographs for the History page)
return [
    'key' => 'galleries', 'perm' => 'content', 'page' => 'admin/galleries.php', 'table' => 'galleries', 'alias' => 'x',
    'title' => 'Photo galleries', 'singular' => 'gallery', 'workflow' => true, 'wf_perm' => 'content', 'export' => false,
    'select' => 'x.*, (SELECT COUNT(*) FROM gallery_items gi WHERE gi.gallery_id = x.id) AS item_count',
    'search' => ['x.title'],
    'sorts' => ['title' => 'x.title', 'created' => 'x.created_at'], 'default_sort' => 'created', 'default_dir' => 'desc',
    'filters' => ['archival' => ['label' => 'Type', 'options' => ['1' => 'Archival photographs', '0' => 'Regular galleries'], 'where' => fn($v) => ['x.is_archival = ?', [(int) $v]]]],
    'columns' => [
        ['label' => 'Gallery', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . ($r['is_archival'] ? '<span class="sub">Archival</span>' : '')],
        ['label' => 'Photos', 'class' => 'num', 'value' => fn($r) => fmt_num($r['item_count'])],
        ['label' => 'Status', 'value' => fn($r) => status_text($r['status'])],
    ],
    'fields' => [
        'title' => ['label' => 'Title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'slug' => ['label' => 'Web address (slug)', 'max' => 160, 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'pattern_message' => 'Use lowercase letters, numbers and hyphens only.', 'help' => 'Leave blank to create one from the title.'],
        'category_id' => ['label' => 'Category', 'type' => 'lookup_sql', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'gallery' AND is_active = 1 ORDER BY sort_order"],
        'event_id' => ['label' => 'Related event', 'type' => 'lookup_sql', 'options_sql' => 'SELECT id, title FROM events WHERE archived_at IS NULL ORDER BY starts_at DESC'],
        'is_archival' => ['label' => 'Archival photographs (shown on the History page)', 'type' => 'bool'],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'max' => 2000],
        'cover_media_id' => ['label' => 'Cover image', 'type' => 'media', 'kind' => 'image', 'visibility' => 'public'],
        'publish_at' => ['label' => 'Publish on', 'type' => 'datetime'],
    ],
    'public_url' => fn($r) => url('gallery.php', ['slug' => $r['slug']]),
    'validate' => function (array &$data, ?array $old, array &$errors) {
        unique_slug('galleries', $data, $old, $errors);
    },
    'view_panels' => function (array $row) {
        $items = q_all('SELECT gi.*, m.original_name FROM gallery_items gi JOIN media_assets m ON m.id = gi.media_id WHERE gi.gallery_id = ? ORDER BY gi.sort_order, gi.id', [$row['id']]);
        echo '<section class="panel" id="photos"><h2 class="panel-title">Photos (' . count($items) . ')</h2>';
        if ($items) {
            echo '<ul class="photo-grid">';
            foreach ($items as $it) {
                echo '<li><img src="' . e(media_url((int) $it['media_id'], true)) . '" alt="' . e($it['caption'] ?: $it['original_name']) . '" loading="lazy"><span>' . e($it['caption'] ?: $it['original_name']) . '</span>';
                if (can('content.edit')) {
                    echo '<form method="post" action="' . e(url('admin/galleries.php', ['action' => 'remove_item', 'id' => $row['id'], 'item' => $it['id']])) . '" data-confirm="Remove this photo from the gallery?">' . csrf_field() . '<button class="link-btn" type="submit">Remove</button></form>';
                }
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="muted">No photos yet.</p>';
        }
        if (can('content.edit') && !$row['archived_at']) {
            echo '<form method="post" enctype="multipart/form-data" class="attach-form" action="' . e(url('admin/galleries.php', ['action' => 'add_items', 'id' => $row['id']])) . '">' . csrf_field()
                . '<div class="field field-half"><label for="g_files">Photos (up to 20 at a time)</label><input type="file" id="g_files" name="photos[]" multiple required accept="' . e(media_accept_attr('image')) . '"></div>'
                . '<div class="field field-half"><label for="g_caption">Caption for these photos</label><input type="text" id="g_caption" name="caption" maxlength="255"></div>'
                . '<div class="btn-row"><button class="btn btn-secondary" type="submit">Upload photos</button></div></form>';
        }
        echo '</section>';
    },
    'custom_page' => [
        'add_items' => function (array $m) {
            if (!is_post()) {
                abort(405);
            }
            crud_require($m, 'edit');
            $id = get_int('id');
            if (!crud_fetch($m, $id)) {
                abort(404);
            }
            $files = $_FILES['photos'] ?? null;
            $ok = 0;
            $errs = [];
            if ($files && is_array($files['name'])) {
                $n = min(20, count($files['name']));
                $order = (int) q_val('SELECT COALESCE(MAX(sort_order), 0) FROM gallery_items WHERE gallery_id = ?', [$id]);
                for ($i = 0; $i < $n; $i++) {
                    $f = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                    try {
                        $mid = media_upload($f, 'image', 'public', post('caption') ?: null);
                        q_insert('gallery_items', ['gallery_id' => $id, 'media_id' => $mid, 'caption' => mb_substr(post('caption'), 0, 255) ?: null, 'sort_order' => ++$order, 'created_by' => uid(), 'created_at' => now()]);
                        $ok++;
                    } catch (ValidationError $e) {
                        $errs[] = $files['name'][$i] . ': ' . $e->getMessage();
                    }
                }
            }
            audit_log('add_photos', 'galleries', $id, "Added {$ok} photos");
            flash($errs ? 'error' : 'success', "{$ok} photo(s) added." . ($errs ? ' Not added: ' . implode(' ', $errs) : ''));
            redirect(url('admin/galleries.php', ['action' => 'view', 'id' => $id]) . '#photos');
        },
        'remove_item' => function (array $m) {
            if (!is_post()) {
                abort(405);
            }
            crud_require($m, 'edit');
            $id = get_int('id');
            if (!crud_fetch($m, $id)) {
                abort(404);
            }
            q('DELETE FROM gallery_items WHERE id = ? AND gallery_id = ?', [get_int('item'), $id]);
            audit_log('remove_photo', 'galleries', $id, 'Removed gallery item #' . get_int('item'));
            flash('success', 'Photo removed from the gallery.');
            redirect(url('admin/galleries.php', ['action' => 'view', 'id' => $id]) . '#photos');
        },
    ],
];
