<?php
/**
 * Reports. Every figure is computed from database records and limited to the user's scope.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';
require APP_ROOT . '/includes/charts.php';

require_perm('reports.view');
$reports = [
    'registration' => ['Cooperative registration', 'New cooperative records over time, by type and status.', 'cooperatives.view'],
    'distribution' => ['Geographic cooperative distribution', 'Cooperatives by region, province and city, with drill-down.', 'cooperatives.view'],
    'personnel' => ['Officer and staff summary', 'Cooperative officers and staff, and CNPL officers by position.', 'personnel.view'],
    'membership' => ['Membership summary', 'Authorized aggregate member counts. No personal details.', 'members.view'],
    'point_persons' => ['Point-person assignments', 'Cooperatives with and without an active CNPL point person.', 'point_persons.view'],
    'ambassadors' => ['Ambassadors and focal persons', 'Designations by region, type and status.', 'ambassadors.view'],
    'activities' => ['Organizational activities', 'Activities by type and month, with participants.', 'activities.view'],
    'helpdesk' => ['Help desk performance', 'Volume, status, response and resolution times.', 'helpdesk.reports'],
    'events' => ['Event participation', 'Registrations and attendance per event.', 'events.view'],
    'elections' => ['Historical election performance', 'Results by year and area with comparisons.', 'elections.view'],
    'content' => ['Content publication', 'Published website content by type and month.', 'content.view'],
    'completeness' => ['Data completeness and verification', 'Missing key details and verification status by region.', 'cooperatives.view'],
];
$r = get('r');
if ($r === 'elections') {
    redirect('admin/election-performance.php');
}
if (!isset($reports[$r])) {
    admin_start('Reports', [['Reports', null]]);
    page_bar('Reports', 'Choose a report. Results honor your role and data scope.');
    echo '<div class="kpis">';
    foreach ($reports as $k => $rep) {
        if (can($rep[2])) {
            echo '<a class="kpi" href="' . e(url('admin/reports.php', ['r' => $k])) . '"><span class="kpi-top">' . e($rep[0]) . icon('chart') . '</span><span class="kpi-foot">' . e($rep[1]) . '</span></a>';
        }
    }
    echo '</div>';
    admin_end();
    exit;
}
[$title, $desc, $perm] = $reports[$r];
require_perm($perm);

$from = valid_date(get('from')) ? get('from') : date('Y-m-01', strtotime('-11 months'));
$to = valid_date(get('to')) ? get('to') : today();
$region = get_int('region');
$province = get_int('province');
$fromDt = $from . ' 00:00:00';
$toDt = $to . ' 23:59:59';

function coop_scope(array &$p, int $region, int $province = 0, string $a = 'c'): string
{
    $w = scope_sql(['region' => "{$a}.region_id", 'province' => "{$a}.province_id", 'city' => "{$a}.city_id", 'coop' => "{$a}.id"], $p);
    if ($region) {
        $w .= " AND {$a}.region_id = ?";
        $p[] = $region;
    }
    if ($province) {
        $w .= " AND {$a}.province_id = ?";
        $p[] = $province;
    }
    return $w;
}

function months_between(string $from, string $to): array
{
    $out = [];
    $t = strtotime(date('Y-m-01', strtotime($from)));
    $end = strtotime($to);
    while ($t <= $end && count($out) < 60) {
        $out[date('Y-m', $t)] = 0;
        $t = strtotime('+1 month', $t);
    }
    return $out;
}

$table = ['headers' => [], 'rows' => []];
$chart = '';
$extra = '';
$useDates = true;
switch ($r) {
    case 'registration':
        $p = [$fromDt, $toDt];
        $w = coop_scope($p, $region);
        $series = months_between($from, $to);
        foreach (q_all("SELECT DATE_FORMAT(c.created_at, '%Y-%m') m, COUNT(*) n FROM cooperatives c WHERE c.archived_at IS NULL AND c.created_at BETWEEN ? AND ? AND {$w} GROUP BY m", $p) as $row) {
            $series[$row['m']] = (int) $row['n'];
        }
        $labels = [];
        foreach ($series as $m => $n) {
            $labels[date('M Y', strtotime($m . '-01'))] = $n;
        }
        $chart = chart_columns($labels, 'New cooperative records per month');
        $table['headers'] = ['Cooperative type', 'Active', 'Inactive', 'Suspended', 'Dissolved', 'Total'];
        foreach (q_all("SELECT COALESCE(t.name, 'Not specified') tn, SUM(c.org_status='active') a, SUM(c.org_status='inactive') i, SUM(c.org_status='suspended') s, SUM(c.org_status='dissolved') d, COUNT(*) n
                        FROM cooperatives c LEFT JOIN cooperative_types t ON t.id = c.cooperative_type_id WHERE c.archived_at IS NULL AND c.created_at BETWEEN ? AND ? AND {$w} GROUP BY tn ORDER BY n DESC", $p) as $row) {
            $table['rows'][] = [$row['tn'], (int) $row['a'], (int) $row['i'], (int) $row['s'], (int) $row['d'], (int) $row['n']];
        }
        break;

    case 'distribution':
        $useDates = false;
        $p = [];
        $w = coop_scope($p, $region, $province);
        if ($province) {
            $q = "SELECT cm.id, COALESCE(cm.name, 'Not specified') nm, COUNT(*) n, SUM(c.verification_status='verified') v FROM cooperatives c LEFT JOIN cities_municipalities cm ON cm.id = c.city_id WHERE c.archived_at IS NULL AND {$w} GROUP BY cm.id, nm ORDER BY n DESC";
            $lvl = 'City or municipality';
        } elseif ($region) {
            $q = "SELECT p.id, COALESCE(p.name, 'Independent cities / no province') nm, COUNT(*) n, SUM(c.verification_status='verified') v FROM cooperatives c LEFT JOIN provinces p ON p.id = c.province_id WHERE c.archived_at IS NULL AND {$w} GROUP BY p.id, nm ORDER BY n DESC";
            $lvl = 'Province';
        } else {
            $q = "SELECT r.id, r.name nm, COUNT(*) n, SUM(c.verification_status='verified') v FROM cooperatives c JOIN regions r ON r.id = c.region_id WHERE c.archived_at IS NULL AND {$w} GROUP BY r.id, nm, r.sort_order ORDER BY r.sort_order";
            $lvl = 'Region';
        }
        $rows = q_all($q, $p);
        $total = max(1, array_sum(array_column($rows, 'n')));
        $data = [];
        $table['headers'] = [$lvl, 'Cooperatives', 'Share', 'Verified'];
        foreach ($rows as $row) {
            $data[$row['nm']] = (int) $row['n'];
            $link = !$region ? url('admin/reports.php', ['r' => 'distribution', 'region' => $row['id']]) : (!$province && $row['id'] ? url('admin/reports.php', ['r' => 'distribution', 'region' => $region, 'province' => $row['id']]) : '');
            $table['rows'][] = [$link ? ['html' => '<a href="' . e($link) . '">' . e($row['nm']) . '</a>', 'text' => $row['nm']] : $row['nm'], (int) $row['n'], fmt_pct($row['n'], $total, 1), (int) $row['v']];
        }
        $chart = chart_hbar(array_slice($data, 0, 25, true), 'Cooperatives by ' . strtolower($lvl), ['label_width' => 200]);
        break;

    case 'personnel':
        $useDates = false;
        $p = [];
        $w = coop_scope($p, $region);
        $table['headers'] = ['Region', 'Officers (active)', 'Staff (active)', 'Ended or resigned', 'Total records'];
        $data = [];
        foreach (q_all("SELECT r.short_name nm, SUM(x.personnel_type='officer' AND x.employment_status='active') o, SUM(x.personnel_type='staff' AND x.employment_status='active') s,
                        SUM(x.employment_status IN ('ended','resigned','terminated')) e, COUNT(*) n FROM cooperative_personnel x JOIN cooperatives c ON c.id = x.cooperative_id JOIN regions r ON r.id = c.region_id
                        WHERE x.archived_at IS NULL AND {$w} GROUP BY r.id, nm, r.sort_order ORDER BY r.sort_order", $p) as $row) {
            $table['rows'][] = [$row['nm'], (int) $row['o'], (int) $row['s'], (int) $row['e'], (int) $row['n']];
            $data[$row['nm']] = (int) $row['o'] + (int) $row['s'];
        }
        $chart = chart_hbar($data, 'Active cooperative officers and staff by region', ['label_width' => 110]);
        if (can('officers.view')) {
            $op = [];
            $ow = scope_sql(['region' => 'o.region_id'], $op);
            $pos = q_all("SELECT ps.title, COUNT(*) n FROM officer_appointments oa JOIN positions ps ON ps.id = oa.position_id JOIN officers o ON o.id = oa.officer_id WHERE oa.appointment_status = 'current' AND oa.archived_at IS NULL AND {$ow} GROUP BY ps.id, ps.title, ps.rank_order ORDER BY ps.rank_order", $op);
            $extra = '<section class="panel"><h2 class="panel-title">CNPL officers by current position</h2><ul class="mini-list">';
            foreach ($pos as $row) {
                $extra .= '<li><span class="ml-main">' . e($row['title']) . '</span><span class="ml-meta">' . fmt_num($row['n']) . '</span></li>';
            }
            $extra .= ($pos ? '' : '<li class="muted">No current appointments.</li>') . '</ul></section>';
        }
        break;

    case 'membership':
        $useDates = false;
        $p = [];
        $w = coop_scope($p, $region);
        $table['headers'] = ['Region', 'Cooperatives with member records', 'Active members', 'Inactive or ended', 'Reported members (cooperative totals)'];
        $data = [];
        foreach (q_all("SELECT r.short_name nm, r.id rid, COUNT(DISTINCT m.cooperative_id) coops, SUM(m.membership_status='active') a, SUM(m.membership_status<>'active') x
                        FROM members m JOIN cooperatives c ON c.id = m.cooperative_id JOIN regions r ON r.id = c.region_id WHERE m.archived_at IS NULL AND {$w} GROUP BY r.id, nm, r.sort_order ORDER BY r.sort_order", $p) as $row) {
            $p2 = [];
            $w2 = coop_scope($p2, (int) $row['rid']);
            $rep = (int) q_val("SELECT SUM(c.reported_member_count) FROM cooperatives c WHERE c.archived_at IS NULL AND {$w2}", $p2);
            $table['rows'][] = [$row['nm'], (int) $row['coops'], (int) $row['a'], (int) $row['x'], $rep];
            $data[$row['nm']] = (int) $row['a'];
        }
        $chart = chart_hbar($data, 'Active members on record by region', ['label_width' => 110]);
        $extra = '<p class="meta-line">Counts only. Membership is not treated as political affiliation, and no individual details appear in this report.</p>';
        break;

    case 'point_persons':
        $useDates = false;
        $p = [];
        $w = coop_scope($p, $region);
        $table['headers'] = ['Region', 'Active cooperatives', 'With active point person', 'Without', 'Coverage'];
        $data = [];
        foreach (q_all("SELECT r.short_name nm, COUNT(*) n, SUM(EXISTS (SELECT 1 FROM cnpl_point_persons pp WHERE pp.cooperative_id = c.id AND pp.assignment_status = 'active' AND pp.archived_at IS NULL)) w
                        FROM cooperatives c JOIN regions r ON r.id = c.region_id WHERE c.archived_at IS NULL AND c.org_status = 'active' AND {$w} GROUP BY r.id, nm, r.sort_order ORDER BY r.sort_order", $p) as $row) {
            $table['rows'][] = [$row['nm'], (int) $row['n'], (int) $row['w'], (int) $row['n'] - (int) $row['w'], fmt_pct($row['w'], $row['n'], 0)];
            $data[$row['nm']] = $row['n'] ? round(100 * $row['w'] / $row['n']) : 0;
        }
        $chart = chart_hbar($data, 'Share of active cooperatives with a point person (%)', ['label_width' => 110, 'format' => fn($v) => $v . '%']);
        $p3 = [];
        $w3 = coop_scope($p3, $region);
        $missing = q_all("SELECT c.id, c.name, c.reference_no FROM cooperatives c WHERE c.archived_at IS NULL AND c.org_status = 'active' AND {$w3}
                          AND NOT EXISTS (SELECT 1 FROM cnpl_point_persons pp WHERE pp.cooperative_id = c.id AND pp.assignment_status = 'active' AND pp.archived_at IS NULL) ORDER BY c.name LIMIT 50", $p3);
        $extra = '<section class="panel"><h2 class="panel-title">Active cooperatives without a point person (first 50)</h2><ul class="mini-list">';
        foreach ($missing as $m) {
            $extra .= '<li><span class="ml-main"><a href="' . e(url('admin/cooperatives.php', ['action' => 'view', 'id' => $m['id']])) . '">' . e($m['name']) . '</a></span><span class="ml-meta">' . e($m['reference_no']) . '</span></li>';
        }
        $extra .= ($missing ? '' : '<li class="muted">Every active cooperative has a point person.</li>') . '</ul></section>';
        break;

    case 'ambassadors':
        $useDates = false;
        $p = [];
        $w = scope_sql(['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id', 'coop' => 'x.cooperative_id'], $p);
        if ($region) {
            $w .= ' AND x.region_id = ?';
            $p[] = $region;
        }
        $table['headers'] = ['Region', 'Ambassadors (active)', 'Focal persons (active)', 'Inactive or ended', 'Activities reported'];
        $data = [];
        foreach (q_all("SELECT COALESCE(r.short_name, 'Not set') nm, SUM(x.designation_type='ambassador' AND x.designation_status='active') a, SUM(x.designation_type='focal_person' AND x.designation_status='active') f,
                        SUM(x.designation_status<>'active') i, SUM((SELECT COUNT(*) FROM activities ac WHERE ac.ambassador_id = x.id AND ac.archived_at IS NULL)) acts
                        FROM ambassadors_focal_persons x LEFT JOIN regions r ON r.id = x.region_id WHERE x.archived_at IS NULL AND {$w} GROUP BY r.id, nm ORDER BY MIN(r.sort_order)", $p) as $row) {
            $table['rows'][] = [$row['nm'], (int) $row['a'], (int) $row['f'], (int) $row['i'], (int) $row['acts']];
            $data[$row['nm']] = (int) $row['a'] + (int) $row['f'];
        }
        $chart = chart_hbar($data, 'Active ambassadors and focal persons by region', ['label_width' => 110]);
        break;

    case 'activities':
        $p = [$from, $to];
        $w = scope_sql(['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id', 'coop' => 'x.cooperative_id'], $p);
        if ($region) {
            $w .= ' AND x.region_id = ?';
            $p[] = $region;
        }
        $series = months_between($from, $to);
        foreach (q_all("SELECT DATE_FORMAT(x.activity_date, '%Y-%m') m, COUNT(*) n FROM activities x WHERE x.archived_at IS NULL AND x.review_status = 'approved' AND x.activity_date BETWEEN ? AND ? AND {$w} GROUP BY m", $p) as $row) {
            $series[$row['m']] = (int) $row['n'];
        }
        $labels = [];
        foreach ($series as $m => $n) {
            $labels[date('M Y', strtotime($m . '-01'))] = $n;
        }
        $chart = chart_columns($labels, 'Approved activities per month');
        $table['headers'] = ['Activity type', 'Activities', 'Participants', 'Cooperatives involved'];
        foreach (q_all("SELECT x.activity_type t, COUNT(*) n, SUM(x.participants_count) pc, COUNT(DISTINCT x.cooperative_id) cc FROM activities x WHERE x.archived_at IS NULL AND x.review_status = 'approved' AND x.activity_date BETWEEN ? AND ? AND {$w} GROUP BY x.activity_type ORDER BY n DESC", $p) as $row) {
            $table['rows'][] = [label_from_key($row['t']), (int) $row['n'], (int) $row['pc'], (int) $row['cc']];
        }
        break;

    case 'helpdesk':
        require_once APP_ROOT . '/includes/helpdesk.php';
        $p = [];
        $vis = ticket_visibility_sql($p);
        array_unshift($p, $fromDt, $toDt);
        $base = "t.archived_at IS NULL AND t.created_at BETWEEN ? AND ? AND {$vis}";
        $st = q_pairs("SELECT t.ticket_status, COUNT(*) FROM helpdesk_tickets t WHERE {$base} GROUP BY t.ticket_status", $p);
        $kp = q_one("SELECT COUNT(*) n, AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.first_response_at)) fr, AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at)) rs,
                     SUM(t.first_response_at IS NULL AND t.ticket_status NOT IN ('resolved','closed')) waiting FROM helpdesk_tickets t WHERE {$base}", $p);
        $hours = fn($m) => $m === null ? 'n/a' : number_format($m / 60, 1) . ' h';
        $extra = '<div class="stat-row"><div class="stat"><span class="stat-num">' . fmt_num($kp['n']) . '</span><span class="stat-label">Tickets received</span></div>'
            . '<div class="stat"><span class="stat-num">' . e($hours($kp['fr'])) . '</span><span class="stat-label">Average first response</span></div>'
            . '<div class="stat"><span class="stat-num">' . e($hours($kp['rs'])) . '</span><span class="stat-label">Average time to resolve</span></div>'
            . '<div class="stat"><span class="stat-num">' . fmt_num($kp['waiting']) . '</span><span class="stat-label">Open, no response yet</span></div></div>';
        $data = [];
        foreach (TICKET_STATUSES as $k => $l) {
            $data[$l] = (int) ($st[$k] ?? 0);
        }
        $chart = chart_hbar($data, 'Tickets by status', ['label_width' => 150]);
        $table['headers'] = ['Category', 'Tickets', 'Resolved or closed', 'Average resolution'];
        foreach (q_all("SELECT COALESCE(c.name, 'Uncategorized') nm, COUNT(*) n, SUM(t.ticket_status IN ('resolved','closed')) r, AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at)) rs
                        FROM helpdesk_tickets t LEFT JOIN helpdesk_categories c ON c.id = t.category_id WHERE {$base} GROUP BY nm ORDER BY n DESC", $p) as $row) {
            $table['rows'][] = [$row['nm'], (int) $row['n'], (int) $row['r'], $hours($row['rs'])];
        }
        $officers = q_all("SELECT u.full_name, COUNT(*) n, SUM(t.ticket_status IN ('resolved','closed')) r FROM helpdesk_tickets t JOIN users u ON u.id = t.assigned_to WHERE {$base} GROUP BY u.id, u.full_name ORDER BY r DESC", $p);
        $extra2 = '<section class="panel"><h2 class="panel-title">By assigned officer</h2><ul class="mini-list">';
        foreach ($officers as $o) {
            $extra2 .= '<li><span class="ml-main">' . e($o['full_name']) . '</span><span class="ml-meta">' . fmt_num($o['r']) . ' of ' . fmt_num($o['n']) . ' resolved</span></li>';
        }
        $extra2 .= ($officers ? '' : '<li class="muted">No assigned tickets in this period.</li>') . '</ul></section>';
        break;

    case 'events':
        $p = [$fromDt, $toDt];
        $table['headers'] = ['Event', 'Date', 'Visibility', 'Capacity', 'Registered', 'Waiting list', 'Attended', 'Attendance rate'];
        $data = [];
        foreach (q_all("SELECT e.id, e.title, e.starts_at, e.visibility, e.registration_limit, SUM(r.registration_status IN ('registered','attended','no_show')) reg, SUM(r.registration_status='waitlisted') wl, SUM(r.registration_status='attended') att
                        FROM events e LEFT JOIN event_registrations r ON r.event_id = e.id WHERE e.archived_at IS NULL AND e.starts_at BETWEEN ? AND ? GROUP BY e.id, e.title, e.starts_at, e.visibility, e.registration_limit ORDER BY e.starts_at DESC", $p) as $row) {
            $table['rows'][] = [$row['title'], fmt_date($row['starts_at']), ucfirst($row['visibility']), $row['registration_limit'] ? (int) $row['registration_limit'] : 'No limit', (int) $row['reg'], (int) $row['wl'], (int) $row['att'], fmt_pct($row['att'], $row['reg'], 0)];
            $data[excerpt($row['title'], 30)] = (int) $row['reg'];
        }
        $chart = chart_hbar(array_slice($data, 0, 15, true), 'Registrations per event', ['label_width' => 220]);
        break;

    case 'content':
        $p = [$fromDt, $toDt];
        $series = months_between($from, $to);
        foreach (q_all("SELECT DATE_FORMAT(COALESCE(publish_at, published_at), '%Y-%m') m, COUNT(*) n FROM posts WHERE status = 'published' AND COALESCE(publish_at, published_at) BETWEEN ? AND ? GROUP BY m", $p) as $row) {
            $series[$row['m']] = (int) $row['n'];
        }
        $labels = [];
        foreach ($series as $m => $n) {
            $labels[date('M Y', strtotime($m . '-01'))] = $n;
        }
        $chart = chart_columns($labels, 'Published news and updates per month');
        $table['headers'] = ['Content type', 'Published', 'In review', 'Drafts', 'Archived'];
        $types = ['posts' => 'News and updates', 'pages' => 'Pages', 'events' => 'Events', 'legislative_updates' => 'Legislative updates', 'awards' => 'Awards', 'policies' => 'Policies and procedures', 'history_milestones' => 'History milestones', 'galleries' => 'Galleries', 'faqs' => 'FAQs'];
        foreach ($types as $tbl => $lbl) {
            $c = q_pairs('SELECT status, COUNT(*) FROM ' . ident($tbl) . ' WHERE created_at BETWEEN ? AND ? GROUP BY status', $p);
            $table['rows'][] = [$lbl, (int) ($c['published'] ?? 0), (int) ($c['in_review'] ?? 0), (int) ($c['draft'] ?? 0), (int) ($c['archived'] ?? 0)];
        }
        break;

    case 'completeness':
        $useDates = false;
        $p = [];
        $w = coop_scope($p, $region);
        $table['headers'] = ['Region', 'Cooperatives', 'No CDA number', 'No type', 'No city or municipality', 'No contact person', 'No contact email or phone', 'Complete', 'Verified'];
        $data = [];
        foreach (q_all("SELECT r.short_name nm, COUNT(*) n, SUM(c.cda_registration_no IS NULL) a, SUM(c.cooperative_type_id IS NULL) b, SUM(c.city_id IS NULL) cc, SUM(c.contact_person IS NULL OR c.contact_person = '') d,
                        SUM((c.contact_email IS NULL OR c.contact_email = '') AND (c.contact_phone IS NULL OR c.contact_phone = '')) e,
                        SUM(c.cda_registration_no IS NOT NULL AND c.cooperative_type_id IS NOT NULL AND c.city_id IS NOT NULL AND c.contact_person <> '' AND (c.contact_email <> '' OR c.contact_phone <> '')) ok,
                        SUM(c.verification_status = 'verified') v FROM cooperatives c JOIN regions r ON r.id = c.region_id WHERE c.archived_at IS NULL AND {$w} GROUP BY r.id, nm, r.sort_order ORDER BY r.sort_order", $p) as $row) {
            $table['rows'][] = [$row['nm'], (int) $row['n'], (int) $row['a'], (int) $row['b'], (int) $row['cc'], (int) $row['d'], (int) $row['e'], fmt_pct($row['ok'], $row['n'], 0), fmt_pct($row['v'], $row['n'], 0)];
            $data[$row['nm']] = $row['n'] ? (int) round(100 * $row['ok'] / $row['n']) : 0;
        }
        $chart = chart_hbar($data, 'Share of cooperative records with complete key details (%)', ['label_width' => 110, 'format' => fn($v) => $v . '%']);
        $pend = [];
        foreach (['cooperatives' => 'Cooperatives', 'cooperative_personnel' => 'Officers and staff', 'members' => 'Members', 'cnpl_point_persons' => 'Point persons', 'ambassadors_focal_persons' => 'Ambassadors and focal persons', 'activities' => 'Activities'] as $tbl => $lbl) {
            $pend[$lbl] = (int) q_val('SELECT COUNT(*) FROM ' . ident($tbl) . " WHERE review_status = 'pending' AND archived_at IS NULL");
        }
        $extra = '<section class="panel"><h2 class="panel-title">Records waiting for review (all scopes)</h2><ul class="mini-list">';
        foreach ($pend as $l => $n) {
            $extra .= '<li><span class="ml-main">' . e($l) . '</span><span class="ml-meta">' . fmt_num($n) . '</span></li>';
        }
        $extra .= '</ul></section>';
        break;
}

if (get('export') === 'csv') {
    require_perm('reports.export');
    q_insert('export_logs', ['user_id' => uid(), 'module' => 'report_' . $r, 'filters' => "from={$from}; to={$to}; region={$region}", 'row_count' => count($table['rows']), 'is_sensitive' => 0, 'ip_address' => client_ip(), 'created_at' => now()]);
    audit_log('export', 'reports', null, 'Exported report: ' . $title);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report-' . $r . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $table['headers']);
    foreach ($table['rows'] as $row) {
        fputcsv($out, array_map(fn($c) => csv_safe(is_array($c) ? $c['text'] : $c), $row));
    }
    fclose($out);
    exit;
}

admin_start($title, [['Reports', url('admin/reports.php')], [$title, null]]);
page_bar($title, e($desc), '<button class="btn btn-secondary" type="button" data-print>Print</button>' . (can('reports.export') && $table['rows'] ? '<a class="btn btn-secondary" href="' . e(current_url(['export' => 'csv'])) . '">Export CSV</a>' : ''));
echo '<form class="filters" method="get"><input type="hidden" name="r" value="' . e($r) . '">';
if ($useDates) {
    echo '<div class="filter"><label for="rp_f">From</label><input type="date" id="rp_f" name="from" value="' . e($from) . '"></div><div class="filter"><label for="rp_t">To</label><input type="date" id="rp_t" name="to" value="' . e($to) . '"></div>';
}
if (!in_array($r, ['events', 'content', 'helpdesk'], true)) {
    echo '<div class="filter"><label for="rp_r">Region</label>' . select_html('region', regions_list(), $region ?: '', ['id' => 'rp_r'], 'All regions in my scope') . '</div>';
}
echo '<div class="filter filter-actions"><button class="btn btn-primary" type="submit">Apply</button></div></form>';
echo $extra;
if ($chart) {
    echo '<section class="panel">' . $chart . '</section>';
}
if ($table['rows']) {
    echo '<div class="table-wrap"><table class="table table-cards"><thead><tr>';
    foreach ($table['headers'] as $i => $h) {
        echo '<th scope="col"' . ($i ? ' class="num"' : '') . '>' . e($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($table['rows'] as $row) {
        echo '<tr>';
        foreach ($row as $i => $c) {
            $v = is_array($c) ? $c['html'] : e(is_int($c) ? fmt_num($c) : $c);
            echo '<td data-label="' . e($table['headers'][$i]) . '"' . ($i ? ' class="num"' : '') . '>' . $v . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
} else {
    echo '<div class="empty"><h2>No data for this selection</h2><p>Try a wider date range or another region.</p></div>';
}
echo $extra2 ?? '';
echo '<p class="meta-line">Generated ' . e(fmt_datetime(now())) . ' from live records. Scope: ' . e(scope_description()) . '.</p>';
admin_end();
