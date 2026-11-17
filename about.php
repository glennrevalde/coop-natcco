<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$page = public_page_row('about-us');
$mission = setting('home_mission');
$vision = setting('home_vision');
$objectives = array_filter(array_map('trim', preg_split('/\R/', setting('home_objectives'))));
public_start('About us', ['crumbs' => [['About us', null]], 'sub' => e($page['summary'] ?? setting('site_tagline')), 'description' => $page['summary'] ?? '']);
echo '<div class="wrap section split"><div>';
if ($page && $page['body']) {
    echo '<article class="article"><div class="prose">' . $page['body'] . '</div></article>';
} elseif (setting('home_intro') !== '') {
    echo '<article class="article"><div class="prose">' . nl2p(setting('home_intro')) . '</div></article>';
} else {
    echo empty_state('Our profile is being prepared', 'The official organizational profile will be published here.');
}
if ($mission || $vision || $objectives) {
    echo '<div class="mv-grid mt">';
    if ($mission) {
        echo '<div class="box"><h2 class="h3">Mission</h2>' . nl2p($mission) . '</div>';
    }
    if ($vision) {
        echo '<div class="box"><h2 class="h3">Vision</h2>' . nl2p($vision) . '</div>';
    }
    if ($objectives) {
        echo '<div class="box"><h2 class="h3">Objectives</h2><ul class="objectives">';
        foreach ($objectives as $o) {
            echo '<li>' . e($o) . '</li>';
        }
        echo '</ul></div>';
    }
    echo '</div>';
}
echo '</div><aside><div class="box aside-box"><h2 class="h3">Learn more</h2><ul class="foot-list related-list">'
    . '<li><a href="' . e(url('history.php')) . '">COOP NATCCO history</a></li><li><a href="' . e(url('structure.php')) . '">Organizational structure</a></li>'
    . '<li><a href="' . e(url('leadership.php')) . '">Leadership</a></li><li><a href="' . e(url('achievements.php')) . '">Achievements</a></li><li><a href="' . e(url('policies.php')) . '">Policies and procedures</a></li>'
    . '<li><a href="' . e(url('cooperatives.php')) . '">Cooperative directory</a></li></ul></div></aside></div>';
public_end();
