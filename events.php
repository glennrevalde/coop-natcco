<?php
/**
 * Events: upcoming list, monthly calendar and archive. Private organizational
 * events are shown only to signed-in users.
 */
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$view = in_array(get('view'), ['upcoming', 'calendar', 'past'], true) ? get('view') : 'upcoming';
$cat = get_int('category');
$base = function (array &$p) use ($cat): string {
    $w = pub_where('x', $p) . (current_user() ? '' : " AND x.visibility = 'public'");
    if ($cat) {
        $w .= ' AND x.category_id = ?';
        $p[] = $cat;
    }
    return $w;
};
public_start('Events', ['crumbs' => [['Events', null]], 'sub' => 'Assemblies, trainings, consultations and other activities.' . (current_user() ? ' Private organizational events are included because you are signed in.' : '')]);
echo '<div class="wrap section"><nav class="tabs" aria-label="Event views">';
foreach (['upcoming' => 'Upcoming', 'calendar' => 'Calendar', 'past' => 'Completed and past'] as $k => $l) {
    echo '<a href="' . e(url('events.php', ['view' => $k, 'category' => $cat ?: null])) . '"' . ($k === $view ? ' aria-current="page"' : '') . '>' . $l . '</a>';
}
echo '</nav>';
echo '<form class="pub-filters" method="get"><input type="hidden" name="view" value="' . e($view) . '"><div class="filter"><label for="ev_c">Category</label>'
    . select_html('category', q_pairs("SELECT id, name FROM content_categories WHERE scope = 'event' AND is_active = 1 ORDER BY sort_order"), $cat ?: '', ['id' => 'ev_c'], 'All categories')
    . '</div>' . ($view === 'calendar' ? '<input type="hidden" name="month" value="' . e(get('month')) . '">' : '') . '<button class="btn btn-primary" type="submit">Filter</button></form>';

if ($view === 'calendar') {
    $month = preg_match('/^\d{4}-\d{2}$/', get('month')) ? get('month') : date('Y-m');
    $start = strtotime($month . '-01');
    $p = [];
    $w = $base($p);
    $p[] = date('Y-m-01 00:00:00', $start);
    $p[] = date('Y-m-t 23:59:59', $start);
    $evs = q_all("SELECT x.* FROM events x WHERE {$w} AND x.starts_at BETWEEN ? AND ? ORDER BY x.starts_at", $p);
    $byDay = [];
    foreach ($evs as $ev) {
        $byDay[(int) date('j', strtotime($ev['starts_at']))][] = $ev;
    }
    $prev = date('Y-m', strtotime('-1 month', $start));
    $next = date('Y-m', strtotime('+1 month', $start));
    echo '<div class="cal-head"><a class="btn btn-secondary btn-sm" href="' . e(url('events.php', ['view' => 'calendar', 'month' => $prev, 'category' => $cat ?: null])) . '">Previous month</a><h2>' . e(date('F Y', $start)) . '</h2>'
        . '<a class="btn btn-secondary btn-sm" href="' . e(url('events.php', ['view' => 'calendar', 'month' => $next, 'category' => $cat ?: null])) . '">Next month</a></div>';
    echo '<div class="cal" role="grid" aria-label="Events in ' . e(date('F Y', $start)) . '">';
    foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d) {
        echo '<div class="cal-dow" role="columnheader">' . $d . '</div>';
    }
    $lead = (int) date('w', $start);
    $days = (int) date('t', $start);
    for ($i = 0; $i < $lead; $i++) {
        echo '<div class="cal-day out" aria-hidden="true"></div>';
    }
    for ($d = 1; $d <= $days; $d++) {
        $isToday = date('Y-m-d', $start + ($d - 1) * 86400) === today();
        echo '<div class="cal-day' . ($isToday ? ' today' : '') . '" role="gridcell"><span class="cal-num">' . $d . '</span>';
        foreach ($byDay[$d] ?? [] as $ev) {
            echo '<a class="cal-ev" href="' . e(url('event.php', ['slug' => $ev['slug']])) . '" title="' . e($ev['title']) . '">' . e(date('g:i', strtotime($ev['starts_at']))) . ' ' . e($ev['title']) . '</a>';
        }
        echo '</div>';
    }
    echo '</div>';
    if ($evs) {
        echo '<h2 class="h3 mt">Events this month</h2><ul class="event-list">';
        foreach ($evs as $ev) {
            echo event_item($ev);
        }
        echo '</ul>';
    }
} else {
    [$page, $per, $offset] = pager_params(12);
    $p = [];
    $w = $base($p);
    $p[] = now();
    $cond = $view === 'upcoming' ? "x.starts_at >= ? AND x.event_status <> 'completed'" : "(x.starts_at < ? OR x.event_status = 'completed')";
    $order = $view === 'upcoming' ? 'x.starts_at ASC' : 'x.starts_at DESC';
    $total = (int) q_val("SELECT COUNT(*) FROM events x WHERE {$w} AND {$cond}", $p);
    $evs = q_all("SELECT x.*, cc.name AS category_name FROM events x LEFT JOIN content_categories cc ON cc.id = x.category_id WHERE {$w} AND {$cond} ORDER BY {$order} LIMIT {$per} OFFSET {$offset}", $p);
    if (!$evs) {
        echo empty_state($view === 'upcoming' ? 'No upcoming events' : 'No past events', $view === 'upcoming' ? 'New events will be announced here.' : 'Completed events will be archived here.');
    } else {
        echo '<ul class="item-list">';
        foreach ($evs as $ev) {
            echo '<li class="ev-row">' . date_tile($ev['starts_at']) . '<div><a class="il-title" href="' . e(url('event.php', ['slug' => $ev['slug']])) . '">' . e($ev['title']) . '</a><div class="il-meta">'
                . '<span>' . e(fmt_datetime($ev['starts_at'])) . '</span>' . ($ev['location_name'] ? '<span>' . e($ev['location_name']) . '</span>' : '') . ($ev['category_name'] ? '<span>' . e($ev['category_name']) . '</span>' : '')
                . ($ev['visibility'] === 'private' ? '<span class="st st-warn">Private organizational event</span>' : '') . ($ev['event_status'] !== 'scheduled' ? '<span>' . status_text($ev['event_status']) . '</span>' : '')
                . ($ev['registration_enabled'] && $view === 'upcoming' ? '<span class="st st-info">Registration open</span>' : '') . '</div>'
                . ($ev['summary'] ? '<p class="small muted">' . e(excerpt($ev['summary'], 200)) . '</p>' : '') . '</div></li>';
        }
        echo '</ul>' . public_pager($total, $page, $per);
    }
}
echo '</div>';
public_end();
