<?php
/**
 * Public geographic reference data for cascading selects (JSON).
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: public, max-age=3600');
$type = get('type');
if (!in_array($type, ['regions', 'provinces', 'cities'], true)) {
    json_out(['error' => 'Unknown type. Use regions, provinces or cities.'], 400);
}
$region = get_int('region');
$province = get_int('province');
$items = [];
if ($type === 'provinces' && $region) {
    foreach (q_all('SELECT id, name FROM provinces WHERE region_id = ? ORDER BY name', [$region]) as $r) {
        $items[] = ['id' => (int) $r['id'], 'name' => $r['name']];
    }
} elseif ($type === 'cities' && ($region || $province)) {
    if ($province) {
        $rows = q_all('SELECT id, name FROM cities_municipalities WHERE province_id = ? ORDER BY name', [$province]);
    } else {
        $rows = q_all("SELECT id, CONCAT(name, IF(province_id IS NULL, ' (independent)', '')) AS name FROM cities_municipalities WHERE region_id = ? ORDER BY name", [$region]);
    }
    foreach ($rows as $r) {
        $items[] = ['id' => (int) $r['id'], 'name' => $r['name']];
    }
} elseif ($type === 'regions') {
    foreach (q_all('SELECT id, name FROM regions ORDER BY sort_order') as $r) {
        $items[] = ['id' => (int) $r['id'], 'name' => $r['name']];
    }
}
json_out(['items' => $items]);
