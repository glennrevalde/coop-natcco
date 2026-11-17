<?php
/**
 * Module registry and shared option lists.
 */
declare(strict_types=1);

require_once APP_ROOT . '/includes/crud.php';
require_once APP_ROOT . '/includes/content.php';

function module_def(string $key, array $args = []): array
{
    static $files = [
        'cooperatives' => 'cooperatives', 'personnel' => 'personnel', 'members' => 'members', 'point_persons' => 'point_persons',
        'tasks' => 'tasks', 'ambassadors' => 'ambassadors', 'officers' => 'officers', 'appointments' => 'appointments',
        'activities' => 'activities', 'persons' => 'persons', 'election_results' => 'election_results', 'election_years' => 'election_years',
        'election_sources' => 'election_sources', 'posts' => 'posts', 'pages' => 'pages', 'events' => 'events', 'legislation' => 'legislation',
        'awards' => 'awards', 'policies' => 'policies', 'history' => 'history', 'galleries' => 'galleries', 'faqs' => 'faqs',
        'org_units' => 'org_units', 'positions' => 'positions', 'incidents' => 'incidents', 'privacy_requests' => 'privacy_requests',
        'processing' => 'processing', 'messages' => 'messages', 'menus' => 'menus',
    ];
    if (!isset($files[$key])) {
        throw new InvalidArgumentException('Unknown module ' . $key);
    }
    $ARGS = $args;
    return crud_norm(require APP_ROOT . '/includes/modules/' . $files[$key] . '.php');
}

/** Can the current user see this record (used for private file downloads)? */
function module_record_accessible(string $key, int $id): bool
{
    try {
        $m = module_def($key);
    } catch (Throwable $e) {
        return false;
    }
    if (!can($m['perm'] . '.view')) {
        return false;
    }
    return crud_fetch($m, $id) !== null;
}

const OPT_REVIEW = ['pending' => 'Pending approval', 'approved' => 'Approved', 'rejected' => 'Rejected'];

function opt_coop_types(): array
{
    return q_pairs('SELECT id, name FROM cooperative_types WHERE is_active = 1 ORDER BY sort_order, name');
}

function geo_label(array $r, string $prefix = ''): string
{
    $bits = array_filter([$r[$prefix . 'city_name'] ?? null, $r[$prefix . 'province_name'] ?? null, $r[$prefix . 'region_name'] ?? null]);
    return implode(', ', $bits);
}

function person_label_sql(string $alias = 'pe'): string
{
    return "TRIM(CONCAT({$alias}.first_name, ' ', IFNULL(CONCAT(LEFT({$alias}.middle_name, 1), '. '), ''), {$alias}.last_name, IFNULL(CONCAT(' ', {$alias}.suffix), '')))";
}

/** Common geo field trio for module definitions. */
function geo_fields(string $group = 'g', bool $regionRequired = true, string $prefix = '', string $labelPrefix = ''): array
{
    return [
        $prefix . 'region_id' => ['label' => $labelPrefix . 'Region', 'type' => 'region', 'required' => $regionRequired, 'geo_group' => $group, 'width' => 'third'],
        $prefix . 'province_id' => ['label' => $labelPrefix . 'Province', 'type' => 'province', 'geo_group' => $group, 'region_field' => $prefix . 'region_id', 'width' => 'third',
            'help' => 'Leave blank for highly urbanized or independent cities.'],
        $prefix . 'city_id' => ['label' => $labelPrefix . 'City or municipality', 'type' => 'city', 'geo_group' => $group, 'region_field' => $prefix . 'region_id', 'province_field' => $prefix . 'province_id', 'width' => 'third'],
    ];
}

function related_table(string $title, array $headers, array $rows, string $emptyText, string $addLink = '', string $addLabel = ''): void
{
    echo '<section class="panel"><div class="panel-head"><h2 class="panel-title">' . e($title) . '</h2>' . ($addLink ? '<a class="btn btn-secondary btn-sm" href="' . e($addLink) . '">' . e($addLabel) . '</a>' : '') . '</div>';
    if (!$rows) {
        echo '<p class="muted">' . e($emptyText) . '</p>';
    } else {
        echo '<div class="table-wrap"><table class="table table-cards"><thead><tr>';
        foreach ($headers as $h) {
            echo '<th scope="col">' . e($h) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            foreach (array_values($r) as $i => $cell) {
                echo '<td data-label="' . e($headers[$i] ?? '') . '">' . $cell . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

/** Assign a unique URL slug: provided slugs must be unique; generated ones get a numeric suffix. */
function unique_slug(string $table, array &$data, ?array $old, array &$errors, string $from = 'title'): void
{
    $provided = (string) ($data['slug'] ?? '');
    $exists = fn($s) => q_val('SELECT id FROM ' . ident($table) . ' WHERE slug = ?' . ($old ? ' AND id <> ' . (int) $old['id'] : ''), [$s]);
    if ($provided !== '') {
        if ($exists($provided)) {
            $errors['slug'] = 'This web address is already used.';
        }
        return;
    }
    if ($old && !empty($old['slug'])) {
        $data['slug'] = $old['slug'];
        return;
    }
    $base = slugify((string) ($data[$from] ?? 'item'), 150);
    $slug = $base;
    $i = 2;
    while ($exists($slug)) {
        $slug = $base . '-' . $i++;
    }
    $data['slug'] = $slug;
}

function posts_notify_announcement(int $id): void
{
    $p = q_one('SELECT title, slug FROM posts WHERE id = ?', [$id]);
    if (!$p) {
        return;
    }
    foreach (q_col("SELECT id FROM users WHERE account_status = 'active'") as $u) {
        notify_user((int) $u, 'announcement', 'New official announcement: ' . $p['title'], '', url('post.php', ['slug' => $p['slug']]));
    }
}
