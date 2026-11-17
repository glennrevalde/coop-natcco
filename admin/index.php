<?php
/**
 * Dashboard: every figure is computed from database records within the user's scope.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';
require APP_ROOT . '/includes/charts.php';

$u = require_login();
if (!can('dashboard.view')) {
    admin_start('Welcome');
    page_bar('Welcome, ' . $u['full_name']);
    echo '<div class="empty"><h2>Your account has no active role yet</h2><p>An administrator must assign and approve a role before you can use the system.</p></div>';
    admin_end();
    exit;
}

$region = get_int('region');
$from = valid_date(get('from')) ? get('from') : '';
$to = valid_date(get('to')) ? get('to') : '';

/** Scoped count helper for cooperative-linked data. */
function dash_coop_where(array &$p, int $region): string
{
    $w = scope_sql(['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'], $p);
    if ($region) {
        $w .= ' AND c.region_id = ?';
        $p[] = $region;
    }
    return $w;
}

$kpis = [];
if (can('cooperatives.view')) {
    $p = [];
    $w = dash_coop_where($p, $region);
    $row = q_one("SELECT COUNT(*) total, SUM(c.verification_status = 'verified') verified, SUM(c.review_status = 'pending') pending,
                  SUM(c.org_status = 'active') active FROM cooperatives c WHERE c.archived_at IS NULL AND {$w}", $p);
    $kpis[] = ['Primary cooperatives', (int) $row['total'], fmt_num($row['verified']) . ' verified, ' . fmt_num($row['active']) . ' active', url('admin/cooperatives.php', ['f_region' => $region ?: null]), 'building', ''];
    if ($from || $to) {
        $p2 = [];
        $w2 = dash_coop_where($p2, $region);
        if ($from) {
            $w2 .= ' AND c.created_at >= ?';
            $p2[] = $from . ' 00:00:00';
        }
        if ($to) {
            $w2 .= ' AND c.created_at <= ?';
            $p2[] = $to . ' 23:59:59';
        }
        $kpis[] = ['Registered in period', (int) q_val("SELECT COUNT(*) FROM cooperatives c WHERE c.archived_at IS NULL AND {$w2}", $p2), ($from ? fmt_date($from) : 'start') . ' to ' . ($to ? fmt_date($to) : 'today'), url('admin/cooperatives.php'), 'calendar', ''];
    }
}
if (can('officers.view')) {
    $p = [];
    $w = scope_sql(['region' => 'o.region_id'], $p);
    $n = (int) q_val("SELECT COUNT(DISTINCT o.id) FROM officers o JOIN officer_appointments oa ON oa.officer_id = o.id AND oa.appointment_status = 'current' AND oa.archived_at IS NULL WHERE o.archived_at IS NULL AND {$w}", $p);
    $all = (int) q_val("SELECT COUNT(*) FROM officers o WHERE o.archived_at IS NULL AND {$w}", $p);
    $kpis[] = ['CNPL officers', $all, fmt_num($n) . ' with a current appointment', url('admin/officers.php'), 'star', ''];
}
if (can('personnel.view')) {
    $p = [];
    $w = dash_coop_where($p, $region);
    $kpis[] = ['Cooperative officers and staff', (int) q_val("SELECT COUNT(*) FROM cooperative_personnel x JOIN cooperatives c ON c.id = x.cooperative_id WHERE x.archived_at IS NULL AND x.employment_status = 'active' AND {$w}", $p), 'Active records', url('admin/personnel.php', ['f_status' => 'active']), 'users', ''];
}
if (can('point_persons.view')) {
    $p = [];
    $w = dash_coop_where($p, $region);
    $act = (int) q_val("SELECT COUNT(*) FROM cnpl_point_persons x JOIN cooperatives c ON c.id = x.cooperative_id WHERE x.archived_at IS NULL AND x.assignment_status = 'active' AND {$w}", $p);
    $kpis[] = ['CNPL point persons', $act, 'Active assignments', url('admin/point-persons.php', ['f_status' => 'active']), 'pin', ''];
}
if (can('ambassadors.view')) {
    $p = [];
    $w = scope_sql(['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id', 'coop' => 'x.cooperative_id'], $p);
    if ($region) {
        $w .= ' AND x.region_id = ?';
        $p[] = $region;
    }
    $r = q_one("SELECT SUM(designation_type = 'ambassador') a, SUM(designation_type = 'focal_person') f FROM ambassadors_focal_persons x WHERE x.archived_at IS NULL AND x.designation_status = 'active' AND {$w}", $p);
    $kpis[] = ['Ambassadors and focal persons', (int) $r['a'] + (int) $r['f'], fmt_num($r['a']) . ' ambassadors, ' . fmt_num($r['f']) . ' focal persons', url('admin/ambassadors.php'), 'flag', ''];
}
if (can('members.view')) {
    $p = [];
    $w = dash_coop_where($p, $region);
    $kpis[] = ['Regular members on record', (int) q_val("SELECT COUNT(*) FROM members x JOIN cooperatives c ON c.id = x.cooperative_id WHERE x.archived_at IS NULL AND x.membership_status = 'active' AND {$w}", $p), 'Active memberships (count only)', url('admin/members.php', ['f_status' => 'active']), 'id', ''];
}
if (can('approvals.view')) {
    $n = approvals_pending_count();
    $kpis[] = ['Pending approvals', $n, $n ? 'Waiting for your decision' : 'Nothing waiting', url('admin/approvals.php'), 'check', $n ? 'kpi-attn' : ''];
}
if (can_any(['helpdesk.view', 'helpdesk.view_all'])) {
    require_once APP_ROOT . '/includes/helpdesk.php';
    $p = [];
    $w = ticket_visibility_sql($p);
    $n = (int) q_val("SELECT COUNT(*) FROM helpdesk_tickets t WHERE t.ticket_status NOT IN ('resolved','closed') AND t.archived_at IS NULL AND {$w}", $p);
    $urgent = (int) q_val("SELECT COUNT(*) FROM helpdesk_tickets t WHERE t.ticket_status NOT IN ('resolved','closed') AND t.priority IN ('high','urgent') AND t.archived_at IS NULL AND {$w}", $p);
    $kpis[] = ['Open help desk tickets', $n, fmt_num($urgent) . ' high or urgent', url('admin/helpdesk.php', ['f_open' => '1']), 'help', $urgent ? 'kpi-alert' : ''];
}
if (can('content.publish')) {
    $n = (int) q_val("SELECT (SELECT COUNT(*) FROM posts WHERE status = 'in_review' AND archived_at IS NULL) + (SELECT COUNT(*) FROM pages WHERE status = 'in_review') + (SELECT COUNT(*) FROM legislative_updates WHERE status = 'in_review') + (SELECT COUNT(*) FROM awards WHERE status = 'in_review') + (SELECT COUNT(*) FROM policies WHERE status = 'in_review')");
    $kpis[] = ['Content waiting to publish', $n, 'Submitted for review', url('admin/approvals.php') . '#content', 'news', $n ? 'kpi-attn' : ''];
}
if (can('point_persons.tasks') && has_role('point_person')) {
    $n = (int) q_val("SELECT COUNT(*) FROM point_person_tasks t JOIN cnpl_point_persons pp ON pp.id = t.point_person_id WHERE pp.user_id = ? AND t.task_status IN ('open','in_progress') AND t.archived_at IS NULL", [uid()]);
    $kpis[] = ['My open tasks', $n, 'Assigned to your point-person role', url('admin/tasks.php', ['f_status' => 'open']), 'list', $n ? 'kpi-attn' : ''];
}

admin_start('Dashboard');
page_bar('Dashboard', 'Hello, ' . e($u['full_name']) . '. Figures cover your data scope: ' . e(scope_description()) . '.');
echo flash_render();
?>
<form class="filters" method="get" aria-label="Dashboard filters">
    <div class="filter"><label for="d_region">Region</label><?= select_html('region', regions_list(), $region ?: '', ['id' => 'd_region'], 'All regions in my scope') ?></div>
    <div class="filter"><label for="d_from">Registered from</label><input type="date" id="d_from" name="from" value="<?= e($from) ?>"></div>
    <div class="filter"><label for="d_to">Registered until</label><input type="date" id="d_to" name="to" value="<?= e($to) ?>"></div>
    <div class="filter filter-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-ghost" href="<?= e(url('admin/index.php')) ?>">Reset</a></div>
</form>
<div class="kpis">
<?php foreach ($kpis as $k): ?>
    <a class="kpi <?= e($k[5]) ?>" href="<?= e($k[3]) ?>"><span class="kpi-top"><?= e($k[0]) ?><?= icon($k[4]) ?></span><span class="kpi-num"><?= fmt_num($k[1]) ?></span><span class="kpi-foot"><?= e($k[2]) ?></span></a>
<?php endforeach; ?>
</div>
<div class="dash-grid">
<?php
if (can('cooperatives.view')) {
    $p = [];
    $w = dash_coop_where($p, $region);
    $rows = q_all("SELECT r.id, r.short_name, COUNT(c.id) n FROM cooperatives c JOIN regions r ON r.id = c.region_id WHERE c.archived_at IS NULL AND {$w} GROUP BY r.id, r.short_name, r.sort_order ORDER BY r.sort_order", $p);
    $data = [];
    foreach ($rows as $r) {
        $data[$r['short_name']] = (int) $r['n'];
    }
    echo '<section class="panel span-8"><div class="panel-head"><h2 class="panel-title">Cooperatives by region</h2><a class="btn btn-ghost btn-sm" href="' . e(url('admin/reports.php', ['r' => 'distribution'])) . '">Distribution report</a></div>' . chart_hbar($data, 'Number of primary cooperatives per region', ['label_width' => 110]) . '</section>';
    $p = [];
    $w = dash_coop_where($p, $region);
    $v = q_pairs("SELECT c.verification_status, COUNT(*) FROM cooperatives c WHERE c.archived_at IS NULL AND {$w} GROUP BY c.verification_status", $p);
    $r2 = q_pairs("SELECT c.review_status, COUNT(*) FROM cooperatives c WHERE c.archived_at IS NULL AND {$w} GROUP BY c.review_status", $p);
    echo '<section class="panel span-4"><h2 class="panel-title">Data verification</h2><ul class="mini-list">';
    foreach (['verified' => 'Verified', 'unverified' => 'Not yet verified', 'needs_update' => 'Needs update after changes'] as $k => $l) {
        echo '<li><span class="ml-main"><a href="' . e(url('admin/cooperatives.php', ['f_verification' => $k])) . '">' . e($l) . '</a></span><span class="ml-meta">' . fmt_num($v[$k] ?? 0) . '</span></li>';
    }
    foreach (['pending' => 'Pending approval', 'rejected' => 'Rejected submissions'] as $k => $l) {
        echo '<li><span class="ml-main"><a href="' . e(url('admin/cooperatives.php', ['review' => $k])) . '">' . e($l) . '</a></span><span class="ml-meta">' . fmt_num($r2[$k] ?? 0) . '</span></li>';
    }
    echo '</ul><p class="small"><a href="' . e(url('admin/reports.php', ['r' => 'completeness'])) . '">Data completeness report</a></p></section>';
}
if (can('elections.view')) {
    $rows = q_all("SELECT y.election_year, SUM(x.votes_obtained) v FROM election_results x JOIN election_years y ON y.id = x.election_year_id
                   WHERE x.geo_level = 'national' AND x.verification_status = 'verified' AND x.archived_at IS NULL GROUP BY y.election_year ORDER BY y.election_year");
    $data = [];
    foreach ($rows as $r) {
        $data[(string) $r['election_year']] = (int) $r['v'];
    }
    $pend = (int) q_val("SELECT COUNT(*) FROM election_results WHERE verification_status = 'pending' AND archived_at IS NULL");
    echo '<section class="panel span-6"><div class="panel-head"><h2 class="panel-title">Verified national votes by election year</h2><a class="btn btn-ghost btn-sm" href="' . e(url('admin/election-performance.php')) . '">Election performance</a></div>'
        . ($data ? chart_columns($data, 'Verified national votes obtained per election year') : '<p class="muted">No verified national results yet.</p>')
        . ($pend ? '<p class="small">' . status_text('pending', fmt_num($pend) . ' result(s) waiting for verification') . '</p>' : '') . '</section>';
}
if (can('approvals.view')) {
    echo '<section class="panel span-6"><div class="panel-head"><h2 class="panel-title">Waiting for approval</h2><a class="btn btn-ghost btn-sm" href="' . e(url('admin/approvals.php')) . '">Open inbox</a></div><ul class="mini-list">';
    $shown = 0;
    foreach (approval_sources() as $key => $src) {
        if (!can($src['perm']) || $shown >= 7) {
            continue;
        }
        $p = [];
        $w = $src['where']($p);
        $idCol = $src['id_col'] ?? 'x.id';
        foreach (q_all("SELECT {$idCol} AS id, {$src['title']} AS t FROM {$src['from']} WHERE {$w} LIMIT 3", $p) as $r) {
            $link = $src['page'] ? url($src['page'], ['action' => 'view', 'id' => $r['id']]) : url('admin/approvals.php') . '#' . $key;
            echo '<li><span class="ml-main"><a href="' . e($link) . '">' . e(excerpt($r['t'], 70)) . '</a></span><span class="ml-meta">' . e($src['label']) . '</span></li>';
            $shown++;
        }
    }
    if (!$shown) {
        echo '<li><span class="ml-main muted">Nothing is waiting for your approval.</span></li>';
    }
    echo '</ul></section>';
}
$events = q_all("SELECT id, title, starts_at, location_name, visibility FROM events WHERE status = 'published' AND archived_at IS NULL AND starts_at >= ? AND event_status <> 'cancelled' ORDER BY starts_at LIMIT 5", [now()]);
echo '<section class="panel span-6"><div class="panel-head"><h2 class="panel-title">Upcoming events</h2>' . (can('events.view') ? '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/events.php', ['f_when' => 'upcoming'])) . '">All events</a>' : '') . '</div><ul class="mini-list">';
foreach ($events as $ev) {
    $link = can('events.view') ? url('admin/events.php', ['action' => 'view', 'id' => $ev['id']]) : url('events.php');
    echo '<li><span class="ml-main"><a href="' . e($link) . '">' . e($ev['title']) . '</a><span class="sub muted small"> ' . e($ev['location_name']) . ($ev['visibility'] === 'private' ? ', private' : '') . '</span></span><span class="ml-meta">' . e(fmt_date($ev['starts_at'], 'M j')) . '</span></li>';
}
if (!$events) {
    echo '<li><span class="ml-main muted">No upcoming events.</span></li>';
}
echo '</ul></section>';
if (can('audit.view')) {
    $acts = q_all("SELECT a.action, a.module, a.description, a.actor_name, a.created_at, a.outcome FROM audit_logs a WHERE a.action NOT IN ('login_success','logout','view') ORDER BY a.id DESC LIMIT 8");
    $title = 'Recent administrative activity';
} else {
    $acts = q_all("SELECT a.action, a.module, a.description, a.actor_name, a.created_at, a.outcome FROM audit_logs a WHERE a.user_id = ? AND a.action NOT IN ('view') ORDER BY a.id DESC LIMIT 8", [uid()]);
    $title = 'My recent activity';
}
echo '<section class="panel span-6"><div class="panel-head"><h2 class="panel-title">' . e($title) . '</h2>' . (can('audit.view') ? '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/audit.php')) . '">Audit log</a>' : '') . '</div><ul class="mini-list">';
foreach ($acts as $a) {
    echo '<li><span class="ml-main">' . e(excerpt($a['description'] ?: label_from_key($a['action']), 80)) . '<span class="sub muted small"> ' . e($a['actor_name']) . '</span></span><span class="ml-meta">' . e(time_ago($a['created_at'])) . '</span></li>';
}
if (!$acts) {
    echo '<li><span class="ml-main muted">No activity yet.</span></li>';
}
echo '</ul></section>';
?>
</div>
<?php admin_end();
