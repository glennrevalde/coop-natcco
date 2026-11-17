<?php
/**
 * Decrypt and check a backup, writing a plain .sql file for import with the mysql client.
 *   php scripts/restore-backup.php storage/backups/cnpl-backup-....sql.gz.enc [output.sql]
 * Then:  mysql -u <admin> -p <empty_database> < output.sql   and delete output.sql afterwards.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/backup.php';

$in = $argv[1] ?? '';
if (!is_file($in)) {
    fwrite(STDERR, "Usage: php scripts/restore-backup.php <backup file> [output.sql]\n");
    exit(1);
}
$out = $argv[2] ?? preg_replace('/\.sql\.gz\.enc$/', '', basename($in)) . '.sql';
$tmp = $out . '.gz';
backup_decrypt_file($in, $tmp);
$gz = gzopen($tmp, 'rb');
$fh = fopen($out, 'wb');
while (!gzeof($gz)) {
    fwrite($fh, (string) gzread($gz, 1048576));
}
gzclose($gz);
fclose($fh);
unlink($tmp);
chmod($out, 0600);
audit_log('backup_restore_prepared', 'backups', null, 'Decrypted backup ' . basename($in) . ' for restoration (CLI)');
echo "Integrity checks passed. Decrypted SQL written to {$out}\nImport it into an empty database, then delete the file.\n";
