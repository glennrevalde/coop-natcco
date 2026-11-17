<?php
/**
 * Encrypted backups: run, verify, download (restricted and logged).
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/backup.php';

$me = require_perm('backups.view');
if (is_post()) {
    require_perm('backups.manage');
    $do = post('do');
    $hash = (string) q_val('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
    if (in_array($do, ['run', 'download'], true) && !password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        audit_log('backup_denied', 'backups', null, 'Password confirmation failed', 'failure');
        flash('error', 'Enter your password to confirm.');
        redirect('admin/backups.php');
    }
    if ($do === 'run') {
        try {
            $rec = backup_create('manual');
            flash('success', 'Backup created: ' . $rec['filename'] . ' (' . human_size((int) $rec['file_size']) . ').');
        } catch (Throwable $e) {
            flash('error', 'The backup failed. Details were recorded in Security monitoring.');
        }
    } elseif ($do === 'verify') {
        $rec = q_one("SELECT * FROM backup_records WHERE id = ? AND backup_status = 'completed'", [post_int('id')]);
        if ($rec) {
            [$ok, $msg] = backup_verify($rec);
            q('UPDATE backup_records SET verified_at = ?, verify_result = ? WHERE id = ?', [now(), mb_substr(($ok ? 'OK: ' : 'FAILED: ') . $msg, 0, 255), $rec['id']]);
            audit_log('backup_verified', 'backups', (int) $rec['id'], ($ok ? 'Verified backup: ' : 'Backup verification failed: ') . $msg, $ok ? 'success' : 'failure');
            if (!$ok) {
                security_event('backup_integrity', 'high', 'Backup ' . $rec['filename'] . ' failed verification: ' . $msg);
            }
            flash($ok ? 'success' : 'error', $msg);
        }
    } elseif ($do === 'download') {
        $rec = q_one("SELECT * FROM backup_records WHERE id = ? AND backup_status = 'completed'", [post_int('id')]);
        $path = $rec ? backup_dir() . '/' . basename($rec['filename']) : '';
        if (!$rec || !is_file($path)) {
            abort(404);
        }
        audit_log('backup_downloaded', 'backups', (int) $rec['id'], 'Downloaded encrypted backup ' . $rec['filename']);
        security_event('backup_downloaded', 'medium', 'Encrypted backup downloaded by ' . $me['username'], (int) $me['id']);
        no_cache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($rec['filename']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    redirect('admin/backups.php');
}
$rows = q_all('SELECT b.*, u.full_name FROM backup_records b LEFT JOIN users u ON u.id = b.created_by ORDER BY b.id DESC LIMIT 60');
$last = q_one("SELECT * FROM backup_records WHERE backup_status = 'completed' ORDER BY id DESC LIMIT 1");
admin_start('Backups', [['Backups', null]]);
page_bar('Database backups', 'Encrypted with the backup key from the configuration file. Keep an off-server copy as described in docs/BACKUP-RECOVERY.md.');
echo flash_render();
if (!$last || strtotime($last['created_at']) < time() - 2 * 86400) {
    echo '<div class="notice notice-warn">No successful backup in the last 48 hours. Check the scheduled task (scripts/backup.php).</div>';
}
if (can('backups.manage')) {
    echo '<form method="post" class="panel" data-confirm="Create a full database backup now?">' . csrf_field() . '<input type="hidden" name="do" value="run"><h2 class="panel-title">Create a backup now</h2>'
        . '<div class="grid"><div class="field"><label for="b_pw">Your password</label><input type="password" id="b_pw" name="password" autocomplete="current-password" required></div></div>'
        . '<div class="form-actions"><button class="btn btn-primary" type="submit">Run backup</button></div></form>';
}
echo '<section class="panel"><h2 class="panel-title">Backup history</h2><p class="muted small">Retention: ' . setting_int('backup_retention_days', 30) . ' days, always keeping the latest ' . setting_int('backup_min_keep', 7) . '.</p>';
echo '<div class="table-wrap"><table class="table table-cards"><thead><tr><th scope="col">Backup</th><th scope="col">Size</th><th scope="col">Created</th><th scope="col">Status</th><th scope="col">Last verification</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>';
foreach ($rows as $r) {
    echo '<tr><td data-label="Backup">' . e($r['filename']) . '<span class="sub">' . e(ucfirst($r['trigger_type'])) . ($r['sha256'] ? ', SHA-256 ' . e(substr($r['sha256'], 0, 12)) . '…' : '') . '</span></td><td data-label="Size">' . e(human_size((int) $r['file_size'])) . '</td>'
        . '<td data-label="Created">' . e(fmt_datetime($r['created_at'])) . '<span class="sub">' . e($r['full_name'] ?: 'Scheduled task') . '</span></td><td data-label="Status">' . status_text($r['backup_status']) . ($r['notes'] ? '<span class="sub">' . e($r['notes']) . '</span>' : '') . '</td>'
        . '<td data-label="Verified">' . ($r['verified_at'] ? e(fmt_datetime($r['verified_at'])) . '<span class="sub">' . e($r['verify_result']) . '</span>' : '<span class="muted">Not yet</span>') . '</td><td class="row-actions">';
    if ($r['backup_status'] === 'completed' && can('backups.manage')) {
        echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="do" value="verify"><input type="hidden" name="id" value="' . (int) $r['id'] . '"><button class="btn btn-secondary btn-sm" type="submit">Verify</button></form> ';
        echo '<details class="inline"><summary class="btn btn-ghost btn-sm">Download</summary><form method="post">' . csrf_field() . '<input type="hidden" name="do" value="download"><input type="hidden" name="id" value="' . (int) $r['id'] . '">'
            . '<label class="sr-only" for="dl' . (int) $r['id'] . '">Your password</label><input type="password" id="dl' . (int) $r['id'] . '" name="password" placeholder="Your password" required autocomplete="current-password"> <button class="btn btn-primary btn-sm" type="submit">Download</button></form></details>';
    }
    echo '</td></tr>';
}
if (!$rows) {
    echo '<tr><td colspan="6" class="muted">No backups yet.</td></tr>';
}
echo '</tbody></table></div></section>';
echo '<section class="panel panel-flat"><h2 class="panel-title">Restoring a backup</h2><ol class="small"><li>Copy the encrypted file to the server and run <code>php scripts/restore-backup.php path/to/file.sql.gz.enc</code>. It checks every block and writes a decrypted .sql file.</li>'
    . '<li>Import that file into an empty database with the mysql client, then delete the decrypted file.</li><li>Test restorations regularly and record the result as a verification.</li></ol></section>';
admin_end();
