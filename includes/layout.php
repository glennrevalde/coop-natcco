<?php
/**
 * Page layouts for the administrative system and the public website.
 */
declare(strict_types=1);

function admin_nav(): array
{
    $pending = 0;
    if (can('approvals.view')) {
        $pending = approvals_pending_count();
    }
    return [
        'Overview' => [
            ['Dashboard', 'admin/index.php', 'dashboard.view', 'home'],
            ['Approvals', 'admin/approvals.php', 'approvals.view', 'check', $pending],
            ['Reports', 'admin/reports.php', 'reports.view', 'chart'],
        ],
        'National database' => [
            ['Cooperatives', 'admin/cooperatives.php', 'cooperatives.view', 'building'],
            ['Cooperative officers and staff', 'admin/personnel.php', 'personnel.view', 'users'],
            ['Regular members', 'admin/members.php', 'members.view', 'id'],
            ['CNPL point persons', 'admin/point-persons.php', 'point_persons.view', 'pin'],
            ['Point-person tasks', 'admin/tasks.php', 'point_persons.view', 'list'],
            ['Ambassadors and focal persons', 'admin/ambassadors.php', 'ambassadors.view', 'flag'],
            ['CNPL officers', 'admin/officers.php', 'officers.view', 'star'],
            ['Officer appointments', 'admin/appointments.php', 'officers.view', 'calendar'],
            ['Activities', 'admin/activities.php', 'activities.view', 'activity'],
            ['People directory', 'admin/persons.php', 'persons.view', 'user'],
        ],
        'Elections' => [
            ['Election performance', 'admin/election-performance.php', 'elections.view', 'chart'],
            ['Election results', 'admin/election-results.php', 'elections.view', 'table'],
            ['Election years', 'admin/election-years.php', 'elections.edit', 'calendar'],
            ['Official sources', 'admin/election-sources.php', 'elections.view', 'doc'],
        ],
        'Website' => [
            ['News and updates', 'admin/posts.php', 'content.view', 'news'],
            ['Pages', 'admin/pages.php', 'content.view', 'doc'],
            ['Events', 'admin/events.php', 'events.view', 'calendar'],
            ['Legislative updates', 'admin/legislation.php', 'content.view', 'scale'],
            ['Awards and recognition', 'admin/awards.php', 'content.view', 'star'],
            ['Policies and procedures', 'admin/policies.php', 'content.view', 'doc'],
            ['History milestones', 'admin/history.php', 'content.view', 'clock'],
            ['Photo galleries', 'admin/galleries.php', 'content.view', 'image'],
            ['FAQs', 'admin/faqs.php', 'content.view', 'help'],
            ['Media library', 'admin/media.php', 'media.view', 'image'],
            ['Menus', 'admin/menus.php', 'menus.manage', 'list'],
            ['Contact messages', 'admin/messages.php', 'messages.view', 'mail'],
        ],
        'Help desk' => [
            ['Tickets', 'admin/helpdesk.php', ['helpdesk.view', 'helpdesk.view_all', 'helpdesk.submit'], 'help'],
        ],
        'Administration' => [
            ['Users', 'admin/users.php', 'users.view', 'users'],
            ['Roles and permissions', 'admin/roles.php', 'roles.manage', 'key'],
            ['Organizational structure', 'admin/org-units.php', 'structure.manage', 'sitemap'],
            ['Positions', 'admin/positions.php', 'structure.manage', 'list'],
            ['Geographic data', 'admin/geo.php', 'geo.view', 'map'],
            ['Reference lists', 'admin/lookups.php', 'settings.manage', 'list'],
            ['Settings', 'admin/settings.php', 'settings.view', 'gear'],
        ],
        'Security and privacy' => [
            ['Security monitoring', 'admin/security.php', 'security.view', 'shield'],
            ['Incidents', 'admin/incidents.php', 'security.view', 'alert'],
            ['Audit log', 'admin/audit.php', 'audit.view', 'list'],
            ['Backups', 'admin/backups.php', 'backups.view', 'db'],
            ['Privacy requests', 'admin/privacy-requests.php', 'privacy.view', 'lock'],
            ['Records of processing', 'admin/processing.php', 'privacy.view', 'doc'],
        ],
    ];
}

function approvals_pending_count(): int
{
    static $n = null;
    if ($n !== null) {
        return $n;
    }
    $n = 0;
    foreach (approval_sources() as $key => $src) {
        if (!can($src['perm'])) {
            continue;
        }
        $p = [];
        $where = $src['where']($p);
        $n += (int) q_val("SELECT COUNT(*) FROM {$src['from']} WHERE {$where}", $p);
    }
    return $n;
}

