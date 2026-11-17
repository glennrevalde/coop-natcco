<?php
/**
 * Staged bulk import for CRUD modules:
 * upload -> column mapping -> validation preview -> approval -> batched processing.
 * Files are parsed as data only (CSV or XLSX); nothing in them is executed.
 */
declare(strict_types=1);

const IMPORT_MAX_ROWS = 5000;
const IMPORT_CHUNK = 100;

function import_fields(array $m): array
{
    $out = [];
    foreach ($m['fields'] as $n => $f) {
        $t = $f['type'] ?? 'text';
        if (!empty($f['computed']) || !empty($f['hide_form']) || in_array($t, ['media', 'static'], true) || (isset($f['import']) && $f['import'] === false)) {
            continue;
        }
        if (!empty($f['perm']) && !can($f['perm'])) {
            continue;
        }
        if ($t === 'person') {
            foreach (['first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name', 'suffix' => 'Suffix', 'email' => 'Email', 'phone' => 'Phone'] as $k => $l) {
                $out['person_' . $k] = ['label' => 'Person ' . strtolower($l), 'required' => in_array($k, ['first_name', 'last_name'], true), 'hint' => 'Matched to an existing person by email or name, or created'];
            }
            continue;
        }
        $hint = '';
        if (in_array($t, ['select', 'lookup_sql'], true)) {
            $opts = field_options($f);
            $hint = count($opts) <= 12 ? 'One of: ' . implode(', ', $opts) : 'Must match an existing ' . strtolower($f['label'] ?? $n);
        } elseif ($t === 'date') {
            $hint = 'YYYY-MM-DD or MM/DD/YYYY';
        } elseif ($t === 'datetime') {
            $hint = 'YYYY-MM-DD HH:MM';
        } elseif ($t === 'region') {
            $hint = 'Region name, short name (e.g. NCR) or PSGC code';
        } elseif ($t === 'province') {
            $hint = 'Province name (leave blank for independent cities)';
        } elseif ($t === 'city') {
            $hint = 'City or municipality name';
        } elseif ($t === 'coop') {
            $hint = 'Cooperative reference number or exact name';
        } elseif ($t === 'bool') {
            $hint = 'Yes or No';
        } elseif ($t === 'number') {
            $hint = 'Whole number';
        }
        $out[$n] = ['label' => $f['label'] ?? label_from_key($n), 'required' => !empty($f['required']), 'hint' => $hint];
    }
    return $out;
}

function import_norm(string $s): string
{
    return preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));
}

function crud_import_template(array $m): void
{
    crud_require($m, 'import');
    $fields = import_fields($m);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $m['key'] . '-import-template.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_map(fn($f) => $f['label'], array_values($fields)));
    fclose($out);
    exit;
}

