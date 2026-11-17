<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$p = [];
$rows = q_all('SELECT x.*, cc.name AS category_name FROM faqs x LEFT JOIN content_categories cc ON cc.id = x.category_id WHERE ' . pub_where('x', $p) . ' ORDER BY cc.sort_order, x.sort_order, x.id', $p);
$groups = [];
foreach ($rows as $r) {
    $groups[$r['category_name'] ?: 'General'][] = $r;
}
public_start('Frequently asked questions', ['crumbs' => [['Help desk', url('helpdesk.php')], ['FAQs', null]], 'sub' => 'Answers to common questions. If yours is not here, ask the help desk.']);
echo '<div class="wrap section split"><div class="faq-list">';
if (!$groups) {
    echo empty_state('No questions published yet', 'Common questions and answers will appear here.');
}
foreach ($groups as $g => $list) {
    echo '<h2 class="h3 mt">' . e($g) . '</h2>';
    foreach ($list as $f) {
        echo '<details><summary>' . e($f['question']) . '</summary><div class="faq-a">' . nl2p($f['answer']) . '</div></details>';
    }
}
echo '</div><aside><div class="box box-blue"><h2 class="h3">Still need help?</h2><p>Send your concern to the help desk and track it with a ticket number.</p><a class="btn btn-accent" href="' . e(url('helpdesk.php')) . '">Submit a concern</a></div></aside></div>';
public_end();
