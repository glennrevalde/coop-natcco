<?php
/**
 * Encrypted database backups.
 * Format: "CNPLBK2" + repeated frames [uint32 length][iv 12][tag 16][ciphertext],
 * each frame AES-256-GCM encrypted with the backup key and its index as associated data.
 * The plaintext is a gzip-compressed SQL dump.
 */
declare(strict_types=1);

const BACKUP_MAGIC = 'CNPLBK2';
const BACKUP_CHUNK = 1048576;

function backup_dir(): string
{
    $d = STORAGE_PATH . '/backups';
    if (!is_dir($d)) {
        mkdir($d, 0750, true);
    }
    return $d;
}

function backup_key(): string
{
    return decode_key((string) cfg('backup_key'));
}

/** Write a SQL dump of every table to a gzip file. */
function backup_dump_sql(string $gzPath): void
{
    $gz = gzopen($gzPath, 'wb6');
    $w = fn(string $s) => gzwrite($gz, $s);
    $w("-- COOP NATCCO Partylist database backup\n-- Created " . date('c') . "\n-- MySQL 5.5 compatible\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
    $tables = q_col("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
    $pdo = db();
    foreach ($tables as $t) {
        $create = q_one('SHOW CREATE TABLE ' . ident($t));
        $w("DROP TABLE IF EXISTS `{$t}`;\n" . $create['Create Table'] . ";\n\n");
        $st = $pdo->query('SELECT * FROM ' . ident($t));
        $batch = [];
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $vals = [];
            foreach ($row as $v) {
                $vals[] = $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v));
            }
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 200) {
                $w("INSERT INTO `{$t}` VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            $w("INSERT INTO `{$t}` VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        $w("\n");
    }
    $w("SET FOREIGN_KEY_CHECKS = 1;\n");
    gzclose($gz);
}

function backup_encrypt_file(string $in, string $out): void
{
    $key = backup_key();
    $src = fopen($in, 'rb');
    $dst = fopen($out, 'wb');
    fwrite($dst, BACKUP_MAGIC);
    $i = 0;
    while (!feof($src)) {
        $chunk = fread($src, BACKUP_CHUNK);
        if ($chunk === '' || $chunk === false) {
            break;
        }
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($chunk, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'chunk' . $i, 16);
        fwrite($dst, pack('N', strlen($ct)) . $iv . $tag . $ct);
        $i++;
    }
    fclose($src);
    fclose($dst);
}

function backup_decrypt_file(string $in, string $out): void
{
    $key = backup_key();
    $src = fopen($in, 'rb');
    if (fread($src, strlen(BACKUP_MAGIC)) !== BACKUP_MAGIC) {
        throw new RuntimeException('Not a COOP NATCCO backup file');
    }
    $dst = $out === 'php://memory' ? null : fopen($out, 'wb');
    $i = 0;
    while (!feof($src)) {
        $hdr = fread($src, 4);
        if ($hdr === '' || $hdr === false) {
            break;
        }
        $len = unpack('N', $hdr)[1];
        $iv = fread($src, 12);
        $tag = fread($src, 16);
        $ct = fread($src, $len);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'chunk' . $i);
        if ($plain === false) {
            throw new RuntimeException('Integrity check failed at chunk ' . $i . ' (wrong key or damaged file)');
        }
        if ($dst) {
            fwrite($dst, $plain);
        }
        $i++;
    }
    fclose($src);
    if ($dst) {
        fclose($dst);
    }
}

function backup_create(string $trigger = 'manual'): array
{
    @set_time_limit(0);
    $dir = backup_dir();
    $name = 'cnpl-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql.gz.enc';
    $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(6)) . '.sql.gz';
    try {
        backup_dump_sql($tmp);
        backup_encrypt_file($tmp, $dir . '/' . $name);
    } catch (Throwable $e) {
        @unlink($tmp);
        q_insert('backup_records', ['filename' => $name, 'file_size' => 0, 'is_encrypted' => 1, 'backup_status' => 'failed', 'trigger_type' => $trigger, 'notes' => mb_substr($e->getMessage(), 0, 255), 'created_by' => uid(), 'created_at' => now()]);
        security_event('backup_failed', 'high', 'Database backup failed: ' . mb_substr($e->getMessage(), 0, 200));
        throw $e;
    }
    @unlink($tmp);
    @chmod($dir . '/' . $name, 0600);
    $id = q_insert('backup_records', ['filename' => $name, 'file_size' => filesize($dir . '/' . $name), 'sha256' => hash_file('sha256', $dir . '/' . $name), 'is_encrypted' => 1,
        'backup_status' => 'completed', 'trigger_type' => $trigger, 'created_by' => uid(), 'created_at' => now()]);
    audit_log('backup_created', 'backups', $id, ucfirst($trigger) . ' backup created: ' . $name);
    backup_apply_retention();
    return q_one('SELECT * FROM backup_records WHERE id = ?', [$id]);
}

function backup_verify(array $rec): array
{
    $path = backup_dir() . '/' . basename($rec['filename']);
    if (!is_file($path)) {
        return [false, 'File is missing from backup storage'];
    }
    if ($rec['sha256'] && !hash_equals($rec['sha256'], hash_file('sha256', $path))) {
        return [false, 'Checksum does not match the recorded value'];
    }
    $tmp = backup_dir() . '/.verify-' . bin2hex(random_bytes(6)) . '.gz';
    try {
        backup_decrypt_file($path, $tmp);
        $gz = gzopen($tmp, 'rb');
        $head = gzread($gz, 200);
        $bytes = strlen((string) $head);
        while (!gzeof($gz)) {
            $bytes += strlen((string) gzread($gz, 1048576));
        }
        gzclose($gz);
        if (strpos((string) $head, 'COOP NATCCO Partylist database backup') === false) {
            return [false, 'Decrypted content is not a database dump'];
        }
        return [true, 'Decrypted and decompressed successfully (' . human_size($bytes) . ' of SQL)'];
    } catch (Throwable $e) {
        return [false, $e->getMessage()];
    } finally {
        @unlink($tmp);
    }
}

function backup_apply_retention(): int
{
    $days = max(7, setting_int('backup_retention_days', 30));
    $keep = max(1, setting_int('backup_min_keep', 7));
    $rows = q_all("SELECT * FROM backup_records WHERE backup_status = 'completed' ORDER BY id DESC");
    $removed = 0;
    foreach (array_slice($rows, $keep) as $r) {
        if (strtotime($r['created_at']) < time() - $days * 86400) {
            @unlink(backup_dir() . '/' . basename($r['filename']));
            q("UPDATE backup_records SET backup_status = 'deleted', notes = 'Removed by retention policy' WHERE id = ?", [$r['id']]);
            $removed++;
        }
    }
    if ($removed) {
        audit_log('backup_retention', 'backups', null, "Retention policy removed {$removed} old backup(s)");
    }
    return $removed;
}
