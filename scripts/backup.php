<?php
/**
 * Scheduled encrypted database backup (run daily from cron or Windows Task Scheduler).
 *   php scripts/backup.php            create a backup and apply retention
 *   php scripts/backup.php --verify   also verify the new backup
 * Copy the files in storage/backups to off-server storage afterwards (see docs/BACKUP-RECOVERY.md).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/backup.php';

$rec = backup_create('scheduled');
echo "Backup created: {$rec['filename']} (" . human_size((int) $rec['file_size']) . ")\nSHA-256: {$rec['sha256']}\n";
if (in_array('--verify', $argv, true)) {
    [$ok, $msg] = backup_verify($rec);
    q('UPDATE backup_records SET verified_at = ?, verify_result = ? WHERE id = ?', [now(), mb_substr(($ok ? 'OK: ' : 'FAILED: ') . $msg, 0, 255), $rec['id']]);
    echo ($ok ? 'Verified: ' : 'VERIFICATION FAILED: ') . $msg . "\n";
    exit($ok ? 0 : 2);
}