/** Sources feeding the approval inbox. */
function approval_sources(): array
{
    $reg = function (string $table, string $alias, array $scope) {
        return function (array &$p) use ($alias, $scope) {
            return "{$alias}.review_status = 'pending' AND {$alias}.archived_at IS NULL AND " . scope_sql($scope, $p);
        };
    };
    return [
        'cooperatives' => ['label' => 'Cooperatives', 'perm' => 'cooperatives.approve', 'page' => 'admin/cooperatives.php', 'from' => 'cooperatives c', 'title' => 'c.name', 'id_col' => 'c.id',
            'where' => $reg('cooperatives', 'c', ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'])],
        'personnel' => ['label' => 'Cooperative officers and staff', 'perm' => 'personnel.approve', 'page' => 'admin/personnel.php', 'from' => 'cooperative_personnel x JOIN cooperatives c ON c.id = x.cooperative_id JOIN persons pe ON pe.id = x.person_id', 'title' => "CONCAT(pe.first_name, ' ', pe.last_name, ' - ', c.name)",
            'where' => $reg('cooperative_personnel', 'x', ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'])],
        'members' => ['label' => 'Regular members', 'perm' => 'members.approve', 'page' => 'admin/members.php', 'from' => 'members x JOIN cooperatives c ON c.id = x.cooperative_id', 'title' => "CONCAT(x.member_ref, ' - ', c.name)",
            'where' => $reg('members', 'x', ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'])],
        'point_persons' => ['label' => 'CNPL point persons', 'perm' => 'point_persons.approve', 'page' => 'admin/point-persons.php', 'from' => 'cnpl_point_persons x JOIN cooperatives c ON c.id = x.cooperative_id JOIN persons pe ON pe.id = x.person_id', 'title' => "CONCAT(pe.first_name, ' ', pe.last_name, ' - ', c.name)",
            'where' => $reg('cnpl_point_persons', 'x', ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'])],
        'ambassadors' => ['label' => 'Ambassadors and focal persons', 'perm' => 'ambassadors.approve', 'page' => 'admin/ambassadors.php', 'from' => 'ambassadors_focal_persons x JOIN persons pe ON pe.id = x.person_id', 'title' => "CONCAT(pe.first_name, ' ', pe.last_name, ' - ', x.designation_title)",
            'where' => $reg('ambassadors_focal_persons', 'x', ['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id', 'coop' => 'x.cooperative_id'])],
        'officers' => ['label' => 'CNPL officers', 'perm' => 'officers.approve', 'page' => 'admin/officers.php', 'from' => 'officers x JOIN persons pe ON pe.id = x.person_id', 'title' => "CONCAT(pe.first_name, ' ', pe.last_name)",
            'where' => $reg('officers', 'x', ['region' => 'x.region_id'])],
        'activities' => ['label' => 'Activities', 'perm' => 'activities.approve', 'page' => 'admin/activities.php', 'from' => 'activities x', 'title' => 'x.title',
            'where' => $reg('activities', 'x', ['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id', 'coop' => 'x.cooperative_id'])],
        'election_results' => ['label' => 'Election results to verify', 'perm' => 'elections.verify', 'page' => 'admin/election-results.php', 'from' => 'election_results x JOIN election_years y ON y.id = x.election_year_id', 'title' => "CONCAT(y.election_year, ' ', x.geo_level, ' result #', x.id)",
            'where' => function (array &$p) {
                return "x.verification_status = 'pending' AND x.archived_at IS NULL AND " . scope_sql(['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id'], $p);
            }],
        'content' => ['label' => 'Website content in review', 'perm' => 'content.publish', 'page' => null, 'from' => '(SELECT id, title, \'posts\' AS src FROM posts WHERE status = \'in_review\' AND archived_at IS NULL UNION ALL SELECT id, title, \'pages\' FROM pages WHERE status = \'in_review\' AND archived_at IS NULL UNION ALL SELECT id, title, \'legislation\' FROM legislative_updates WHERE status = \'in_review\' AND archived_at IS NULL UNION ALL SELECT id, title, \'awards\' FROM awards WHERE status = \'in_review\' AND archived_at IS NULL UNION ALL SELECT id, title, \'policies\' FROM policies WHERE status = \'in_review\' AND archived_at IS NULL UNION ALL SELECT id, title, \'history\' FROM history_milestones WHERE status = \'in_review\' AND archived_at IS NULL UNION ALL SELECT id, title, \'galleries\' FROM galleries WHERE status = \'in_review\' AND archived_at IS NULL UNION ALL SELECT id, question, \'faqs\' FROM faqs WHERE status = \'in_review\' AND archived_at IS NULL) x', 'title' => 'x.title',
            'where' => function (array &$p) {
                return '1=1';
            }],
        'events' => ['label' => 'Events in review', 'perm' => 'events.publish', 'page' => 'admin/events.php', 'from' => 'events x', 'title' => 'x.title',
            'where' => function (array &$p) {
                return "x.status = 'in_review' AND x.archived_at IS NULL";
            }],
        'imports' => ['label' => 'Import batches', 'perm' => 'imports.approve', 'page' => null, 'from' => 'import_batches x', 'title' => "CONCAT(x.module, ': ', x.original_name)",
            'where' => function (array &$p) {
                return "x.batch_status = 'pending_approval'";
            }],
        'roles' => ['label' => 'Role assignments', 'perm' => 'users.approve_roles', 'page' => 'admin/users.php', 'id_col' => 'x.user_id', 'from' => 'user_roles x JOIN users u ON u.id = x.user_id JOIN roles r ON r.id = x.role_id', 'title' => "CONCAT(u.full_name, ' as ', r.name)",
            'where' => function (array &$p) {
                return "x.assignment_status = 'pending'";
            }],
    ];
}

function nav_is_active(string $href): bool
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return basename($href) === $script;
}

function icon(string $name): string
{
    $paths = [
        'home' => 'M3 10.5 12 3l9 7.5M5 9.5V21h5v-6h4v6h5V9.5',
        'check' => 'M4 12.5l5 5L20 6.5',
        'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'building' => 'M4 21V5l8-3 8 3v16M9 21v-5h6v5M8 8h2M14 8h2M8 12h2M14 12h2',
        'users' => 'M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 10.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6M20 19v-1.5a3.5 3.5 0 0 0-2.5-3.3M15.5 4.6a3 3 0 0 1 0 5.8',
        'id' => 'M3 5h18v14H3zM7 15c.5-1.7 1.6-2.5 3-2.5s2.5.8 3 2.5M10 11a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6M15 9h3M15 12h3',
        'pin' => 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5',
        'list' => 'M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01',
        'flag' => 'M5 21V4M5 4h11l-2 4 2 4H5',
        'star' => 'M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z',
        'calendar' => 'M4 6h16v15H4zM4 10h16M8 3v4M16 3v4',
        'activity' => 'M3 12h4l3-8 4 16 3-8h4',
        'user' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
        'table' => 'M3 5h18v14H3zM3 10h18M3 15h18M9 5v14',
        'doc' => 'M6 3h8l4 4v14H6zM14 3v4h4M9 12h6M9 16h6',
        'news' => 'M4 5h13v14H6a2 2 0 0 1-2-2zM17 9h3v8a2 2 0 0 1-2 2M8 9h5M8 13h5',
        'scale' => 'M12 4v16M5 20h14M6 8l-3 6a3 3 0 0 0 6 0zM18 8l-3 6a3 3 0 0 0 6 0zM6 8h12',
        'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
        'image' => 'M4 5h16v14H4zM4 15l4-4 5 5 3-3 4 4M15 9.5a1.5 1.5 0 1 0 0-.01',
        'help' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.5V14M12 17.5h.01',
        'mail' => 'M3 6h18v12H3zM3 7l9 6 9-6',
        'key' => 'M14.5 9.5a4.5 4.5 0 1 0-4 4.47L10 14l-1 1H7v2H5v2H3v-3l6.6-6.6',
        'sitemap' => 'M10 3h4v4h-4zM3 17h4v4H3zM10 17h4v4h-4zM17 17h4v4h-4zM12 7v4M5 17v-3h14v3M12 11v6',
        'map' => 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2zM9 4v14M15 6v14',
        'gear' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 2.7-1.1V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1.3z',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
        'alert' => 'M12 4 2.5 20h19zM12 10v4M12 17h.01',
        'db' => 'M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zM4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
        'lock' => 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
        'bell' => 'M6 16V11a6 6 0 1 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0',
        'menu' => 'M4 7h16M4 12h16M4 17h16',
        'x' => 'M6 6l12 12M18 6 6 18',
        'out' => 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10',
        'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3c2.5 2.7 3.5 5.7 3.5 9s-1 6.3-3.5 9c-2.5-2.7-3.5-5.7-3.5-9s1-6.3 3.5-9',
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-4-4',
        'phone' => 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2',
        'arrow' => 'M5 12h14M13 6l6 6-6 6',
        'download' => 'M12 4v11M7 10l5 5 5-5M5 20h14',
        'install' => 'M8 2.5h8a2 2 0 0 1 2 2v15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-15a2 2 0 0 1 2-2zM12 6.5v7M9 10.5l3 3 3-3M10.5 18.5h3',
    ];
    $d = $paths[$name] ?? $paths['doc'];
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
}

// ------------------------------------------------------------ installable app (PWA)
/** Manifest, icons, theme color and iOS tags for every page head. */
function pwa_head(): string
{
    $short = app_short_name();
    return '<link rel="manifest" href="' . e(url('manifest.php')) . '">'
        . '<link rel="icon" type="image/png" sizes="32x32" href="' . e(asset('icons/favicon-32.png')) . '">'
        . '<link rel="apple-touch-icon" sizes="180x180" href="' . e(asset('icons/apple-touch-icon.png')) . '">'
        . '<meta name="theme-color" content="' . PWA_THEME_COLOR . '">'
        . '<meta name="application-name" content="' . e($short) . '">'
        . '<meta name="mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-title" content="' . e($short) . '">'
        . '<meta name="apple-mobile-web-app-status-bar-style" content="default">';
}

/** "Install app" button. Hidden by CSS and pwa.js inside the installed app. */
function pwa_install_button(string $class, string $label = 'Install app'): string
{
    return '<button type="button" class="' . e($class) . '" data-pwa-install aria-haspopup="dialog" aria-controls="pwaDialog">'
        . icon('install') . '<span class="pwa-label">' . e($label) . '</span></button>';
}

/** Install instructions for browsers that do not offer the install prompt themselves. */
function pwa_install_dialog(): string
{
    $name = site_name();
    $steps = [
        'insecure' => ['Installing needs a secure address', [
            'Browsers offer installation only on <strong>https://</strong> addresses, or on <strong>http://localhost</strong> on the computer that runs the system.',
            'This page was opened over plain http from another device, so the install is not available here.',
            'Ask your administrator for the https address of the system, open it, and try again.']],
        'ios' => ['iPhone and iPad', [
            'Tap the <strong>Share</strong> button (the square with an arrow) in the browser toolbar.',
            'Scroll down and tap <strong>Add to Home Screen</strong>.',
            'Tap <strong>Add</strong>. The app icon appears on your Home Screen.']],
        'android-chrome' => ['Chrome on Android', [
            'Tap the <strong>⋮</strong> menu at the top right.',
            'Tap <strong>Install app</strong> or <strong>Add to Home screen</strong>.',
            'Tap <strong>Install</strong>.']],
        'android-samsung' => ['Samsung Internet', [
            'Tap the <strong>install icon</strong> in the address bar if it is shown, or tap the <strong>≡</strong> menu.',
            'Tap <strong>Add page to</strong>, then <strong>Home screen</strong>.',
            'Tap <strong>Add</strong>.']],
        'android-firefox' => ['Firefox on Android', [
            'Tap the <strong>⋮</strong> menu.',
            'Tap <strong>Add to Home screen</strong> (or <strong>Add app to Home screen</strong>).',
            'Tap <strong>Add</strong>.']],
        'chrome' => ['Chrome on a computer', [
            'Click the <strong>install icon</strong> at the right end of the address bar (a screen with a down arrow).',
            'Or open the <strong>⋮</strong> menu, then <strong>Cast, save, and share</strong>, then <strong>Install page as app</strong>.',
            'Click <strong>Install</strong>. The app opens in its own window and is added to your Start menu, Dock or app launcher.']],
        'edge' => ['Microsoft Edge', [
            'Click the <strong>App available</strong> icon in the address bar.',
            'Or open the <strong>…</strong> menu, then <strong>Apps</strong>, then <strong>Install this site as an app</strong>.',
            'Click <strong>Install</strong>.']],
        'safari-mac' => ['Safari on a Mac', [
            'Open the <strong>File</strong> menu (or the <strong>Share</strong> button).',
            'Choose <strong>Add to Dock</strong>.',
            'Click <strong>Add</strong>. Needs macOS Sonoma or later.']],
        'firefox' => ['Firefox on a computer', [
            'Firefox on Windows, Mac and Linux does not install sites as apps.',
            'Open this page in <strong>Chrome</strong> or <strong>Edge</strong> and use <strong>Install app</strong> there.']],
        'other' => ['Other browsers', [
            'Open the browser menu.',
            'Look for <strong>Install app</strong>, <strong>Add to Home screen</strong> or <strong>Add to Dock</strong>.']],
    ];
    $html = '<dialog class="pwa-dialog" id="pwaDialog" aria-labelledby="pwaTitle"><div class="pwa-dialog-body"><div class="pwa-dialog-head">'
        . '<img src="' . e(asset('icons/icon-192.png')) . '" alt="" width="56" height="56">'
        . '<div><h2 id="pwaTitle">Install the app</h2><p>Open ' . e($name) . ' from an icon on your phone or computer, full screen and without the browser address bar.</p></div></div>'
        . '<p class="pwa-installed-note" hidden>The app is installed. Open it from your Home Screen, Start menu, Dock or app launcher.</p>'
        . '<div class="pwa-steps">';
    foreach ($steps as $key => [$title, $list]) {
        $html .= '<section class="pwa-step" data-pwa-steps="' . $key . '" hidden><h3>' . e($title) . '</h3><ol>';
        foreach ($list as $li) {
            $html .= '<li>' . $li . '</li>';
        }
        $html .= '</ol></section>';
    }
    $html .= '</div><details class="pwa-more"><summary>Steps for other browsers</summary><div class="pwa-more-list"></div></details>'
        . '<p class="pwa-note">The app shows the same live system. Nothing is stored on the device except the design files, so you still need an internet connection.</p>'
        . '<form method="dialog" class="btn-row"><button class="btn btn-primary" value="close">Close</button></form></div></dialog>';
    return $html;
}

function admin_start(string $title, array $crumbs = [], array $opts = []): void
{
    $u = current_user();
    $GLOBALS['ADMIN_LAYOUT_OPEN'] = true;
    no_cache_headers();
    $roles = $u ? user_role_rows((int) $u['id']) : [];
    $roleLabel = $roles ? $roles[0]['name'] : 'No role assigned';
    $unread = unread_notifications();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' | ' . e(site_name()) . ' Admin</title><meta name="robots" content="noindex, nofollow">'
        . '<meta name="csrf-token" content="' . e(csrf_token()) . '"><meta name="base-url" content="' . e(base_path()) . '">'
        . pwa_head()
        . '<link rel="preload" href="' . e(asset('fonts/public-sans-latin-400-normal.woff2')) . '" as="font" type="font/woff2" crossorigin>'
        . '<link rel="stylesheet" href="' . e(asset('css/admin.css')) . '"></head><body class="admin">';
    echo '<a class="skip-link" href="#main">Skip to main content</a>';
    echo '<div class="shell"><aside class="sidebar" id="sidebar" aria-label="Main navigation"><div class="sb-brand"><a href="' . e(url('admin/index.php')) . '"><img src="' . e(logo_url()) . '" alt="' . e(site_name()) . '" width="96" height="56"></a>'
        . '<div class="sb-brand-text"><strong>National Database</strong><span>Management System</span></div>'
        . '<button class="sb-close" type="button" data-sidebar-close aria-label="Close menu">' . icon('x') . '</button></div><nav class="sb-nav">';
    foreach (admin_nav() as $group => $items) {
        $visible = array_filter($items, fn($i) => is_array($i[2]) ? can_any($i[2]) : can($i[2]));
        if (!$visible) {
            continue;
        }
        echo '<div class="sb-group"><p class="sb-group-label">' . e($group) . '</p><ul>';
        foreach ($visible as $i) {
            $active = nav_is_active($i[1]);
            $count = $i[4] ?? 0;
            echo '<li><a href="' . e(url($i[1])) . '"' . ($active ? ' aria-current="page" class="active"' : '') . '>' . icon($i[3]) . '<span class="sb-label">' . e($i[0]) . '</span>'
                . ($count ? '<span class="sb-count" aria-label="' . (int) $count . ' pending">' . (int) $count . '</span>' : '') . '</a></li>';
        }
        echo '</ul></div>';
    }
    echo '</nav><div class="sb-foot"><a href="' . e(url('index.php')) . '" target="_blank" rel="noopener">' . icon('globe') . '<span>View public website</span></a></div></aside>';
    echo '<div class="sb-backdrop" data-sidebar-close hidden></div>';
    echo '<div class="main-col"><header class="topbar"><button class="tb-menu" type="button" data-sidebar-open aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">' . icon('menu') . '</button>';
    if ($crumbs) {
        echo '<nav class="crumbs" aria-label="Breadcrumb"><ol><li><a href="' . e(url('admin/index.php')) . '">Dashboard</a></li>';
        foreach ($crumbs as $c) {
            echo $c[1] ? '<li><a href="' . e($c[1]) . '">' . e($c[0]) . '</a></li>' : '<li aria-current="page">' . e($c[0]) . '</li>';
        }
        echo '</ol></nav>';
    } else {
        echo '<span class="crumbs"></span>';
    }
    echo '<div class="tb-right">' . pwa_install_button('tb-install') . '<a class="tb-bell" href="' . e(url('admin/notifications.php')) . '" aria-label="Notifications' . ($unread ? ', ' . $unread . ' unread' : '') . '">' . icon('bell') . ($unread ? '<span class="tb-dot">' . ($unread > 99 ? '99+' : $unread) . '</span>' : '') . '</a>';
    if ($u) {
        echo '<details class="tb-user"><summary><span class="avatar" aria-hidden="true">' . e(mb_strtoupper(mb_substr($u['full_name'], 0, 1))) . '</span><span class="tb-user-text"><strong>' . e($u['full_name']) . '</strong><span>' . e($roleLabel) . '</span></span></summary>'
            . '<div class="tb-menu-pop"><p class="muted small">Data scope: ' . e(scope_description()) . '</p><a href="' . e(url('account/profile.php')) . '">My account and security</a><a href="' . e(url('admin/notifications.php')) . '">Notifications</a>'
            . '<form method="post" action="' . e(url('account/logout.php')) . '">' . csrf_field() . '<button type="submit">' . icon('out') . ' Sign out</button></form></div></details>';
    }
    echo '</div></header>';
    if (!is_production() && strtolower((string) cfg('db.user')) === 'root' && can('settings.security')) {
        echo '<div class="dev-banner" role="note">Development mode: the database account is root. Use a least-privilege account before going live (see docs/INSTALLATION.md).</div>';
    }
    echo '<main id="main" class="content" tabindex="-1">';
}

function page_bar(string $title, string $sub = '', string $actions = ''): void
{
    echo '<div class="page-bar"><div class="pb-text"><h1>' . e($title) . '</h1>' . ($sub !== '' ? '<p>' . $sub . '</p>' : '') . '</div>'
        . ($actions !== '' ? '<div class="pb-actions">' . $actions . '</div>' : '') . '</div>';
}

function admin_end(): void
{
    echo '</main><footer class="admin-foot"><span>' . e(site_name()) . ' National Database Management System</span><span>Signed-in activity is recorded in the audit log.</span></footer></div></div>';
    echo '<dialog class="confirm-dialog" id="confirmDialog" aria-labelledby="confirmTitle"><form method="dialog"><h2 id="confirmTitle">Please confirm</h2><p id="confirmText"></p>'
        . '<div class="cd-remarks" hidden><label for="confirmRemarks">Remarks</label><input type="text" id="confirmRemarks" maxlength="255"></div>'
        . '<div class="btn-row"><button class="btn btn-ghost" value="cancel">Cancel</button><button class="btn btn-primary" value="ok" id="confirmOk">Continue</button></div></form></dialog>';
    echo pwa_install_dialog();
    echo '<script src="' . e(asset('js/admin.js')) . '" defer></script><script src="' . e(asset('js/pwa.js')) . '" defer></script></body></html>';
}

// ------------------------------------------------------------- public site
function public_menu(string $location = 'main'): array
{
    $rows = q_all('SELECT * FROM menu_items WHERE menu_location = ? AND is_active = 1 ORDER BY sort_order, id', [$location]);
    $tree = [];
    $children = [];
    foreach ($rows as $r) {
        if ($r['parent_id']) {
            $children[(int) $r['parent_id']][] = $r;
        } else {
            $tree[] = $r;
        }
    }
    foreach ($tree as &$t) {
        $t['children'] = $children[(int) $t['id']] ?? [];
    }
    return $tree;
}

function menu_href(string $u): string
{
    return preg_match('#^https?://#i', $u) ? $u : url($u);
}

function public_start(string $title, array $opts = []): void
{
    $desc = $opts['description'] ?? setting('site_description', '');
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $isHome = $script === 'index.php' && empty($opts['not_home']);
    $full = $isHome ? site_name() . ' | ' . setting('site_tagline', 'Official Website') : $title . ' | ' . site_name();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($full) . '</title><meta name="description" content="' . e(excerpt($desc, 160)) . '">'
        . '<meta property="og:title" content="' . e($full) . '"><meta property="og:description" content="' . e(excerpt($desc, 160)) . '"><meta property="og:type" content="website">'
        . (!empty($opts['image']) ? '<meta property="og:image" content="' . e(abs_url(ltrim(substr($opts['image'], strlen(base_path())), '/'))) . '">' : '')
        . '<meta name="base-url" content="' . e(base_path()) . '">'
        . pwa_head()
        . '<link rel="preload" href="' . e(asset('fonts/public-sans-latin-400-normal.woff2')) . '" as="font" type="font/woff2" crossorigin>'
        . '<link rel="preload" href="' . e(asset('fonts/barlow-semi-condensed-latin-700-normal.woff2')) . '" as="font" type="font/woff2" crossorigin>'
        . '<link rel="stylesheet" href="' . e(asset('css/site.css')) . '"></head><body class="site' . ($isHome ? ' is-home' : '') . '">';
    echo '<a class="skip-link" href="#main">Skip to main content</a>';
    $demo = setting('demo_notice');
    if ($demo !== '') {
        echo '<div class="demo-notice" role="note">' . e($demo) . '</div>';
    }
    $phone = setting('contact_phone');
    $email = setting('contact_email');
    echo '<div class="utility"><div class="wrap utility-in"><div class="u-contact">'
        . ($phone ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $phone)) . '">' . icon('phone') . e($phone) . '</a>' : '')
        . ($email ? '<a href="mailto:' . e($email) . '">' . icon('mail') . e($email) . '</a>' : '')
        . '</div><div class="u-links"><a href="' . e(url('helpdesk-track.php')) . '">Track a ticket</a><a href="' . e(url('search.php')) . '">' . icon('search') . 'Search</a>'
        . (current_user() ? '<a href="' . e(url('admin/index.php')) . '">Dashboard</a>' : '<a href="' . e(url('account/login.php')) . '">Staff sign in</a>') . '</div></div></div>';
    echo '<header class="masthead"><div class="wrap mh-in"><a class="brand" href="' . e(url('index.php')) . '"><img src="' . e(logo_url()) . '" alt="' . e(site_name()) . ' home" width="110" height="64"></a>'
        . '<button class="nav-toggle" type="button" aria-controls="siteNav" aria-expanded="false">' . icon('menu') . '<span>Menu</span></button>'
        . '<nav class="site-nav" id="siteNav" aria-label="Main"><ul class="nav-list">';
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    foreach (public_menu('main') as $i => $item) {
        $active = basename(strtok($item['url'], '?')) === $current || in_array($current, array_map(fn($c) => basename(strtok($c['url'], '?')), $item['children']), true);
        if ($item['children']) {
            echo '<li class="has-sub' . ($active ? ' active' : '') . '"><button type="button" class="sub-toggle" aria-expanded="false" aria-controls="sub' . $i . '">' . e($item['label']) . '<span class="chev" aria-hidden="true"></span></button><ul class="sub" id="sub' . $i . '">';
            foreach ($item['children'] as $c) {
                echo '<li><a href="' . e(menu_href($c['url'])) . '"' . (basename(strtok($c['url'], '?')) === $current ? ' aria-current="page"' : '') . ($c['opens_new_tab'] ? ' target="_blank" rel="noopener"' : '') . '>' . e($c['label']) . '</a></li>';
            }
            echo '</ul></li>';
        } else {
            echo '<li' . ($active ? ' class="active"' : '') . '><a href="' . e(menu_href($item['url'])) . '"' . ($active ? ' aria-current="page"' : '') . ($item['opens_new_tab'] ? ' target="_blank" rel="noopener"' : '') . '>' . e($item['label']) . '</a></li>';
        }
    }
    echo '</ul></nav></div><div class="brand-stripe" aria-hidden="true"></div></header>';
    echo '<main id="main" tabindex="-1">';
    if (empty($opts['no_bar'])) {
        echo '<div class="page-bar"><div class="wrap">';
        if (!empty($opts['crumbs'])) {
            echo '<nav class="crumbs" aria-label="Breadcrumb"><ol><li><a href="' . e(url('index.php')) . '">Home</a></li>';
            foreach ($opts['crumbs'] as $c) {
                echo !empty($c[1]) ? '<li><a href="' . e($c[1]) . '">' . e($c[0]) . '</a></li>' : '<li aria-current="page">' . e($c[0]) . '</li>';
            }
            echo '</ol></nav>';
        }
        echo '<h1>' . e($opts['h1'] ?? $title) . '</h1>' . (!empty($opts['sub']) ? '<p class="pb-sub">' . $opts['sub'] . '</p>' : '') . '</div></div>';
    }
    if (!empty($_GET['preview']) && current_user()) {
        echo '<div class="wrap"><div class="notice notice-info" role="status">Preview mode: unpublished content is visible to you because you are signed in.</div></div>';
    }
}

function public_end(): void
{
    $social = [];
    foreach (['facebook' => 'Facebook', 'youtube' => 'YouTube', 'x' => 'X', 'instagram' => 'Instagram', 'tiktok' => 'TikTok'] as $k => $l) {
        $v = setting('social_' . $k);
        if ($v !== '' && preg_match('#^https://#', $v)) {
            $social[] = '<a href="' . e($v) . '" target="_blank" rel="noopener noreferrer">' . e($l) . '</a>';
        }
    }
    echo '</main><footer class="site-foot"><div class="brand-stripe" aria-hidden="true"></div><div class="wrap foot-grid">';
    echo '<div class="foot-about"><img src="' . e(logo_url()) . '" alt="" width="120" height="70" loading="lazy">'
        . (setting('footer_about') !== '' ? '<p>' . e(setting('footer_about')) . '</p>' : '')
        . ($social ? '<p class="foot-social">' . implode('', $social) . '</p>' : '') . '</div>';
    echo '<div><h2>Contact</h2><ul class="foot-list">';
    $any = false;
    foreach (['contact_address' => '', 'contact_phone' => 'tel', 'contact_email' => 'mailto', 'office_hours' => ''] as $k => $proto) {
        $v = setting($k);
        if ($v === '') {
            continue;
        }
        $any = true;
        echo '<li>' . ($proto ? '<a href="' . $proto . ':' . e($proto === 'tel' ? preg_replace('/[^0-9+]/', '', $v) : $v) . '">' . e($v) . '</a>' : nl2br(e($v))) . '</li>';
    }
    if (!$any) {
        echo '<li><a href="' . e(url('contact.php')) . '">Send us a message</a></li>';
    }
    echo '</ul></div><div><h2>Services</h2><ul class="foot-list"><li><a href="' . e(url('helpdesk.php')) . '">Help desk for primary cooperatives</a></li><li><a href="' . e(url('helpdesk-track.php')) . '">Track a ticket</a></li><li><a href="' . e(url('events.php')) . '">Upcoming events</a></li><li><a href="' . e(url('publications.php')) . '">Publications and downloads</a></li></ul></div>';
    echo '<div><h2>Information</h2><ul class="foot-list">';
    foreach (public_menu('footer') as $f) {
        echo '<li><a href="' . e(menu_href($f['url'])) . '">' . e($f['label']) . '</a></li>';
    }
    echo '</ul></div></div><div class="wrap foot-bottom"><p>&copy; ' . date('Y') . ' ' . e(site_name()) . '. ' . e(setting('footer_note', 'All rights reserved.')) . '</p></div></footer>';
    echo '<script src="' . e(asset('js/site.js')) . '" defer></script><script src="' . e(asset('js/pwa.js')) . '" defer></script></body></html>';
}

// ------------------------------------------------------------ sign-in pages
function auth_start(string $title, bool $wide = false): void
{
    no_cache_headers();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' | ' . e(site_name()) . '</title><meta name="robots" content="noindex">'
        . '<meta name="csrf-token" content="' . e(csrf_token()) . '"><meta name="base-url" content="' . e(base_path()) . '">'
        . pwa_head()
        . '<link rel="stylesheet" href="' . e(asset('css/admin.css')) . '"></head><body class="auth">';
    echo '<main id="main"><div class="auth-card' . ($wide ? ' wide' : '') . '"><div class="auth-head"><a href="' . e(url('index.php')) . '"><img src="' . e(logo_url()) . '" alt="' . e(site_name()) . ' home" width="96" height="56"></a>'
        . '<div><strong>National Database Management System</strong><span>Authorized users only</span></div></div><div class="auth-body">';
}

function auth_end(): void
{
    echo '</div></div><p class="auth-foot"><a href="' . e(url('index.php')) . '">Back to the public website</a></p></main>';
    echo pwa_install_dialog();
    echo '<script src="' . e(asset('vendor/qrcode.js')) . '" defer></script><script src="' . e(asset('js/admin.js')) . '" defer></script><script src="' . e(asset('js/pwa.js')) . '" defer></script></body></html>';
}
