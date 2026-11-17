<?php
/**
 * Historical election performance by year and geographic level, with year-to-year
 * comparison, drill-down, charts, printing and controlled export. Aggregate official data only.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';
require APP_ROOT . '/includes/charts.php';

require_perm('elections.view');
$years = q_pairs('SELECT id, election_year FROM election_years WHERE archived_at IS NULL ORDER BY election_year DESC');
$latest = (int) q_val("SELECT y.id FROM election_years y JOIN election_results x ON x.election_year_id = y.id AND x.archived_at IS NULL WHERE y.archived_at IS NULL ORDER BY y.election_year DESC LIMIT 1");
$yearId = isset($years[get_int('year')]) ? get_int('year') : $latest;
$compareId = isset($years[get_int('compare')]) && get_int('compare') !== $yearId ? get_int('compare') : 0;
$regionId = get_int('region');
$provinceId = get_int('province');
$level = $provinceId ? 'city' : ($regionId ? 'province' : (in_array(get('level'), ['region', 'province', 'city'], true) ? get('level') : 'region'));
$includePending = get('pending') === '1';

function perf_rows(int $yearId, string $level, int $regionId, int $provinceId, bool $pending): array
{
    if (!$yearId) {
        return [];
    }
    $p = [$yearId, $level];
    $w = 'x.election_year_id = ? AND x.geo_level = ? AND x.archived_at IS NULL';
    $w .= $pending ? " AND x.verification_status IN ('verified','pending')" : " AND x.verification_status = 'verified'";
    if ($regionId) {
        $w .= ' AND x.region_id = ?';
        $p[] = $regionId;
    }
    if ($provinceId) {
        $w .= ' AND x.province_id = ?';
        $p[] = $provinceId;
    }
    $w .= ' AND ' . scope_sql(['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id'], $p);
    $rows = q_all("SELECT x.*, s.title AS source_title, r.name AS region_name, r.short_name, r.sort_order, p.name AS province_name, cm.name AS city_name FROM election_results x JOIN election_sources s ON s.id = x.source_id
                   LEFT JOIN regions r ON r.id = x.region_id LEFT JOIN provinces p ON p.id = x.province_id LEFT JOIN cities_municipalities cm ON cm.id = x.city_id WHERE {$w} ORDER BY x.votes_obtained DESC", $p);
    $out = [];
    foreach ($rows as $r) {
        $out[$r['geo_key']] = $r;
    }
    return $out;
}

$rows = perf_rows($yearId, $level, $regionId, $provinceId, $includePending);
$cmp = $compareId ? perf_rows($compareId, $level, $regionId, $provinceId, $includePending) : [];
$national = $yearId ? q_one("SELECT x.* FROM election_results x WHERE x.election_year_id = ? AND x.geo_level = 'national' AND x.archived_at IS NULL AND x.verification_status = 'verified'", [$yearId]) : null;
$label = function (array $r): string {
    return $r['geo_level'] === 'region' ? $r['region_name'] : ($r['geo_level'] === 'province' ? $r['province_name'] : $r['city_name']);
};

if (get('export') === 'csv') {
    require_perm('elections.export');
    q_insert('export_logs', ['user_id' => uid(), 'module' => 'election_performance', 'filters' => "year={$yearId}; level={$level}; region={$regionId}; province={$provinceId}", 'row_count' => count($rows), 'is_sensitive' => 0, 'ip_address' => client_ip(), 'created_at' => now()]);
    audit_log('export', 'election_results', null, 'Exported election performance (' . count($rows) . ' rows)');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="election-performance-' . ($years[$yearId] ?? '') . '-' . $level . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Area', 'Level', 'Region', 'Election year', 'Votes obtained', 'Total votes cast', 'Vote share', 'Rank', 'Comparison year', 'Comparison votes', 'Change', 'Verification', 'Source']);
    foreach ($rows as $k => $r) {
        $c = $cmp[$k] ?? null;
        fputcsv($out, array_map('csv_safe', [$label($r), $level, $r['region_name'], $years[$yearId], $r['votes_obtained'], $r['total_votes_cast'], fmt_pct($r['votes_obtained'], $r['total_votes_cast']), $r['rank_position'],
            $compareId ? $years[$compareId] : '', $c['votes_obtained'] ?? '', $c ? (int) $r['votes_obtained'] - (int) $c['votes_obtained'] : '', $r['verification_status'], $r['source_title']]));
    }
    fclose($out);
    exit;
}

$crumbs = [['Election performance', url('admin/election-performance.php', ['year' => $yearId, 'compare' => $compareId ?: null])]];
if ($regionId) {
    $crumbs[] = [q_val('SELECT name FROM regions WHERE id = ?', [$regionId]), url('admin/election-performance.php', ['year' => $yearId, 'compare' => $compareId ?: null, 'region' => $regionId])];
}
if ($provinceId) {
    $crumbs[] = [q_val('SELECT name FROM provinces WHERE id = ?', [$provinceId]), null];
}
admin_start('Election performance', $crumbs);
$actions = '<button class="btn btn-secondary" type="button" data-print>Print report</button>' . (can('elections.export') && $rows ? '<a class="btn btn-secondary" href="' . e(current_url(['export' => 'csv'])) . '">Export CSV</a>' : '') . (can('elections.create') ? '<a class="btn btn-primary" href="' . e(url('admin/election-results.php', ['action' => 'create'])) . '">Encode result</a>' : '');
page_bar('Election performance', 'Aggregate results from cited official sources, by election year and area. ' . ($includePending ? 'Pending figures are included and labeled.' : 'Verified figures only.'), $actions);
?>
<form class="filters" method="get" aria-label="Election performance filters">
    <div class="filter"><label for="e_y">Election year</label><?= select_html('year', $years, $yearId, ['id' => 'e_y'], null) ?></div>
    <div class="filter"><label for="e_c">Compare with</label><?= select_html('compare', $years, $compareId ?: '', ['id' => 'e_c'], 'No comparison') ?></div>
    <div class="filter"><label for="e_r">Region</label><?= select_html('region', regions_list(), $regionId ?: '', ['id' => 'e_r', 'data-geo' => 'region', 'data-geo-group' => 'ep'], 'All regions') ?></div>
    <div class="filter"><label for="e_p">Province</label><?= select_html('province', provinces_for($regionId ?: null), $provinceId ?: '', ['id' => 'e_p', 'data-geo' => 'province', 'data-geo-group' => 'ep'], 'All provinces') ?></div>
    <?php if (!$regionId): ?><div class="filter"><label for="e_l">Level</label><?= select_html('level', ['region' => 'Regions', 'province' => 'Provinces', 'city' => 'Cities and municipalities'], $level, ['id' => 'e_l'], null) ?></div><?php endif; ?>
    <div class="filter"><label for="e_pd">Figures</label><?= select_html('pending', ['1' => 'Verified and pending'], get('pending'), ['id' => 'e_pd'], 'Verified only') ?></div>
    <div class="filter filter-actions"><button class="btn btn-primary" type="submit">Show</button></div>
</form>
<?php
if (!$years) {
    echo '<div class="empty"><h2>No election years yet</h2><p>Add an election year and an official source, then encode the results.</p></div>';
    admin_end();
    exit;
}
$sumVotes = array_sum(array_column($rows, 'votes_obtained'));
$sumCmp = array_sum(array_column($cmp, 'votes_obtained'));
echo '<div class="stat-row">';
echo '<div class="stat"><span class="stat-num">' . e($years[$yearId] ?? '') . '</span><span class="stat-label">Election year</span></div>';
if ($national && !$regionId && !$provinceId) {
    echo '<div class="stat"><span class="stat-num">' . fmt_num($national['votes_obtained']) . '</span><span class="stat-label">National votes (verified)</span></div>';
    if ($national['total_votes_cast']) {
        echo '<div class="stat"><span class="stat-num">' . fmt_pct($national['votes_obtained'], $national['total_votes_cast']) . '</span><span class="stat-label">National vote share</span></div>';
    }
    if ($national['rank_position']) {
        echo '<div class="stat"><span class="stat-num">' . (int) $national['rank_position'] . '</span><span class="stat-label">National rank</span></div>';
    }
}
echo '<div class="stat"><span class="stat-num">' . fmt_num(count($rows)) . '</span><span class="stat-label">Areas with results (' . e(['region' => 'regions', 'province' => 'provinces', 'city' => 'cities/municipalities'][$level]) . ')</span></div>';
echo '<div class="stat"><span class="stat-num">' . fmt_num($sumVotes) . '</span><span class="stat-label">Sum of listed area votes</span></div>';
if ($compareId) {
    $diff = $sumVotes - $sumCmp;
    echo '<div class="stat"><span class="stat-num">' . ($diff >= 0 ? '+' : '') . fmt_num($diff) . '</span><span class="stat-label">Change from ' . e($years[$compareId]) . ' for the same areas</span></div>';
}
echo '</div>';

if (!$rows) {
    echo '<div class="empty"><h2>No ' . ($includePending ? '' : 'verified ') . 'results for this selection</h2><p>Try another year, level or region' . ($includePending ? '' : ', or include pending figures') . '.</p></div>';
    admin_end();
    exit;
}
$top = array_slice($rows, 0, 20, true);
echo '<section class="panel"><h2 class="panel-title">' . ($compareId ? 'Votes obtained, ' . e($years[$yearId]) . ' compared with ' . e($years[$compareId]) : 'Votes obtained by area, ' . e($years[$yearId])) . (count($rows) > 20 ? ' (top 20)' : '') . '</h2>';
if ($compareId) {
    $labels = $a = $b = [];
    foreach ($top as $k => $r) {
        $labels[$k] = $label($r);
        $a[$k] = (int) $r['votes_obtained'];
        if (isset($cmp[$k])) {
            $b[$k] = (int) $cmp[$k]['votes_obtained'];
        }
    }
    echo chart_compare($labels, $a, $b, (string) $years[$yearId], (string) $years[$compareId], 'Votes by area, two election years');
} else {
    $data = [];
    foreach ($top as $r) {
        $data[$label($r)] = (int) $r['votes_obtained'];
    }
    echo chart_hbar($data, 'Votes obtained by area', ['label_width' => 190]);
}
echo '</section>';
echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Area</th><th scope="col" class="num">Votes</th><th scope="col" class="num">Total votes cast</th><th scope="col" class="num">Share</th><th scope="col" class="num">Rank</th>'
    . ($compareId ? '<th scope="col" class="num">' . e($years[$compareId]) . ' votes</th><th scope="col" class="num">Change</th>' : '') . '<th scope="col">Verification</th><th scope="col">Source</th></tr></thead><tbody>';
foreach ($rows as $k => $r) {
    $name = $label($r);
    $drill = '';
    if ($r['geo_level'] === 'region') {
        $drill = url('admin/election-performance.php', ['year' => $yearId, 'compare' => $compareId ?: null, 'region' => $r['region_id'], 'pending' => $includePending ? '1' : null]);
    } elseif ($r['geo_level'] === 'province') {
        $drill = url('admin/election-performance.php', ['year' => $yearId, 'compare' => $compareId ?: null, 'region' => $r['region_id'], 'province' => $r['province_id'], 'pending' => $includePending ? '1' : null]);
    }
    $c = $cmp[$k] ?? null;
    $chg = $c ? (int) $r['votes_obtained'] - (int) $c['votes_obtained'] : null;
    echo '<tr><td data-label="Area">' . ($drill ? '<a class="row-link" href="' . e($drill) . '">' . e($name) . '</a>' : '<strong>' . e($name) . '</strong>') . ($r['geo_level'] !== 'region' ? '<span class="sub">' . e($r['short_name']) . '</span>' : '') . '</td>'
        . '<td data-label="Votes" class="num">' . fmt_num($r['votes_obtained']) . '</td><td data-label="Total cast" class="num">' . fmt_num($r['total_votes_cast']) . '</td>'
        . '<td data-label="Share" class="num">' . e(fmt_pct($r['votes_obtained'], $r['total_votes_cast'])) . '</td><td data-label="Rank" class="num">' . e($r['rank_position'] ?? '') . '</td>'
        . ($compareId ? '<td data-label="Comparison" class="num">' . ($c ? fmt_num($c['votes_obtained']) : '<span class="muted">No data</span>') . '</td><td data-label="Change" class="num">' . ($chg === null ? '' : status_text($chg >= 0 ? 'approved' : 'rejected', ($chg >= 0 ? '+' : '') . fmt_num($chg))) . '</td>' : '')
        . '<td data-label="Verification">' . status_text($r['verification_status']) . '</td><td data-label="Source"><a href="' . e(url('admin/election-results.php', ['action' => 'view', 'id' => $r['id']])) . '">' . e(excerpt($r['source_title'], 40)) . '</a></td></tr>';
}
echo '</tbody></table></div>';
echo '<p class="meta-line">Generated ' . e(fmt_datetime(now())) . ' by ' . e(current_user()['full_name']) . '. Results are aggregate figures from the cited sources. No individual or member-level data is used.</p>';
admin_end();
