<?php
return [
    'key' => 'election_years', 'perm' => 'elections', 'page' => 'admin/election-years.php', 'table' => 'election_years', 'alias' => 'x',
    'title' => 'Election years', 'singular' => 'election year', 'export' => false,
    'search' => ['x.title'],
    'sorts' => ['year' => 'x.election_year'], 'default_sort' => 'year', 'default_dir' => 'desc',
    'select' => "x.*, (SELECT COUNT(*) FROM election_results er WHERE er.election_year_id = x.id AND er.archived_at IS NULL) AS result_count,
                 (SELECT COUNT(*) FROM election_results er WHERE er.election_year_id = x.id AND er.verification_status = 'verified' AND er.archived_at IS NULL) AS verified_count",
    'columns' => [
        ['label' => 'Year', 'sort' => 'year', 'value' => fn($r) => e($r['election_year'])],
        ['label' => 'Title', 'value' => fn($r) => e($r['title'])],
        ['label' => 'Election date', 'value' => fn($r) => e(fmt_date($r['election_date']))],
        ['label' => 'Results', 'class' => 'num', 'value' => fn($r) => fmt_num($r['result_count']) . '<span class="sub">' . fmt_num($r['verified_count']) . ' verified</span>'],
    ],
    'fields' => [
        'election_year' => ['label' => 'Year', 'type' => 'number', 'min' => 1987, 'max_value' => 2100, 'required' => true, 'unique' => true],
        'election_date' => ['label' => 'Election date', 'type' => 'date'],
        'title' => ['label' => 'Title', 'required' => true, 'max' => 150, 'help' => 'For example: 2025 National and Local Elections.'],
        'description' => ['label' => 'Notes', 'type' => 'textarea', 'max' => 3000],
    ],
    'title_of' => fn($r) => (string) $r['election_year'],
];
