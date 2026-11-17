<?php
// Public website navigation (main menu and footer links)
return [
    'key' => 'menus', 'perm' => 'menus', 'page' => 'admin/menus.php', 'table' => 'menu_items', 'alias' => 'x',
    'perm_map' => ['view' => 'menus.manage', 'create' => 'menus.manage', 'edit' => 'menus.manage'],
    'title' => 'Website menus', 'singular' => 'menu item', 'export' => false, 'soft_delete' => false,
    'list_intro' => 'Turn a link off instead of deleting it. Links to pages of this website use the page file name, for example news.php; external links must start with https://.',
    'from' => 'menu_items x LEFT JOIN menu_items px ON px.id = x.parent_id',
    'select' => 'x.*, px.label AS parent_label',
    'search' => ['x.label', 'x.url'],
    'sorts' => ['order' => 'COALESCE(px.sort_order, x.sort_order) ASC, x.parent_id IS NOT NULL, x.sort_order'], 'default_sort' => 'order', 'default_dir' => 'asc',
    'filters' => ['location' => ['label' => 'Menu', 'col' => 'x.menu_location', 'options' => ['main' => 'Main menu', 'footer' => 'Footer links']]],
    'columns' => [
        ['label' => 'Label', 'value' => fn($r) => ($r['parent_id'] ? '<span class="indent" aria-hidden="true">↳</span> ' : '') . e($r['label']) . ($r['parent_label'] ? '<span class="sub">Under ' . e($r['parent_label']) . '</span>' : '')],
        ['label' => 'Link', 'value' => fn($r) => e($r['url'])],
        ['label' => 'Menu', 'value' => fn($r) => e($r['menu_location'] === 'main' ? 'Main' : 'Footer')],
        ['label' => 'Order', 'class' => 'num', 'value' => fn($r) => (int) $r['sort_order']],
        ['label' => 'Shown', 'value' => fn($r) => $r['is_active'] ? 'Yes' : '<span class="muted">Hidden</span>'],
    ],
    'fields' => [
        'menu_location' => ['label' => 'Menu', 'type' => 'select', 'options' => ['main' => 'Main menu', 'footer' => 'Footer links'], 'required' => true, 'default' => 'main'],
        'parent_id' => ['label' => 'Show under', 'type' => 'lookup_sql', 'options_sql' => "SELECT id, label FROM menu_items WHERE parent_id IS NULL AND menu_location = 'main' ORDER BY sort_order", 'placeholder' => '— Top level —'],
        'label' => ['label' => 'Label', 'required' => true, 'max' => 80],
        'url' => ['label' => 'Link', 'required' => true, 'max' => 255, 'help' => 'Page file such as about.php or page.php?slug=my-page, or a full https:// address.'],
        'sort_order' => ['label' => 'Order', 'type' => 'number', 'default' => 100],
        'is_active' => ['label' => 'Show this link', 'type' => 'bool', 'default' => 1],
        'opens_new_tab' => ['label' => 'Open in a new tab', 'type' => 'bool'],
    ],
    'title_of' => fn($r) => $r['label'],
    'validate' => function (array &$data, ?array $old, array &$errors) {
        $u = (string) ($data['url'] ?? '');
        if ($u !== '' && !preg_match('#^https://[^\s<>"]+$#i', $u) && !preg_match('#^[a-z0-9\-/]+\.php(\?[a-z0-9=&_\-]*)?$#i', $u)) {
            $errors['url'] = 'Use a page file name such as news.php or a full https:// address.';
        }
        if (($data['menu_location'] ?? '') === 'footer') {
            $data['parent_id'] = null;
        }
        if ($old && !empty($data['parent_id']) && (int) $data['parent_id'] === (int) $old['id']) {
            $errors['parent_id'] = 'A link cannot be placed under itself.';
        }
    },
];