/** Read CSV or XLSX into [headers, rows]. */
function import_read_file(string $path, string $ext): array
{
    $rows = [];
    if ($ext === 'csv') {
        $fh = fopen($path, 'r');
        $first = (string) fgets($fh);
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($fh);
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        while (($r = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            if ($r === [null]) {
                continue;
            }
            $rows[] = array_map(fn($c) => mb_convert_encoding((string) $c, 'UTF-8', 'UTF-8, Windows-1252'), $r);
            if (count($rows) > IMPORT_MAX_ROWS + 1) {
                break;
            }
        }
        fclose($fh);
    } else {
        if (!class_exists('ZipArchive')) {
            throw new ValidationError('Excel import needs the PHP zip extension. Save the sheet as CSV and import that instead.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new ValidationError('The Excel file could not be opened.');
        }
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb && $rels) {
            $wbx = simplexml_load_string($wb, 'SimpleXMLElement', LIBXML_NONET);
            $rx = simplexml_load_string($rels, 'SimpleXMLElement', LIBXML_NONET);
            if ($wbx && $rx && isset($wbx->sheets->sheet[0])) {
                $rid = (string) $wbx->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($rx->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $sheetPath = 'xl/' . ltrim(str_replace('/xl/', '', (string) $rel['Target']), '/');
                    }
                }
            }
        }
        $stat = $zip->statName($sheetPath);
        if (!$stat || $stat['size'] > 60 * 1024 * 1024) {
            throw new ValidationError('The worksheet could not be read or is too large.');
        }
        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss) {
            $ssx = simplexml_load_string($ss, 'SimpleXMLElement', LIBXML_NONET);
            foreach ($ssx->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    $t = '';
                    foreach ($si->r as $r) {
                        $t .= (string) $r->t;
                    }
                    $shared[] = $t;
                }
            }
        }
        $sx = simplexml_load_string((string) $zip->getFromName($sheetPath), 'SimpleXMLElement', LIBXML_NONET);
        $zip->close();
        if (!$sx) {
            throw new ValidationError('The worksheet could not be read.');
        }
        foreach ($sx->sheetData->row as $row) {
            $cells = [];
            $idx = 0;
            foreach ($row->c as $c) {
                $ref = strtoupper((string) $c['r']);
                if ($ref !== '') {
                    $col = 0;
                    foreach (str_split(preg_replace('/[^A-Z]/', '', $ref)) as $ch) {
                        $col = $col * 26 + (ord($ch) - 64);
                    }
                    $idx = max(0, $col - 1);
                }
                $t = (string) $c['t'];
                if ($t === 's') {
                    $v = $shared[(int) $c->v] ?? '';
                } elseif ($t === 'inlineStr') {
                    $v = (string) $c->is->t;
                } else {
                    $v = (string) $c->v;
                }
                $cells[$idx] = $v;
                $idx++;
            }
            if (!$cells) {
                continue;
            }
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= min($max, 80); $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
            if (count($rows) > IMPORT_MAX_ROWS + 1) {
                break;
            }
        }
    }
    if (!$rows) {
        throw new ValidationError('The file has no rows.');
    }
    $headers = array_map(fn($h) => mb_substr(trim((string) $h), 0, 100), array_shift($rows));
    if (count($headers) > 80) {
        throw new ValidationError('The file has too many columns.');
    }
    $rows = array_values(array_filter($rows, fn($r) => implode('', array_map('trim', array_map('strval', $r))) !== ''));
    if (count($rows) > IMPORT_MAX_ROWS) {
        throw new ValidationError('A single import is limited to ' . IMPORT_MAX_ROWS . ' rows. Split the file and import each part.');
    }
    return [$headers, $rows];
}

function import_batch(array $m, int $id): array
{
    $b = q_one('SELECT * FROM import_batches WHERE id = ? AND module = ?', [$id, $m['key']]);
    if (!$b) {
        abort(404);
    }
    if ((int) $b['uploaded_by'] !== uid() && !can('imports.approve')) {
        deny('Import batch of another user');
    }
    return $b;
}

function import_file_path(array $b): string
{
    return STORAGE_PATH . '/imports/' . basename($b['stored_name']);
}

