<?php
/**
 * Authenticated search-as-you-type lookups. Results are limited to the
 * caller's permissions and data scope; only ids and display labels are returned.
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/forms.php';

if (!current_user()) {
    json_out(['items' => [], 'error' => 'Not signed in'], 401);
}
no_cache_headers();
$type = get('type');
$q = mb_substr(get('q'), 0, 60);
$items = [];
if (mb_strlen($q) < 2) {
    json_out(['items' => []]);
}
$like = like_escape($q);
switch ($type) {
    case 'coop':
        if (!can_any(['cooperatives.view', 'personnel.create', 'members.create', 'activities.create', 'point_persons.create', 'content.edit'])) {
            json_out(['items' => []], 403);
        }
        $p = [$like, $like, $like];
        $scope = scope_sql(['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'], $p);
        if (can('content.edit') && !can('cooperatives.view')) {
            $scope = '1=1';
            $p = [$like, $like, $like];
        }
        foreach (q_all("SELECT c.id, c.name, c.reference_no, cm.name AS city FROM cooperatives c LEFT JOIN cities_municipalities cm ON cm.id = c.city_id
                        WHERE (c.name LIKE ? OR c.reference_no LIKE ? OR c.cda_registration_no LIKE ?) AND c.archived_at IS NULL AND {$scope} ORDER BY c.name LIMIT 15", $p) as $r) {
            $items[] = ['id' => (int) $r['id'], 'label' => $r['name'] . ' (' . $r['reference_no'] . ')' . ($r['city'] ? ', ' . $r['city'] : '')];
        }
        break;
    case 'person':
        if (!can_any(['persons.view', 'persons.create'])) {
            json_out(['items' => []], 403);
        }
        $p = [$like, $like];
        $where = '(x.last_name LIKE ? OR CONCAT(x.first_name, \' \', x.last_name) LIKE ?) AND x.archived_at IS NULL';
        if (!scope_is_national()) {
            $p1 = [];
            $coop = scope_sql(['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'], $p1);
            $p2 = [];
            $amb = scope_sql(['region' => 'a.region_id', 'province' => 'a.province_id', 'city' => 'a.city_id', 'coop' => 'a.cooperative_id'], $p2);
            $where .= " AND (x.created_by = ? OR EXISTS (SELECT 1 FROM cooperative_personnel cp JOIN cooperatives c ON c.id = cp.cooperative_id WHERE cp.person_id = x.id AND {$coop})
                        OR EXISTS (SELECT 1 FROM cnpl_point_persons pp JOIN cooperatives c ON c.id = pp.cooperative_id WHERE pp.person_id = x.id AND {$coop})
                        OR EXISTS (SELECT 1 FROM ambassadors_focal_persons a WHERE a.person_id = x.id AND {$amb}))";
            $p = array_merge($p, [(int) uid()], $p1, $p1, $p2);
        }
        foreach (q_all("SELECT x.id, x.first_name, x.middle_name, x.last_name, x.suffix, x.email_enc FROM persons x WHERE {$where} ORDER BY x.last_name, x.first_name LIMIT 15", $p) as $r) {
            $email = decrypt_value($r['email_enc']);
            $items[] = ['id' => (int) $r['id'], 'label' => full_name($r) . ($email ? ' (' . mask_email($email) . ')' : '')];
        }
        break;
    case 'user':
        if (!can_any(['users.view', 'point_persons.edit', 'ambassadors.edit'])) {
            json_out(['items' => []], 403);
        }
        foreach (q_all("SELECT id, full_name, username FROM users WHERE (full_name LIKE ? OR username LIKE ?) AND account_status = 'active' ORDER BY full_name LIMIT 15", [$like, $like]) as $r) {
            $items[] = ['id' => (int) $r['id'], 'label' => $r['full_name'] . ' (' . $r['username'] . ')'];
        }
        break;
    default:
        json_out(['items' => []], 400);
}
json_out(['items' => $items]);
