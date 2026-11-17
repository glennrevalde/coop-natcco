<?php
/**
 * Helpers for the public website pages.
 */
declare(strict_types=1);

require_once APP_ROOT . '/includes/layout.php';
require_once APP_ROOT . '/includes/content.php';
require_once APP_ROOT . '/includes/forms.php';

/** WHERE for published content; preview mode shows drafts to signed-in content staff. */
function pub_where(string $alias, array &$p): string
{
    if (can_preview()) {
        return "{$alias}.archived_at IS NULL";
    }
    $p[] = now();
    return public_published_sql($alias);
}

function date_tile(string $dt): string
{
    $t = strtotime($dt);
    return '<span class="date-tile" aria-hidden="true"><span class="dt-m">' . date('M', $t) . '</span><span class="dt-d">' . date('j', $t) . '</span></span>';
}

function post_card(array $p, bool $large = false): string
{
    $types = post_type_labels();
    $img = $p['cover_media_id'] ? '<span class="card-img"><img src="' . e(media_url((int) $p['cover_media_id'], !$large)) . '" alt="" loading="lazy"></span>' : '<span class="card-img card-img-empty" aria-hidden="true"><img src="' . e(asset('img/logo.jpg')) . '" alt="" loading="lazy"></span>';
    $date = $p['publish_at'] ?: ($p['published_at'] ?: $p['created_at']);
    return '<article class="card post-card' . ($large ? ' post-card-lg' : '') . '"><a class="card-link" href="' . e(url('post.php', ['slug' => $p['slug']])) . '">' . $img
        . '<span class="card-body"><span class="card-meta">' . e($types[$p['post_type']] ?? '') . ' published <time datetime="' . e(date('c', strtotime($date))) . '">' . e(fmt_date($date)) . '</time></span>'
        . '<span class="card-title">' . e($p['title']) . '</span>'
        . ($p['summary'] ? '<span class="card-text">' . e(excerpt($p['summary'], $large ? 220 : 130)) . '</span>' : '') . '</span></a></article>';
}

function event_item(array $e): string
{
    return '<li class="event-item"><a href="' . e(url('event.php', ['slug' => $e['slug']])) . '">' . date_tile($e['starts_at'])
        . '<span class="ev-body"><span class="ev-title">' . e($e['title']) . '</span><span class="ev-meta">' . e(date('l, g:i A', strtotime($e['starts_at']))) . ($e['location_name'] ? ', ' . e($e['location_name']) : '')
        . ($e['event_status'] !== 'scheduled' ? ' <strong class="st st-' . ($e['event_status'] === 'cancelled' ? 'bad' : 'warn') . '">' . e(ucfirst($e['event_status'])) . '</strong>' : '') . '</span></span></a></li>';
}

function section_head(string $title, string $link = '', string $linkText = 'View all', string $id = ''): string
{
    return '<div class="sec-head"><h2' . ($id ? ' id="' . e($id) . '"' : '') . '>' . e($title) . '</h2>' . ($link ? '<a class="more" href="' . e($link) . '">' . e($linkText) . icon('arrow') . '</a>' : '') . '</div>';
}

function public_page_row(string $slug): ?array
{
    $p = [$slug];
    $w = pub_where('x', $p);
    return q_one("SELECT x.* FROM pages x WHERE x.slug = ? AND {$w}", $p);
}

function empty_state(string $title, string $text): string
{
    return '<div class="empty-state"><h2>' . e($title) . '</h2><p>' . e($text) . '</p></div>';
}

function public_pager(int $total, int $page, int $per): string
{
    return str_replace('class="pager"', 'class="pager pub-pager"', pager_html($total, $page, $per));
}

function not_found(): void
{
    http_response_code(404);
    public_start('Page not found', ['crumbs' => [['Page not found', null]]]);
    echo '<div class="wrap section">' . empty_state('We could not find that page', 'It may have moved, or it is not published yet. Try the search or the menu above.') . '<p class="center"><a class="btn btn-primary" href="' . e(url('index.php')) . '">Go to the home page</a> <a class="btn btn-secondary" href="' . e(url('search.php')) . '">Search the site</a></p></div>';
    public_end();
    exit;
}

