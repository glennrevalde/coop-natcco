<?php
// Historical aggregate election results. Every result carries its source, level, year and verification status.
$levelOpts = ['national' => 'National', 'region' => 'Region', 'province' => 'Province', 'city' => 'City or municipality'];
return [
    'key' => 'election_results', 'perm' => 'elections', 'page' => 'admin/election-results.php', 'table' => 'election_results', 'alias' => 'x',
    'title' => 'Election results', 'singular' => 'election result', 'import' => true,
    'list_intro' => 'Aggregate results only, encoded from official or certified sources. New and edited results stay pending until a different authorized user verifies them.',
    'from' => 'election_results x JOIN election_years y ON y.id = x.election_year_id JOIN election_sources s ON s.id = x.source_id LEFT JOIN regions r ON r.id = x.region_id
               LEFT JOIN provinces p ON p.id = x.province_id LEFT JOIN cities_municipalities cm ON cm.id = x.city_id',
    'select' => 'x.*, y.election_year, s.title AS source_title, r.name AS region_full, r.short_name AS region_name, p.name AS province_name, cm.name AS city_name',
    'scope' => ['region' => 'x.region_id', 'province' => 'x.province_id', 'city' => 'x.city_id'],
    'scope_of' => fn($d) => ($d['geo_level'] ?? '') === 'national' ? [null, null, null, null] : [$d['region_id'] ?? null, $d['province_id'] ?? null, $d['city_id'] ?? null, null],
    'search' => ['r.name', 'p.name', 'cm.name', 's.title'],
    'sorts' => ['year' => 'y.election_year', 'votes' => 'x.votes_obtained', 'area' => 'r.sort_order'],
    'default_sort' => 'year', 'default_dir' => 'desc',
    'filters' => [
        'year' => ['label' => 'Election year', 'col' => 'x.election_year_id', 'options_sql' => 'SELECT id, election_year FROM election_years WHERE archived_at IS NULL ORDER BY election_year DESC'],
        'level' => ['label' => 'Level', 'col' => 'x.geo_level', 'options' => $levelOpts],
        'region' => ['label' => 'Region', 'type' => 'region', 'col' => 'x.region_id'],
        'verification' => ['label' => 'Verification', 'col' => 'x.verification_status', 'options' => ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected']],
    ],
    'columns' => [
        ['label' => 'Area', 'sort' => 'area', 'value' => fn($r) => e($r['geo_level'] === 'national' ? 'Philippines (national)' : ($r['city_name'] ?: ($r['province_name'] ?: $r['region_full']))) . '<span class="sub">' . e($levelOpts[$r['geo_level']]) . ($r['geo_level'] !== 'region' && $r['region_name'] ? ', ' . e($r['region_name']) : '') . '</span>'],
        ['label' => 'Year', 'sort' => 'year', 'value' => fn($r) => e($r['election_year'])],
        ['label' => 'Votes', 'sort' => 'votes', 'class' => 'num', 'value' => fn($r) => e(fmt_num($r['votes_obtained']))],
        ['label' => 'Share', 'class' => 'num', 'value' => fn($r) => e(fmt_pct($r['votes_obtained'], $r['total_votes_cast']))],
        ['label' => 'Source', 'value' => fn($r) => e(excerpt($r['source_title'], 40))],
        ['label' => 'Verification', 'value' => fn($r) => status_text($r['verification_status'])],
    ],
    'fields' => [
        'election_year_id' => ['label' => 'Election year', 'type' => 'lookup_sql', 'required' => true, 'options_sql' => 'SELECT id, election_year FROM election_years WHERE archived_at IS NULL ORDER BY election_year DESC'],
        'geo_level' => ['label' => 'Geographic level', 'type' => 'select', 'options' => $levelOpts, 'required' => true],
    ] + geo_fields('g', false) + [
        'votes_obtained' => ['label' => 'Votes obtained', 'type' => 'number', 'min' => 0, 'required' => true],
        'total_votes_cast' => ['label' => 'Total party-list votes cast in the area', 'type' => 'number', 'min' => 0, 'help' => 'Needed to compute the vote share. Leave blank if the source does not state it.'],
        'registered_voters' => ['label' => 'Registered voters', 'type' => 'number', 'min' => 0],
        'rank_position' => ['label' => 'Rank in the area', 'type' => 'number', 'min' => 1, 'help' => 'Only if stated in the official source.'],
        'source_id' => ['label' => 'Official source', 'type' => 'lookup_sql', 'required' => true, 'options_sql' => 'SELECT id, title FROM election_sources WHERE archived_at IS NULL ORDER BY date_issued DESC, title'],
        'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'max' => 2000],
        'verification_status' => ['label' => 'Verification', 'computed' => true, 'type' => 'select', 'options' => ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected']],
    ],
    'sections' => [
        'Election and area' => ['election_year_id', 'geo_level', 'region_id', 'province_id', 'city_id'],
        'Figures from the official source' => ['votes_obtained', 'total_votes_cast', 'registered_voters', 'rank_position', 'source_id', 'remarks'],
    ],
    'view_sections' => [
        'Election and area' => ['election_year_id', 'geo_level', 'region_id', 'province_id', 'city_id'],
        'Figures from the official source' => ['votes_obtained', 'total_votes_cast', 'registered_voters', 'rank_position', 'source_id', 'remarks', 'verification_status'],
    ],
    'title_of' => fn($r) => $r['election_year'] . ' ' . ($r['geo_level'] === 'national' ? 'national' : ($r['city_name'] ?: ($r['province_name'] ?: $r['region_full']))) . ' result',
    'validate' => function (array &$data, ?array $old, array &$errors) {
        $lvl = $data['geo_level'] ?? '';
        if ($lvl === 'national') {
            $data['region_id'] = $data['province_id'] = $data['city_id'] = null;
            $data['geo_key'] = 'N';
        } elseif ($lvl === 'region') {
            if (empty($data['region_id'])) {
                $errors['region_id'] = 'Select the region.';
            }
            $data['province_id'] = $data['city_id'] = null;
            $data['geo_key'] = 'R' . (int) ($data['region_id'] ?? 0);
        } elseif ($lvl === 'province') {
            if (empty($data['province_id'])) {
                $errors['province_id'] = 'Select the province.';
            }
            $data['city_id'] = null;
            $data['geo_key'] = 'P' . (int) ($data['province_id'] ?? 0);
        } elseif ($lvl === 'city') {
            if (empty($data['city_id'])) {
                $errors['city_id'] = 'Select the city or municipality.';
            }
            $data['geo_key'] = 'C' . (int) ($data['city_id'] ?? 0);
        }
        if (isset($data['votes_obtained'], $data['total_votes_cast']) && $data['total_votes_cast'] !== null && (int) $data['votes_obtained'] > (int) $data['total_votes_cast']) {
            $errors['votes_obtained'] = 'Votes obtained cannot exceed the total votes cast.';
        }
        if (!$errors && !empty($data['election_year_id'])) {
            $p = [(int) $data['election_year_id'], $data['geo_key']];
            $sql = 'SELECT id FROM election_results WHERE election_year_id = ? AND geo_key = ?';
            if ($old) {
                $sql .= ' AND id <> ?';
                $p[] = (int) $old['id'];
            }
            if ($dup = q_val($sql, $p)) {
                $errors['geo_level'] = 'A result for this year and area already exists (record #' . $dup . '). Edit that record instead.';
            }
        }
    },
    'before_save' => function (array &$data, ?array $old) {
        $data['verification_status'] = 'pending';
        $data['verified_by'] = null;
        $data['verified_at'] = null;
    },
    'after_save' => function (int $id, array $data, ?array $old) {
        q_insert('election_result_verifications', ['result_id' => $id, 'action' => $old ? 'updated' : 'submitted', 'remarks' => $old ? 'Figures edited; verification required again' : null, 'user_id' => uid(), 'created_at' => now()]);
        notify_perm('elections.verify', 'approval_pending', 'An election result is waiting for verification', '', url('admin/election-results.php', ['action' => 'view', 'id' => $id]),
            ['region' => $data['region_id'] ?? null, 'province' => $data['province_id'] ?? null, 'city' => $data['city_id'] ?? null], uid());
    },
    'import_key' => fn($d) => ($d['election_year_id'] ?? '') . '|' . ($d['geo_key'] ?? ''),
    'import_duplicate' => fn($d) => ($id = q_val('SELECT id FROM election_results WHERE election_year_id = ? AND geo_key = ?', [(int) ($d['election_year_id'] ?? 0), $d['geo_key'] ?? ''])) ? (int) $id : null,
    'custom_actions' => [
        'verify' => ['label' => 'Verify result', 'perm' => 'elections.verify', 'class' => 'btn-primary', 'confirm' => 'Confirm that these figures match the cited official source.',
            'show' => function ($r) {
                if ($r['verification_status'] === 'verified' || $r['archived_at']) {
                    return false;
                }
                $last = q_val("SELECT user_id FROM election_result_verifications WHERE result_id = ? AND action IN ('submitted','updated') ORDER BY id DESC LIMIT 1", [$r['id']]);
                return is_super() || (int) $last !== uid();
            },
            'run' => function ($r) {
                q("UPDATE election_results SET verification_status = 'verified', verified_by = ?, verified_at = ? WHERE id = ?", [uid(), now(), $r['id']]);
                q_insert('election_result_verifications', ['result_id' => $r['id'], 'action' => 'verified', 'user_id' => uid(), 'created_at' => now()]);
                revision_log('election_results', (int) $r['id'], 'verified', null);
                audit_log('verify', 'election_results', (int) $r['id'], 'Verified election result');
                return 'Result verified. It now appears in verified reports.';
            }],
        'reject' => ['label' => 'Reject', 'perm' => 'elections.verify', 'class' => 'btn-danger-ghost', 'confirm' => 'Reject these figures?', 'remarks' => 'Reason for rejecting',
            'show' => fn($r) => $r['verification_status'] === 'pending' && !$r['archived_at'],
            'run' => function ($r) {
                $remarks = mb_substr(post('remarks'), 0, 500);
                q("UPDATE election_results SET verification_status = 'rejected' WHERE id = ?", [$r['id']]);
                q_insert('election_result_verifications', ['result_id' => $r['id'], 'action' => 'rejected', 'remarks' => $remarks ?: null, 'user_id' => uid(), 'created_at' => now()]);
                audit_log('reject', 'election_results', (int) $r['id'], 'Rejected election result' . ($remarks ? ': ' . $remarks : ''));
                return 'Result rejected.';
            }],
        'reopen' => ['label' => 'Reopen for verification', 'perm' => 'elections.verify', 'class' => 'btn-secondary',
            'show' => fn($r) => $r['verification_status'] !== 'pending' && !$r['archived_at'],
            'run' => function ($r) {
                q("UPDATE election_results SET verification_status = 'pending', verified_by = NULL, verified_at = NULL WHERE id = ?", [$r['id']]);
                q_insert('election_result_verifications', ['result_id' => $r['id'], 'action' => 'reopened', 'user_id' => uid(), 'created_at' => now()]);
                audit_log('reopen', 'election_results', (int) $r['id'], 'Reopened election result for verification');
                return 'Result reopened.';
            }],
    ],
    'view_panels' => function (array $row) {
        $src = q_one('SELECT * FROM election_sources WHERE id = ?', [$row['source_id']]);
        echo '<section class="panel"><h2 class="panel-title">Source documentation</h2><dl class="dl">'
            . '<div class="dl-item"><dt>Source</dt><dd>' . e($src['title']) . '</dd></div><div class="dl-item"><dt>Issued by</dt><dd>' . e($src['issuing_body'] ?: 'Not stated') . '</dd></div>'
            . '<div class="dl-item"><dt>Reference</dt><dd>' . e($src['reference_no'] ?: 'Not stated') . '</dd></div>'
            . ($src['reference_url'] ? '<div class="dl-item"><dt>Link</dt><dd><a href="' . e($src['reference_url']) . '" target="_blank" rel="noopener noreferrer">' . e($src['reference_url']) . '</a></dd></div>' : '')
            . ($src['document_media_id'] ? '<div class="dl-item"><dt>Document</dt><dd><a href="' . e(media_url((int) $src['document_media_id'])) . '">Open source document</a></dd></div>' : '')
            . '<div class="dl-item"><dt>Vote share</dt><dd>' . e(fmt_pct($row['votes_obtained'], $row['total_votes_cast']) ?: 'Not computed (total votes not stated)') . '</dd></div></dl></section>';
        $log = q_all('SELECT v.*, u.full_name FROM election_result_verifications v LEFT JOIN users u ON u.id = v.user_id WHERE v.result_id = ? ORDER BY v.id DESC', [$row['id']]);
        related_table('Verification log', ['When', 'Action', 'By', 'Remarks'], array_map(fn($l) => [e(fmt_datetime($l['created_at'])), e(ucfirst($l['action'])), e($l['full_name']), e($l['remarks'])], $log), 'No entries.');
    },
    'export_columns' => [
        'Election year' => fn($r) => $r['election_year'], 'Level' => fn($r) => $r['geo_level'], 'Region' => fn($r) => $r['region_full'], 'Province' => fn($r) => $r['province_name'],
        'City/Municipality' => fn($r) => $r['city_name'], 'Votes obtained' => fn($r) => $r['votes_obtained'], 'Total votes cast' => fn($r) => $r['total_votes_cast'],
        'Vote share' => fn($r) => fmt_pct($r['votes_obtained'], $r['total_votes_cast']), 'Registered voters' => fn($r) => $r['registered_voters'], 'Rank' => fn($r) => $r['rank_position'],
        'Source' => fn($r) => $r['source_title'], 'Verification' => fn($r) => $r['verification_status'],
    ],
];
