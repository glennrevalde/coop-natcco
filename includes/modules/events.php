<?php
// Events calendar with registration limits, public/private visibility and archives
$statusOpts = ['scheduled' => 'Scheduled', 'postponed' => 'Postponed', 'cancelled' => 'Cancelled', 'completed' => 'Completed'];
return [
    'key' => 'events', 'perm' => 'events', 'page' => 'admin/events.php', 'table' => 'events', 'alias' => 'x',
    'title' => 'Events', 'singular' => 'event', 'workflow' => true, 'wf_perm' => 'events', 'export' => false,
    'from' => 'events x LEFT JOIN content_categories cc ON cc.id = x.category_id',
    'select' => "x.*, cc.name AS category_name, (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = x.id AND er.registration_status IN ('registered','attended')) AS reg_count",
    'search' => ['x.title', 'x.location_name', 'x.organizer'],
    'sorts' => ['start' => 'x.starts_at', 'title' => 'x.title'], 'default_sort' => 'start', 'default_dir' => 'desc',
    'filters' => [
        'when' => ['label' => 'When', 'options' => ['upcoming' => 'Upcoming', 'past' => 'Completed and past'],
            'where' => fn($v) => [$v === 'upcoming' ? 'x.starts_at >= ?' : 'x.starts_at < ?', [now()]]],
        'visibility' => ['label' => 'Visibility', 'col' => 'x.visibility', 'options' => ['public' => 'Public', 'private' => 'Private (organization only)']],
        'category' => ['label' => 'Category', 'col' => 'x.category_id', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'event' ORDER BY sort_order"],
    ],
    'columns' => [
        ['label' => 'Event', 'sort' => 'title', 'value' => fn($r) => e($r['title']) . '<span class="sub">' . e($r['category_name'] ?: 'Uncategorized') . ', ' . ($r['visibility'] === 'private' ? 'private' : 'public') . '</span>'],
        ['label' => 'Schedule', 'sort' => 'start', 'value' => fn($r) => e(fmt_datetime($r['starts_at'])) . '<span class="sub">' . e($r['location_name']) . '</span>'],
        ['label' => 'Registrations', 'class' => 'num', 'value' => fn($r) => $r['registration_enabled'] ? fmt_num($r['reg_count']) . ($r['registration_limit'] ? ' of ' . fmt_num($r['registration_limit']) : '') : '<span class="muted">Off</span>'],
        ['label' => 'Event status', 'value' => fn($r) => status_text($r['event_status'])],
        ['label' => 'Publishing', 'value' => fn($r) => status_text($r['status'])],
    ],
    'fields' => [
        'title' => ['label' => 'Event title', 'required' => true, 'max' => 191, 'width' => 'full'],
        'slug' => ['label' => 'Web address (slug)', 'max' => 160, 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'pattern_message' => 'Use lowercase letters, numbers and hyphens only.', 'help' => 'Leave blank to create one from the title.'],
        'category_id' => ['label' => 'Category', 'type' => 'lookup_sql', 'options_sql' => "SELECT id, name FROM content_categories WHERE scope = 'event' AND is_active = 1 ORDER BY sort_order"],
        'summary' => ['label' => 'Summary', 'type' => 'textarea', 'max' => 500, 'rows' => 3],
        'description' => ['label' => 'Description', 'type' => 'richtext'],
        'starts_at' => ['label' => 'Starts', 'type' => 'datetime', 'required' => true],
        'ends_at' => ['label' => 'Ends', 'type' => 'datetime'],
        'location_name' => ['label' => 'Venue or platform', 'max' => 191],
        'venue_address' => ['label' => 'Venue address', 'max' => 255],
        'region_id' => ['label' => 'Region', 'type' => 'region'],
        'organizer' => ['label' => 'Organizer', 'max' => 191],
        'contact_email' => ['label' => 'Contact email', 'type' => 'email'],
        'cover_media_id' => ['label' => 'Featured image', 'type' => 'media', 'kind' => 'image', 'visibility' => 'public'],
        'visibility' => ['label' => 'Visibility', 'type' => 'select', 'options' => ['public' => 'Public event', 'private' => 'Private organizational event (signed-in users only)'], 'required' => true, 'default' => 'public'],
        'event_status' => ['label' => 'Event status', 'type' => 'select', 'options' => $statusOpts, 'required' => true, 'default' => 'scheduled'],
        'is_featured' => ['label' => 'Feature on the home page', 'type' => 'bool'],
        'registration_enabled' => ['label' => 'Accept registrations', 'type' => 'bool'],
        'registration_limit' => ['label' => 'Registration limit', 'type' => 'number', 'min' => 1, 'help' => 'Leave blank for no limit. Extra registrations go to a waiting list.'],
        'registration_deadline' => ['label' => 'Registration deadline', 'type' => 'datetime'],
        'publish_at' => ['label' => 'Publish on', 'type' => 'datetime'],
    ],
    'sections' => [
        'Event' => ['title', 'slug', 'category_id', 'summary', 'description', 'cover_media_id'],
        'Schedule and venue' => ['starts_at', 'ends_at', 'location_name', 'venue_address', 'region_id', 'organizer', 'contact_email'],
        'Visibility and registration' => ['visibility', 'event_status', 'is_featured', 'registration_enabled', 'registration_limit', 'registration_deadline', 'publish_at'],
    ],
    'public_url' => fn($r) => url('event.php', ['slug' => $r['slug']]),
    'validate' => function (array &$data, ?array $old, array &$errors) {
        unique_slug('events', $data, $old, $errors);
        if (!empty($data['ends_at']) && !empty($data['starts_at']) && $data['ends_at'] < $data['starts_at']) {
            $errors['ends_at'] = 'The end must be after the start.';
        }
        if (!empty($data['registration_deadline']) && !empty($data['starts_at']) && $data['registration_deadline'] > $data['starts_at']) {
            $errors['registration_deadline'] = 'The registration deadline should be before the event starts.';
        }
    },
    'view_panels' => function (array $row) {
        $counts = q_pairs('SELECT registration_status, COUNT(*) FROM event_registrations WHERE event_id = ? GROUP BY registration_status', [$row['id']]);
        echo '<section class="panel"><div class="panel-head"><h2 class="panel-title">Registrations</h2>' . (can('events.registrations') ? '<a class="btn btn-secondary btn-sm" href="' . e(url('admin/event-registrations.php', ['event' => $row['id']])) . '">Manage registrations</a>' : '') . '</div><div class="stat-row compact">';
        foreach (['registered' => 'Registered', 'waitlisted' => 'Waiting list', 'attended' => 'Attended', 'cancelled' => 'Cancelled'] as $k => $l) {
            echo '<div class="stat"><span class="stat-num">' . fmt_num($counts[$k] ?? 0) . '</span><span class="stat-label">' . $l . '</span></div>';
        }
        echo '</div></section>';
        $posts = q_all('SELECT id, title, status, created_at FROM posts WHERE event_id = ? AND archived_at IS NULL ORDER BY created_at DESC', [$row['id']]);
        related_table('Announcements and updates for this event', ['Title', 'Status', 'Created'], array_map(fn($p) => ['<a href="' . e(url('admin/posts.php', ['action' => 'view', 'id' => $p['id']])) . '">' . e($p['title']) . '</a>', status_text($p['status']), e(fmt_date($p['created_at']))], $posts),
            'No updates posted.', can('content.create') ? url('admin/posts.php', ['type' => 'announcement', 'action' => 'create', 'event_id' => $row['id']]) : '', 'Post an update');
    },
];
