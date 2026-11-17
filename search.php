<?php
/**
 * Site search across published public content.
 */
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$q = mb_substr(get('q'), 0, 80);
$results = [];
if (mb_strlen($q) >= 2) {
    public_form_limit('site_search', 60, 60);
    $like = like_escape($q);
    $now = now();
    $types = post_type_labels();
    foreach (q_all("SELECT x.slug, x.title, x.summary, x.post_type, COALESCE(x.publish_at, x.published_at) d FROM posts x WHERE " . public_published_sql('x') . " AND (x.title LIKE ? OR x.summary LIKE ? OR x.body LIKE ?) ORDER BY d DESC LIMIT 20", [$now, $like, $like, $like]) as $r) {
        $results[] = [$types[$r['post_type']], $r['title'], url('post.php', ['slug' => $r['slug']]), $r['summary'], $r['d']];
    }
    foreach (q_all("SELECT x.slug, x.title, x.summary, x.starts_at FROM events x WHERE " . public_published_sql('x') . " AND x.visibility = 'public' AND (x.title LIKE ? OR x.summary LIKE ?) ORDER BY x.starts_at DESC LIMIT 10", [$now, $like, $like]) as $r) {
        $results[] = ['Event', $r['title'], url('event.php', ['slug' => $r['slug']]), $r['summary'], $r['starts_at']];
    }
    foreach (q_all("SELECT x.slug, x.title, x.measure_number, x.filing_date FROM legislative_updates x WHERE " . public_published_sql('x') . " AND (x.title LIKE ? OR x.measure_number LIKE ? OR x.sponsors LIKE ?) LIMIT 10", [$now, $like, $like, $like]) as $r) {
        $results[] = ['Legislation', $r['title'], url('legislation.php', ['slug' => $r['slug']]), $r['measure_number'], $r['filing_date']];
    }
    foreach (q_all("SELECT x.slug, x.title, x.summary, x.effective_date FROM policies x WHERE " . public_published_sql('x') . " AND x.visibility = 'public' AND (x.title LIKE ? OR x.summary LIKE ? OR x.reference_no LIKE ?) LIMIT 10", [$now, $like, $like, $like]) as $r) {
        $results[] = ['Policy or document', $r['title'], url('policy.php', ['slug' => $r['slug']]), $r['summary'], $r['effective_date']];
    }
    foreach (q_all("SELECT x.slug, x.title, x.recipient_name, x.award_date FROM awards x WHERE " . public_published_sql('x') . " AND (x.title LIKE ? OR x.recipient_name LIKE ?) LIMIT 10", [$now, $like, $like]) as $r) {
        $results[] = ['Award', $r['title'], url('awards.php', ['slug' => $r['slug']]), $r['recipient_name'], $r['award_date']];
    }
    foreach (q_all("SELECT x.slug, x.title, x.summary FROM pages x WHERE " . public_published_sql('x') . " AND (x.title LIKE ? OR x.body LIKE ?) LIMIT 10", [$now, $like, $like]) as $r) {
        $results[] = ['Page', $r['title'], url('page.php', ['slug' => $r['slug']]), $r['summary'], null];
    }
    foreach (q_all("SELECT x.question, x.answer FROM faqs x WHERE " . public_published_sql('x') . " AND (x.question LIKE ? OR x.answer LIKE ?) LIMIT 10", [$now, $like, $like]) as $r) {
        $results[] = ['FAQ', $r['question'], url('faq.php'), $r['answer'], null];
    }
}
public_start('Search', ['crumbs' => [['Search', null]], 'sub' => 'Search news, events, legislation, documents and pages.']);
echo '<div class="wrap section"><form class="search-big" method="get" role="search"><label for="s_q" class="sr-only">Search the website</label><input type="search" id="s_q" name="q" value="' . e($q) . '" placeholder="What are you looking for?" maxlength="80" autofocus><button class="btn btn-primary" type="submit">Search</button></form>';
if ($q !== '' && mb_strlen($q) < 2) {
    echo '<p class="mt muted">Type at least two characters.</p>';
} elseif ($q !== '') {
    echo '<p class="mt muted" role="status">' . count($results) . ' result' . (count($results) === 1 ? '' : 's') . ' for "' . e($q) . '"</p>';
    if ($results) {
        echo '<ul class="item-list">';
        foreach ($results as $r) {
            echo '<li><span class="result-type">' . e($r[0]) . '</span><br><a class="il-title" href="' . e($r[2]) . '">' . e($r[1]) . '</a>' . ($r[3] ? '<p class="small muted">' . e(excerpt($r[3], 200)) . '</p>' : '') . '</li>';
        }
        echo '</ul>';
    } else {
        echo empty_state('No results', 'Try other words, or browse the menu above.');
    }
}
echo '</div>';
public_end();
