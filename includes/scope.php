<?php
/**
 * Geographic and organizational data scope.
 *
 * A user's scope comes from user_scope_assignments plus assignment-based roles
 * (point persons see their assigned cooperatives; ambassadors and focal persons
 * see their designated area). National scope (or Super Administrator) sees all.
 * Every scoped query and every create/edit is checked on the server.
 */
declare(strict_types=1);

function user_scope(?int $userId = null): array
{
    static $cache = [];
    $userId = $userId ?? uid();
    if (!$userId) {
        return ['national' => false, 'regions' => [], 'provinces' => [], 'cities' => [], 'coops' => []];
    }
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    $s = ['national' => false, 'regions' => [], 'provinces' => [], 'cities' => [], 'coops' => []];
    foreach (user_role_rows($userId) as $r) {
        if ($r['scope_level'] === 'global') {
            $s['national'] = true;
        }
    }
    foreach (q_all('SELECT * FROM user_scope_assignments WHERE user_id = ?', [$userId]) as $a) {
        switch ($a['scope_type']) {
            case 'national':
                $s['national'] = true;
                break;
            case 'region':
                $s['regions'][] = (int) $a['region_id'];
                break;
            case 'province':
                $s['provinces'][] = (int) $a['province_id'];
                break;
            case 'city':
                $s['cities'][] = (int) $a['city_id'];
                break;
            case 'cooperative':
                $s['coops'][] = (int) $a['cooperative_id'];
                break;
        }
    }
    // Assignment-based scope
    if (has_role('point_person', $userId)) {
        foreach (q_col("SELECT cooperative_id FROM cnpl_point_persons WHERE user_id = ? AND assignment_status = 'active' AND archived_at IS NULL", [$userId]) as $c) {
            $s['coops'][] = (int) $c;
        }
    }
    if (has_role('ambassador', $userId) || has_role('focal_person', $userId)) {
        foreach (q_all("SELECT cooperative_id, region_id, province_id, city_id FROM ambassadors_focal_persons WHERE user_id = ? AND designation_status = 'active' AND archived_at IS NULL", [$userId]) as $a) {
            if ($a['city_id']) {
                $s['cities'][] = (int) $a['city_id'];
            } elseif ($a['province_id']) {
                $s['provinces'][] = (int) $a['province_id'];
            } elseif ($a['region_id']) {
                $s['regions'][] = (int) $a['region_id'];
            }
            if ($a['cooperative_id']) {
                $s['coops'][] = (int) $a['cooperative_id'];
            }
        }
    }
    foreach (['regions', 'provinces', 'cities', 'coops'] as $k) {
        $s[$k] = array_values(array_unique(array_filter($s[$k])));
    }
    return $cache[$userId] = $s;
}

function scope_is_national(): bool
{
    return user_scope()['national'];
}

/**
 * SQL condition limiting rows to the user's scope.
 * $cols keys: region, province, city, coop (column expressions from code).
 */
function scope_sql(array $cols, array &$params): string
{
    return scope_sql_for(user_scope(), $cols, $params);
}

/** Pure form of scope_sql() for a given scope array (used by scope_sql and unit tests). */
function scope_sql_for(array $s, array $cols, array &$params): string
{
    if (!empty($s['national'])) {
        return '1=1';
    }
    $parts = [];
    foreach (['region' => 'regions', 'province' => 'provinces', 'city' => 'cities', 'coop' => 'coops'] as $col => $key) {
        if (!empty($cols[$col]) && !empty($s[$key])) {
            $parts[] = sql_in($cols[$col], $s[$key], $params);
        }
    }
    return $parts ? '(' . implode(' OR ', $parts) . ')' : '0=1';
}

/** Does the scope allow a record located at this place / cooperative? */
function scope_allows(?int $regionId, ?int $provinceId, ?int $cityId, ?int $coopId = null): bool
{
    return scope_allows_for(user_scope(), $regionId, $provinceId, $cityId, $coopId);
}

/** Pure form of scope_allows() for a given scope array. */
function scope_allows_for(array $s, ?int $regionId, ?int $provinceId, ?int $cityId, ?int $coopId = null): bool
{
    if (!empty($s['national'])) {
        return true;
    }
    return ($coopId && in_array($coopId, $s['coops'] ?? [], true))
        || ($cityId && in_array($cityId, $s['cities'] ?? [], true))
        || ($provinceId && in_array($provinceId, $s['provinces'] ?? [], true))
        || ($regionId && in_array($regionId, $s['regions'] ?? [], true));
}

function coop_geo(?int $coopId): ?array
{
    if (!$coopId) {
        return null;
    }
    return q_one('SELECT id, region_id, province_id, city_id, name FROM cooperatives WHERE id = ?', [$coopId]);
}

function scope_allows_coop(?int $coopId): bool
{
    $g = coop_geo($coopId);
    return $g !== null && scope_allows((int) $g['region_id'], $g['province_id'] ? (int) $g['province_id'] : null, $g['city_id'] ? (int) $g['city_id'] : null, (int) $g['id']);
}

function scope_description(?int $userId = null): string
{
    $s = user_scope($userId);
    if ($s['national']) {
        return 'Nationwide';
    }
    $bits = [];
    $p = [];
    if ($s['regions']) {
        $p = [];
        $bits[] = implode(', ', q_col('SELECT short_name FROM regions WHERE ' . sql_in('id', $s['regions'], $p), $p));
    }
    if ($s['provinces']) {
        $p = [];
        $bits[] = implode(', ', q_col('SELECT name FROM provinces WHERE ' . sql_in('id', $s['provinces'], $p), $p));
    }
    if ($s['cities']) {
        $p = [];
        $bits[] = implode(', ', q_col('SELECT name FROM cities_municipalities WHERE ' . sql_in('id', $s['cities'], $p), $p));
    }
    if ($s['coops']) {
        $n = count($s['coops']);
        $bits[] = $n === 1 ? (string) q_val('SELECT name FROM cooperatives WHERE id = ?', [$s['coops'][0]]) : $n . ' cooperatives';
    }
    return $bits ? implode('; ', array_filter($bits)) : 'No data scope assigned';
}
