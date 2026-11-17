<?php
/**
 * Public cooperative directory: only verified, approved, active cooperatives that chose
 * to be listed. Only name, type and location are shown.
 */
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

[$page, $per, $offset] = pager_params(25);
$p = [];
$w = ["c.is_public_listing = 1", "c.verification_status = 'verified'", "c.review_status = 'approved'", "c.org_status = 'active'", 'c.archived_at IS NULL'];
$region = get_int('region');
$province = get_int('province');
$type = get_int('type');
$q = mb_substr(get('q'), 0, 80);
if ($region) {
    $w[] = 'c.region_id = ?';
    $p[] = $region;
}
if ($province) {
    $w[] = 'c.province_id = ?';
    $p[] = $province;
}
if ($type) {
    $w[] = 'c.cooperative_type_id = ?';
    $p[] = $type;
}
if ($q !== '') {
    $w[] = 'c.name LIKE ?';
    $p[] = like_escape($q);
}
$where = implode(' AND ', $w);
$total = (int) q_val("SELECT COUNT(*) FROM cooperatives c WHERE {$where}", $p);
$rows = q_all("SELECT c.name, ct.name AS type_name, r.short_name, p.name AS province_name, cm.name AS city_name FROM cooperatives c LEFT JOIN cooperative_types ct ON ct.id = c.cooperative_type_id
               LEFT JOIN regions r ON r.id = c.region_id LEFT JOIN provinces p ON p.id = c.province_id LEFT JOIN cities_municipalities cm ON cm.id = c.city_id
               WHERE {$where} ORDER BY c.name LIMIT {$per} OFFSET {$offset}", $p);
$byRegion = q_all("SELECT r.short_name, COUNT(*) n FROM cooperatives c JOIN regions r ON r.id = c.region_id WHERE c.is_public_listing = 1 AND c.verification_status = 'verified' AND c.review_status = 'approved' AND c.org_status = 'active' AND c.archived_at IS NULL GROUP BY r.id, r.short_name, r.sort_order ORDER BY r.sort_order");
public_start('Cooperative directory', ['crumbs' => [['Cooperatives', null]], 'sub' => 'Verified primary cooperatives that participate with COOP NATCCO Partylist and agreed to be listed.']);
echo '<div class="wrap section"><nav class="tabs" aria-label="Cooperatives"><a href="' . e(url('cooperatives.php')) . '" aria-current="page">Cooperative directory</a><a href="' . e(url('programs.php')) . '">Programs and activities</a></nav>';
echo '<form class="pub-filters" method="get" role="search" aria-label="Find a cooperative">'
    . '<div class="filter"><label for="cd_q">Cooperative name</label><input type="search" id="cd_q" name="q" value="' . e($q) . '" maxlength="80"></div>'
    . '<div class="filter"><label for="cd_r">Region</label>' . select_html('region', regions_list(), $region ?: '', ['id' => 'cd_r', 'data-geo' => 'region', 'data-geo-group' => 'cd'], 'All regions') . '</div>'
    . '<div class="filter"><label for="cd_p">Province</label>' . select_html('province', provinces_for($region ?: null), $province ?: '', ['id' => 'cd_p', 'data-geo' => 'province', 'data-geo-group' => 'cd'], 'All provinces') . '</div>'
    . '<div class="filter"><label for="cd_t">Type</label>' . select_html('type', q_pairs('SELECT id, name FROM cooperative_types WHERE is_active = 1 ORDER BY name'), $type ?: '', ['id' => 'cd_t'], 'All types') . '</div>'
    . '<button class="btn btn-primary" type="submit">Search</button></form>';
if (!$rows) {
    echo empty_state('No cooperatives found', $total === 0 && !$q && !$region && !$type ? 'Verified cooperatives will be listed here.' : 'Try a different name, region or type.');
} else {
    echo '<p class="muted">' . fmt_num($total) . ' cooperative' . ($total === 1 ? '' : 's') . '</p><div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Cooperative</th><th scope="col">Type</th><th scope="col">City or municipality</th><th scope="col">Province</th><th scope="col">Region</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td class="td-primary" data-label="Cooperative"><strong>' . e($r['name']) . '</strong></td><td data-label="Type">' . e($r['type_name']) . '</td><td data-label="City">' . e($r['city_name']) . '</td><td data-label="Province">' . e($r['province_name'] ?: 'Independent city') . '</td><td data-label="Region">' . e($r['short_name']) . '</td></tr>';
    }
    echo '</tbody></table></div>' . public_pager($total, $page, $per);
}
if ($byRegion) {
    echo '<div class="mt box"><h2 class="h3">Listed cooperatives by region</h2><ul class="region-counts">';
    foreach ($byRegion as $b) {
        echo '<li><span>' . e($b['short_name']) . '</span><strong>' . fmt_num($b['n']) . '</strong></li>';
    }
    echo '</ul></div>';
}
echo '<p class="muted small mt">Is your cooperative missing or are its details out of date? <a href="' . e(url('helpdesk.php')) . '">Send a help desk request</a> under "Registration and Records Update".</p></div>';
public_end();