function crud_import(array $m): void
{
    crud_require($m, 'view');
    $step = get('step', 'start');
    $approver = can('imports.approve');
    if (in_array($step, ['start', 'upload', 'map', 'submit'], true) && !crud_can($m, 'import')) {
        deny('Import ' . $m['key']);
    }
    switch ($step) {
        case 'upload':
            if (!is_post()) {
                abort(405);
            }
            try {
                if (!upload_present('file')) {
                    throw new ValidationError('Choose a CSV or Excel file.');
                }
                $info = media_validate_upload($_FILES['file'], 'import');
                $name = bin2hex(random_bytes(16)) . '.' . $info['ext'];
                $dir = STORAGE_PATH . '/imports';
                if (!is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }
                if (!move_uploaded_file($info['tmp'], $dir . '/' . $name) && !(defined('CNPL_TESTING') && copy($info['tmp'], $dir . '/' . $name))) {
                    throw new RuntimeException('Could not store import file');
                }
                [$headers, $rows] = import_read_file($dir . '/' . $name, $info['ext']);
                $bid = q_insert('import_batches', ['module' => $m['key'], 'original_name' => $info['original'], 'stored_name' => $name, 'file_type' => $info['ext'],
                    'total_rows' => count($rows), 'batch_status' => 'uploaded', 'uploaded_by' => uid(), 'created_at' => now()]);
                audit_log('import_upload', $m['key'], null, "Uploaded import file with " . count($rows) . " rows (batch #{$bid})");
                redirect(crud_url($m, ['action' => 'import', 'step' => 'map', 'batch' => $bid]));
            } catch (ValidationError $e) {
                flash('error', $e->getMessage());
                redirect(crud_url($m, ['action' => 'import']));
            }
            return;

        case 'map':
            $b = import_batch($m, get_int('batch'));
            if (!in_array($b['batch_status'], ['uploaded', 'mapped', 'validated'], true)) {
                redirect(crud_url($m, ['action' => 'import', 'step' => 'preview', 'batch' => $b['id']]));
            }
            [$headers, $rows] = import_read_file(import_file_path($b), $b['file_type']);
            $fields = import_fields($m);
            if (is_post()) {
                $map = [];
                foreach ($fields as $k => $f) {
                    $col = $_POST['map'][$k] ?? '';
                    if (is_string($col) && ctype_digit($col) && (int) $col < count($headers)) {
                        $map[$k] = (int) $col;
                    }
                }
                $missing = array_filter($fields, fn($f, $k) => $f['required'] && !isset($map[$k]), ARRAY_FILTER_USE_BOTH);
                if ($missing) {
                    flash('error', 'Map a column for every required field: ' . implode(', ', array_map(fn($f) => $f['label'], $missing)) . '.');
                    redirect(crud_url($m, ['action' => 'import', 'step' => 'map', 'batch' => $b['id']]));
                }
                import_validate_batch($m, $b, $map, $rows);
                redirect(crud_url($m, ['action' => 'import', 'step' => 'preview', 'batch' => $b['id']]));
            }
            $saved = $b['column_map'] ? json_decode($b['column_map'], true) : [];
            $normHeaders = array_map('import_norm', $headers);
            admin_start('Import ' . $m['title'], [[$m['title'], crud_url($m)], ['Import', crud_url($m, ['action' => 'import'])], ['Map columns', null]]);
            page_bar('Map columns', e($b['original_name']) . ', ' . fmt_num($b['total_rows']) . ' rows');
            echo flash_render();
            echo '<form method="post" class="panel">' . csrf_field() . '<p class="intro">Match each field to a column in your file. Required fields are marked *.</p><div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Field</th><th scope="col">Column in file</th><th scope="col">Accepted values</th></tr></thead><tbody>';
            foreach ($fields as $k => $f) {
                $guess = $saved[$k] ?? null;
                if ($guess === null) {
                    foreach ([import_norm($f['label']), import_norm($k)] as $cand) {
                        $pos = array_search($cand, $normHeaders, true);
                        if ($pos !== false) {
                            $guess = $pos;
                            break;
                        }
                    }
                }
                echo '<tr><td data-label="Field">' . e($f['label']) . ($f['required'] ? ' <span class="req">*</span>' : '') . '</td><td data-label="Column">'
                    . select_html('map[' . $k . ']', array_map(fn($h, $i) => $h !== '' ? $h : 'Column ' . ($i + 1), $headers, array_keys($headers)), $guess === null ? '' : (string) $guess, ['aria-label' => 'Column for ' . $f['label']], '— Not imported —')
                    . '</td><td data-label="Accepted" class="muted">' . e($f['hint']) . '</td></tr>';
            }
            echo '</tbody></table></div><div class="form-actions"><button class="btn btn-primary" type="submit">Validate rows</button><a class="btn btn-ghost" href="' . e(crud_url($m, ['action' => 'import'])) . '">Cancel</a></div></form>';
            admin_end();
            return;

        case 'preview':
            $b = import_batch($m, get_int('batch'));
            $counts = q_pairs('SELECT row_status, COUNT(*) FROM import_rows WHERE batch_id = ? GROUP BY row_status', [$b['id']]);
            $show = get('rows', 'problems');
            $where = $show === 'all' ? '' : " AND row_status IN ('invalid','duplicate','failed')";
            $rows = q_all("SELECT * FROM import_rows WHERE batch_id = ?{$where} ORDER BY row_no LIMIT 200", [$b['id']]);
            $fields = import_fields($m);
            $canApprove = $approver && ((int) $b['uploaded_by'] !== uid() || is_super());
            $actions = '';
            if (($counts['invalid'] ?? 0) + ($counts['duplicate'] ?? 0) + ($counts['failed'] ?? 0) > 0) {
                $actions .= '<a class="btn btn-secondary" href="' . e(crud_url($m, ['action' => 'import', 'step' => 'errors', 'batch' => $b['id']])) . '">Download error report</a>';
            }
            admin_start('Import preview', [[$m['title'], crud_url($m)], ['Import', crud_url($m, ['action' => 'import'])], ['Batch #' . $b['id'], null]]);
            page_bar('Import batch #' . (int) $b['id'], e($b['original_name']) . ', status: ' . label_from_key($b['batch_status']), $actions);
            echo flash_render();
            echo '<div class="stat-row">';
            foreach (['valid' => 'Ready to import', 'invalid' => 'Invalid rows', 'duplicate' => 'Duplicates', 'imported' => 'Imported', 'failed' => 'Failed'] as $k => $l) {
                echo '<div class="stat"><span class="stat-num">' . fmt_num($counts[$k] ?? 0) . '</span><span class="stat-label">' . e($l) . '</span></div>';
            }
            echo '</div>';
            echo '<div class="panel panel-flat"><div class="btn-row">';
            if ($b['batch_status'] === 'validated' && crud_can($m, 'import')) {
                echo '<a class="btn btn-secondary" href="' . e(crud_url($m, ['action' => 'import', 'step' => 'map', 'batch' => $b['id']])) . '">Change mapping</a>';
                if (!$canApprove && ($counts['valid'] ?? 0) > 0) {
                    echo '<form method="post" action="' . e(crud_url($m, ['action' => 'import', 'step' => 'submit', 'batch' => $b['id']])) . '">' . csrf_field() . '<button class="btn btn-primary" type="submit">Submit for approval</button></form>';
                }
            }
            if (in_array($b['batch_status'], ['validated', 'pending_approval'], true) && $canApprove && ($counts['valid'] ?? 0) > 0) {
                echo '<form method="post" action="' . e(crud_url($m, ['action' => 'import', 'step' => 'approve', 'batch' => $b['id']])) . '" data-confirm="Approve and import ' . (int) ($counts['valid'] ?? 0) . ' valid rows?">' . csrf_field() . '<button class="btn btn-primary" type="submit">Approve and import ' . fmt_num($counts['valid'] ?? 0) . ' rows</button></form>';
            }
            if (in_array($b['batch_status'], ['approved', 'processing'], true) && $approver) {
                echo '<a class="btn btn-primary" href="' . e(crud_url($m, ['action' => 'import', 'step' => 'process', 'batch' => $b['id']])) . '">Continue processing</a>';
            }
            if (in_array($b['batch_status'], ['uploaded', 'validated', 'pending_approval'], true) && ($approver || (int) $b['uploaded_by'] === uid())) {
                echo '<form method="post" action="' . e(crud_url($m, ['action' => 'import', 'step' => 'reject', 'batch' => $b['id']])) . '" data-confirm="Cancel this import batch?">' . csrf_field() . '<button class="btn btn-danger-ghost" type="submit">' . ($approver && (int) $b['uploaded_by'] !== uid() ? 'Reject batch' : 'Cancel batch') . '</button></form>';
            }
            if ($b['batch_status'] === 'pending_approval' && !$canApprove) {
                echo '<p class="muted">Waiting for an administrator to approve this import.</p>';
            }
            echo '</div></div>';
            echo '<div class="tabs-inline"><a href="' . e(current_url(['rows' => 'problems'])) . '"' . ($show !== 'all' ? ' aria-current="true"' : '') . '>Rows with problems</a><a href="' . e(current_url(['rows' => 'all'])) . '"' . ($show === 'all' ? ' aria-current="true"' : '') . '>All rows (first 200)</a></div>';
            if (!$rows) {
                echo '<div class="empty"><p>' . ($show === 'all' ? 'No rows.' : 'No problems found.') . '</p></div>';
            } else {
                $keys = array_slice(array_keys($fields), 0, 5);
                echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Row</th><th scope="col">Status</th>';
                foreach ($keys as $k) {
                    echo '<th scope="col">' . e($fields[$k]['label']) . '</th>';
                }
                echo '<th scope="col">Problems</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    $d = json_decode($r['row_data'], true) ?: [];
                    echo '<tr><td data-label="Row">' . (int) $r['row_no'] . '</td><td data-label="Status">' . status_text($r['row_status'] === 'valid' ? 'approved' : ($r['row_status'] === 'imported' ? 'completed' : ($r['row_status'] === 'duplicate' ? 'pending' : 'rejected')), label_from_key($r['row_status'])) . '</td>';
                    foreach ($keys as $k) {
                        echo '<td data-label="' . e($fields[$k]['label']) . '">' . e(excerpt($d[$k] ?? '', 60)) . '</td>';
                    }
                    echo '<td data-label="Problems" class="err-text">' . e((string) $r['errors']) . '</td></tr>';
                }
                echo '</tbody></table></div>';
            }
            admin_end();
            return;

        case 'errors':
            $b = import_batch($m, get_int('batch'));
            $rows = q_all("SELECT * FROM import_rows WHERE batch_id = ? AND row_status IN ('invalid','duplicate','failed') ORDER BY row_no", [$b['id']]);
            $fields = import_fields($m);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="import-' . (int) $b['id'] . '-errors.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['Row', 'Status', 'Problems'], array_map(fn($f) => $f['label'], array_values($fields))));
            foreach ($rows as $r) {
                $d = json_decode($r['row_data'], true) ?: [];
                fputcsv($out, array_map('csv_safe', array_merge([$r['row_no'], $r['row_status'], $r['errors']], array_map(fn($k) => $d[$k] ?? '', array_keys($fields)))));
            }
            fclose($out);
            exit;

        case 'submit':
            if (!is_post()) {
                abort(405);
            }
            $b = import_batch($m, get_int('batch'));
            if ($b['batch_status'] !== 'validated') {
                abort(400);
            }
            q("UPDATE import_batches SET batch_status = 'pending_approval' WHERE id = ?", [$b['id']]);
            q_insert('approval_requests', ['module' => $m['key'], 'record_id' => (int) $b['id'], 'request_type' => 'import', 'summary' => mb_substr('Import of ' . $b['valid_rows'] . ' ' . $m['title'] . ' rows from ' . $b['original_name'], 0, 255),
                'request_status' => 'pending', 'requested_by' => uid(), 'created_at' => now()]);
            notify_perm('imports.approve', 'approval_pending', 'An import batch is waiting for approval', '', crud_url($m, ['action' => 'import', 'step' => 'preview', 'batch' => $b['id']]), null, uid());
            audit_log('import_submit', $m['key'], null, 'Submitted import batch #' . $b['id'] . ' for approval');
            flash('success', 'Import submitted for approval.');
            redirect(crud_url($m, ['action' => 'import', 'step' => 'preview', 'batch' => $b['id']]));
            return;

        case 'approve':
        case 'reject':
            if (!is_post()) {
                abort(405);
            }
            $b = import_batch($m, get_int('batch'));
            if ($step === 'approve') {
                if (!$approver || ((int) $b['uploaded_by'] === uid() && !is_super())) {
                    deny('Approve own import');
                }
                if (!in_array($b['batch_status'], ['validated', 'pending_approval'], true)) {
                    abort(400);
                }
                q("UPDATE import_batches SET batch_status = 'approved', approved_by = ?, approved_at = ? WHERE id = ?", [uid(), now(), $b['id']]);
                q("UPDATE approval_requests SET request_status = 'approved', reviewed_by = ?, reviewed_at = ? WHERE module = ? AND record_id = ? AND request_type = 'import' AND request_status = 'pending'", [uid(), now(), $m['key'], $b['id']]);
                audit_log('import_approve', $m['key'], null, 'Approved import batch #' . $b['id']);
                redirect(crud_url($m, ['action' => 'import', 'step' => 'process', 'batch' => $b['id']]));
            }
            if (!$approver && (int) $b['uploaded_by'] !== uid()) {
                deny('Reject import');
            }
            q("UPDATE import_batches SET batch_status = 'rejected', approved_by = ?, approved_at = ? WHERE id = ?", [uid(), now(), $b['id']]);
            q("UPDATE approval_requests SET request_status = 'rejected', reviewed_by = ?, reviewed_at = ? WHERE module = ? AND record_id = ? AND request_type = 'import' AND request_status = 'pending'", [uid(), now(), $m['key'], $b['id']]);
            @unlink(import_file_path($b));
            audit_log('import_reject', $m['key'], null, 'Rejected or cancelled import batch #' . $b['id']);
            if ((int) $b['uploaded_by'] !== uid()) {
                notify_user((int) $b['uploaded_by'], 'approval_done', 'Your import batch was rejected', '', crud_url($m, ['action' => 'import']));
            }
            flash('success', 'Import batch closed.');
            redirect(crud_url($m, ['action' => 'import']));
            return;

        case 'process':
            $b = import_batch($m, get_int('batch'));
            if (!$approver) {
                deny('Process import');
            }
            if (is_post()) {
                $result = import_process_chunk($m, $b);
                if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
                    json_out($result);
                }
                redirect(crud_url($m, ['action' => 'import', 'step' => $result['done'] ? 'preview' : 'process', 'batch' => $b['id']]));
            }
            $total = (int) $b['valid_rows'];
            $left = (int) q_val("SELECT COUNT(*) FROM import_rows WHERE batch_id = ? AND row_status = 'valid'", [$b['id']]);
            $doneN = max(0, $total - $left);
            admin_start('Processing import', [[$m['title'], crud_url($m)], ['Import', crud_url($m, ['action' => 'import'])], ['Batch #' . $b['id'], null]]);
            page_bar('Processing import batch #' . (int) $b['id'], 'Rows are imported in groups of ' . IMPORT_CHUNK . ', each in its own transaction.');
            $pct = $total ? (int) round(100 * min($doneN, $total) / $total) : 100;
            echo '<div class="panel"><div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $pct . '" aria-label="Import progress"><div class="progress-bar" data-width="' . $pct . '"></div></div>'
                . '<p class="progress-text" aria-live="polite">' . $pct . '% complete</p>'
                . ($b['batch_status'] === 'completed' ? '<p><a class="btn btn-primary" href="' . e(crud_url($m, ['action' => 'import', 'step' => 'preview', 'batch' => $b['id']])) . '">View results</a></p>'
                    : '<form method="post" data-import-runner data-done-url="' . e(crud_url($m, ['action' => 'import', 'step' => 'preview', 'batch' => $b['id']])) . '">' . csrf_field() . '<button class="btn btn-primary" type="submit">Process next ' . IMPORT_CHUNK . ' rows</button></form>')
                . '</div>';
            admin_end();
            return;
    }

    // Start page
    $batches = q_all('SELECT b.*, u.full_name FROM import_batches b LEFT JOIN users u ON u.id = b.uploaded_by WHERE b.module = ?' . ($approver ? '' : ' AND b.uploaded_by = ' . (int) uid()) . ' ORDER BY b.id DESC LIMIT 15', [$m['key']]);
    admin_start('Import ' . $m['title'], [[$m['title'], crud_url($m)], ['Import', null]]);
    page_bar('Import ' . $m['title'], 'CSV or Excel (.xlsx), up to ' . fmt_num(IMPORT_MAX_ROWS) . ' rows per file', '<a class="btn btn-secondary" href="' . e(crud_url($m, ['action' => 'template'])) . '">Download template</a>');
    echo flash_render();
    if (crud_can($m, 'import')) {
        echo '<form method="post" enctype="multipart/form-data" class="panel" action="' . e(crud_url($m, ['action' => 'import', 'step' => 'upload'])) . '">' . csrf_field()
            . '<h2 class="panel-title">1. Upload a file</h2><p class="muted">Use the template so the columns are recognized automatically. Formulas and macros in the file are ignored; only cell values are read.</p>'
            . '<div class="grid"><div class="field field-half"><label for="imp_file">CSV or Excel file</label><input type="file" id="imp_file" name="file" required accept=".csv,.xlsx"></div></div>'
            . '<div class="form-actions"><button class="btn btn-primary" type="submit">Upload and continue</button></div></form>';
    }
    echo '<section class="panel"><h2 class="panel-title">Recent import batches</h2>';
    if (!$batches) {
        echo '<p class="muted">No imports yet.</p>';
    } else {
        echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Batch</th><th scope="col">File</th><th scope="col">Rows</th><th scope="col">Imported</th><th scope="col">Status</th><th scope="col">Uploaded by</th></tr></thead><tbody>';
        foreach ($batches as $b) {
            echo '<tr><td data-label="Batch"><a href="' . e(crud_url($m, ['action' => 'import', 'step' => 'preview', 'batch' => $b['id']])) . '">#' . (int) $b['id'] . '</a></td><td data-label="File">' . e($b['original_name']) . '</td><td data-label="Rows">' . fmt_num($b['total_rows']) . '</td><td data-label="Imported">' . fmt_num($b['imported_rows'])
                . '</td><td data-label="Status">' . status_text(['completed' => 'completed', 'rejected' => 'rejected', 'failed' => 'failed', 'pending_approval' => 'pending'][$b['batch_status']] ?? 'open', label_from_key($b['batch_status'])) . '</td><td data-label="Uploaded by">' . e($b['full_name']) . ', ' . e(fmt_date($b['created_at'])) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    admin_end();
}

function import_validate_batch(array $m, array $b, array $map, array $rows): void
{
    $fields = import_fields($m);
    q('DELETE FROM import_rows WHERE batch_id = ?', [$b['id']]);
    $counts = ['valid' => 0, 'invalid' => 0, 'duplicate' => 0];
    $seen = [];
    tx(function () use ($m, $b, $map, $rows, $fields, &$counts, &$seen) {
        foreach ($rows as $i => $r) {
            $raw = [];
            foreach ($map as $k => $col) {
                $raw[$k] = mb_substr(trim((string) ($r[$col] ?? '')), 0, 5000);
            }
            foreach ($fields as $k => $f) {
                $raw[$k] = $raw[$k] ?? '';
            }
            [$data, $errors] = crud_collect($m, $raw, null, 'import');
            $status = $errors ? 'invalid' : 'valid';
            $msg = $errors ? implode(' ', array_values($errors)) : null;
            if (!$errors) {
                if (isset($m['import_key'])) {
                    $key = ($m['import_key'])($data);
                    if ($key !== null && isset($seen[$key])) {
                        $status = 'duplicate';
                        $msg = 'Same as row ' . $seen[$key] . ' in this file.';
                    } elseif ($key !== null) {
                        $seen[$key] = $i + 2;
                    }
                }
                if ($status === 'valid' && isset($m['import_duplicate']) && ($dupId = ($m['import_duplicate'])($data))) {
                    $status = 'duplicate';
                    $msg = 'Matches existing record #' . $dupId . '.';
                }
            }
            $counts[$status]++;
            q_insert('import_rows', ['batch_id' => $b['id'], 'row_no' => $i + 2, 'row_data' => json_encode($raw, JSON_UNESCAPED_UNICODE), 'row_status' => $status, 'errors' => $msg]);
        }
        q("UPDATE import_batches SET column_map = ?, valid_rows = ?, invalid_rows = ?, duplicate_rows = ?, batch_status = 'validated' WHERE id = ?",
            [json_encode($map), $counts['valid'], $counts['invalid'], $counts['duplicate'], $b['id']]);
    });
    audit_log('import_validate', $m['key'], null, "Validated batch #{$b['id']}: {$counts['valid']} valid, {$counts['invalid']} invalid, {$counts['duplicate']} duplicates");
    if ($counts['invalid'] > 0 && $counts['invalid'] >= max(10, (int) (0.5 * count($rows)))) {
        security_event('import_anomaly', 'low', "Import batch #{$b['id']} for {$m['key']} has a high share of invalid rows ({$counts['invalid']} of " . count($rows) . ')');
    }
}

function import_process_chunk(array $m, array $b): array
{
    if (!in_array($b['batch_status'], ['approved', 'processing'], true)) {
        return ['done' => true, 'percent' => 100];
    }
    q("UPDATE import_batches SET batch_status = 'processing' WHERE id = ?", [$b['id']]);
    $rows = q_all("SELECT * FROM import_rows WHERE batch_id = ? AND row_status = 'valid' ORDER BY row_no LIMIT " . IMPORT_CHUNK, [$b['id']]);
    $imported = 0;
    foreach ($rows as $r) {
        $raw = json_decode($r['row_data'], true) ?: [];
        try {
            [$data, $errors, $values] = crud_collect($m, $raw, null, 'import');
            if ($errors) {
                q("UPDATE import_rows SET row_status = 'failed', errors = ? WHERE id = ?", [mb_substr(implode(' ', $errors), 0, 1000), $r['id']]);
                continue;
            }
            if (isset($m['import_duplicate']) && ($dupId = ($m['import_duplicate'])($data))) {
                q("UPDATE import_rows SET row_status = 'duplicate', errors = ? WHERE id = ?", ['Matches existing record #' . $dupId . '.', $r['id']]);
                continue;
            }
            $id = crud_save($m, $data, null, $values, $m['workflow'] ? 'draft' : null, true);
            q("UPDATE import_rows SET row_status = 'imported', record_id = ? WHERE id = ?", [$id, $r['id']]);
            $imported++;
        } catch (Throwable $e) {
            q("UPDATE import_rows SET row_status = 'failed', errors = ? WHERE id = ?", [$e instanceof ValidationError ? $e->getMessage() : 'Could not be saved (conflicting or invalid data).', $r['id']]);
        }
    }
    $total = (int) q_val("SELECT COUNT(*) FROM import_rows WHERE batch_id = ? AND row_status = 'imported'", [$b['id']]);
    $left = (int) q_val("SELECT COUNT(*) FROM import_rows WHERE batch_id = ? AND row_status = 'valid'", [$b['id']]);
    q('UPDATE import_batches SET imported_rows = ? WHERE id = ?', [$total, $b['id']]);
    if ($left === 0) {
        q("UPDATE import_batches SET batch_status = 'completed', completed_at = ? WHERE id = ?", [now(), $b['id']]);
        audit_log('import_complete', $m['key'], null, "Import batch #{$b['id']} completed: {$total} records created");
        if ((int) $b['uploaded_by'] !== uid()) {
            notify_user((int) $b['uploaded_by'], 'approval_done', 'Your import batch was approved and processed', "{$total} records were imported.", crud_url($m, ['action' => 'import', 'step' => 'preview', 'batch' => $b['id']]));
        }
    }
    $all = max(1, (int) $b['valid_rows']);
    return ['done' => $left === 0, 'imported' => $imported, 'percent' => (int) round(100 * ($all - $left) / $all)];
}