/**
 * News-style listing used by news, announcements, achievements, newsletters, programs and activities.
 */
function render_post_listing(array $types, string $title, string $intro, array $tabs = []): void
{
    [$page, $per, $offset] = pager_params(12);
    $p = [];
    $w = [pub_where('x', $p)];
    $w[] = sql_in_str('x.post_type', $types, $p);
    $cat = get_int('category');
    if ($cat) {
        $w[] = 'x.category_id = ?';
        $p[] = $cat;
    }
    $year = get_int('year');
    if ($year >= 2000 && $year <= 2100) {
        $w[] = 'YEAR(COALESCE(x.publish_at, x.published_at, x.created_at)) = ?';
        $p[] = $year;
    }
    $q = mb_substr(get('q'), 0, 80);
    if ($q !== '') {
        $w[] = '(x.title LIKE ? OR x.summary LIKE ?)';
        array_push($p, like_escape($q), like_escape($q));
    }
    $where = implode(' AND ', $w);
    $total = (int) q_val("SELECT COUNT(*) FROM posts x WHERE {$where}", $p);
    $rows = q_all("SELECT x.* FROM posts x WHERE {$where} ORDER BY COALESCE(x.publish_at, x.published_at, x.created_at) DESC LIMIT {$per} OFFSET {$offset}", $p);
    $yp = [];
    $years = q_col('SELECT DISTINCT YEAR(COALESCE(x.publish_at, x.published_at, x.created_at)) y FROM posts x WHERE ' . pub_where('x', $yp) . ' AND ' . sql_in_str('x.post_type', $types, $yp) . ' ORDER BY y DESC', $yp);
    public_start($title, ['crumbs' => [[$title, null]], 'sub' => e($intro)]);
    echo '<div class="wrap section">';
    if ($tabs) {
        echo '<nav class="tabs" aria-label="Content types">';
        foreach ($tabs as $file => $label) {
            echo '<a href="' . e(url($file)) . '"' . (basename($_SERVER['SCRIPT_NAME']) === $file ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
        }
        echo '</nav>';
    }
    echo '<form class="pub-filters" method="get" role="search" aria-label="Filter ' . e($title) . '">'
        . '<div class="filter"><label for="pf_q">Search</label><input type="search" id="pf_q" name="q" value="' . e($q) . '" maxlength="80"></div>'
        . '<div class="filter"><label for="pf_c">Category</label>' . select_html('category', q_pairs("SELECT id, name FROM content_categories WHERE scope = 'post' AND is_active = 1 ORDER BY sort_order"), $cat ?: '', ['id' => 'pf_c'], 'All categories') . '</div>'
        . '<div class="filter"><label for="pf_y">Year</label>' . select_html('year', array_combine($years, $years) ?: [], $year ?: '', ['id' => 'pf_y'], 'All years') . '</div>'
        . '<button class="btn btn-primary" type="submit">Filter</button></form>';
    if (!$rows) {
        echo empty_state('Nothing published yet', $q !== '' || $cat || $year ? 'No items match your filters.' : 'Items will appear here once they are published.');
    } else {
        echo '<div class="grid-3">';
        foreach ($rows as $r) {
            echo post_card($r);
        }
        echo '</div>' . public_pager($total, $page, $per);
    }
    echo '</div>';
    public_end();
}

function sql_in_str(string $col, array $values, array &$params): string
{
    if (!$values) {
        return '0=1';
    }
    foreach ($values as $v) {
        $params[] = (string) $v;
    }
    return $col . ' IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
}

const UPDATE_TABS = ['news.php' => 'News', 'announcements.php' => 'Announcements', 'achievements.php' => 'Achievements', 'newsletters.php' => 'Newsletters', 'gallery.php' => 'Photos and videos'];
