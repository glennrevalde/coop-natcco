<?php
/**
 * Philippine geographic reference data (PSGC): regions, provinces, cities and municipalities.
 * Highly urbanized and independent cities have no province.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';

require_perm('geo.view');
$regionId = get_int('region');
$provinceId = get_int('province');

if (is_post()) {
    require_perm('geo.manage');
    $do = post('do');
    $name = mb_substr(post('name'), 0, 150);
    $code = preg_replace('/[^0-9]/', '', post('psgc_code'));
    try {
        if ($name === '' || $code === '' || strlen($code) > 10) {
            throw new ValidationError('Enter the official name and a PSGC code of up to 10 digits.');
        }
        if ($do === 'add_province') {
            if (!q_val('SELECT id FROM regions WHERE id = ?', [$regionId])) {
                throw new ValidationError('Choose a region first.');
            }
            $id = q_insert('provinces', ['region_id' => $regionId, 'psgc_code' => $code, 'name' => $name, 'created_at' => now()]);
            audit_log('create', 'geo', $id, 'Added province ' . $name);
        } elseif ($do === 'add_city') {
            $pid = post_int('province_id') ?: null;
            if (!q_val('SELECT id FROM regions WHERE id = ?', [$regionId]) || ($pid && !q_val('SELECT id FROM provinces WHERE id = ? AND region_id = ?', [$pid, $regionId]))) {
                throw new ValidationError('Choose a valid region and province.');
            }
            $type = post('lgu_type') === 'city' ? 'city' : 'municipality';
            $id = q_insert('cities_municipalities', ['region_id' => $regionId, 'province_id' => $pid, 'psgc_code' => $code, 'name' => $name, 'lgu_type' => $type,
                'city_class' => mb_substr(post('city_class'), 0, 20) ?: null, 'is_independent' => $pid ? 0 : 1, 'created_at' => now()]);
            audit_log('create', 'geo', $id, 'Added city/municipality ' . $name);
        } elseif ($do === 'rename') {
            $level = post('level') === 'province' ? 'provinces' : 'cities_municipalities';
            $id = post_int('id');
            q('UPDATE ' . $level . ' SET name = ?, psgc_code = ?, updated_at = ? WHERE id = ?', [$name, $code, now(), $id]);
            audit_log('update', 'geo', $id, 'Updated ' . $level . ' record to ' . $name);
        }
        flash('success', 'Geographic reference data saved.');
    } catch (ValidationError $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        flash('error', 'That PSGC code is already used.');
    }
    redirect(current_url());
}

admin_start('Geographic data', [['Geographic data', null]]);
page_bar('Philippine geographic data', 'Based on the Philippine Standard Geographic Code. Highly urbanized and independent cities are recorded without a province.');
echo flash_render();
$regions = q_all('SELECT r.*, (SELECT COUNT(*) FROM provinces p WHERE p.region_id = r.id) prov, (SELECT COUNT(*) FROM cities_municipalities c WHERE c.region_id = r.id) lgus,
                  (SELECT COUNT(*) FROM cooperatives co WHERE co.region_id = r.id AND co.archived_at IS NULL) coops FROM regions r ORDER BY r.sort_order');
echo '<div class="two-col"><section class="panel"><h2 class="panel-title">Regions</h2><ul class="mini-list">';
foreach ($regions as $r) {
    echo '<li><span class="ml-main"><a href="' . e(url('admin/geo.php', ['region' => $r['id']])) . '"' . ((int) $r['id'] === $regionId ? ' aria-current="true"' : '') . '>' . e($r['name']) . '</a><span class="sub muted small"> PSGC ' . e($r['psgc_code']) . '</span></span>'
        . '<span class="ml-meta">' . (int) $r['prov'] . ' prov., ' . (int) $r['lgus'] . ' LGUs, ' . (int) $r['coops'] . ' coops</span></li>';
}
echo '</ul></section><div>';
if ($regionId) {
    $reg = q_one('SELECT * FROM regions WHERE id = ?', [$regionId]);
    $provs = q_all('SELECT p.*, (SELECT COUNT(*) FROM cities_municipalities c WHERE c.province_id = p.id) lgus FROM provinces p WHERE p.region_id = ? ORDER BY p.name', [$regionId]);
    echo '<section class="panel"><h2 class="panel-title">' . e($reg['name']) . '</h2><h3 class="small muted">Provinces</h3><ul class="mini-list">';
    foreach ($provs as $p) {
        echo '<li><span class="ml-main"><a href="' . e(url('admin/geo.php', ['region' => $regionId, 'province' => $p['id']])) . '">' . e($p['name']) . '</a></span><span class="ml-meta">' . (int) $p['lgus'] . ' LGUs</span></li>';
    }
    if (!$provs) {
        echo '<li class="muted">No provinces (all LGUs in this region are independent).</li>';
    }
    echo '</ul>';
    $where = $provinceId ? 'c.province_id = ?' : 'c.region_id = ? AND c.province_id IS NULL';
    $cities = q_all("SELECT c.* FROM cities_municipalities c WHERE {$where} ORDER BY c.name", [$provinceId ?: $regionId]);
    echo '<h3 class="small muted">' . ($provinceId ? 'Cities and municipalities of ' . e(q_val('SELECT name FROM provinces WHERE id = ?', [$provinceId])) : 'Independent cities and LGUs without a province') . '</h3><ul class="mini-list">';
    foreach ($cities as $c) {
        echo '<li><span class="ml-main">' . e($c['name']) . '<span class="sub muted small"> ' . e(ucfirst($c['lgu_type'])) . ($c['city_class'] ? ', ' . e($c['city_class']) : '') . ', PSGC ' . e($c['psgc_code']) . '</span></span></li>';
    }
    if (!$cities) {
        echo '<li class="muted">None.</li>';
    }
    echo '</ul></section>';
    if (can('geo.manage')) {
        echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="do" value="add_city"><h2 class="panel-title">Add a city or municipality</h2><div class="grid">'
            . form_field('name', ['label' => 'Official name', 'required' => true, 'max' => 150], '') . form_field('psgc_code', ['label' => 'PSGC code', 'required' => true, 'max' => 10], '')
            . form_field('province_id', ['label' => 'Province', 'type' => 'select', 'options' => array_column($provs, 'name', 'id'), 'placeholder' => 'None (independent city)'], $provinceId ?: '')
            . form_field('lgu_type', ['label' => 'Type', 'type' => 'select', 'options' => ['municipality' => 'Municipality', 'city' => 'City'], 'placeholder' => null], 'municipality')
            . form_field('city_class', ['label' => 'City class', 'max' => 20, 'help' => 'For example HUC, ICC or CC.'], '')
            . '</div><div class="form-actions"><button class="btn btn-secondary" type="submit">Add</button></div></form>';
        echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="do" value="add_province"><h2 class="panel-title">Add a province</h2><div class="grid">'
            . form_field('name', ['label' => 'Official name', 'required' => true, 'max' => 150], '') . form_field('psgc_code', ['label' => 'PSGC code', 'required' => true, 'max' => 10], '')
            . '</div><div class="form-actions"><button class="btn btn-secondary" type="submit">Add</button></div></form>';
    }
} else {
    echo '<div class="empty"><p>Choose a region to see its provinces, cities and municipalities.</p></div>';
}
echo '</div></div>';
admin_end();
