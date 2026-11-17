<?php
/**
 * Installation check: php scripts/check-requirements.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
$ok = true;
$line = function (bool $pass, string $msg, bool $required = true) use (&$ok) {
    if (!$pass && $required) {
        $ok = false;
    }
    echo ($pass ? '[ OK ] ' : ($required ? '[FAIL] ' : '[WARN] ')) . $msg . "\n";
};
$line(PHP_VERSION_ID >= 80100, 'PHP 8.1 or newer (found ' . PHP_VERSION . ')');
foreach (['pdo_mysql', 'openssl', 'mbstring', 'fileinfo', 'dom', 'session', 'zlib'] as $ext) {
    $line(extension_loaded($ext), "PHP extension {$ext}");
}
$line(extension_loaded('gd'), 'PHP extension gd (image re-encoding and thumbnails)', false);
$line(extension_loaded('zip'), 'PHP extension zip (Excel .xlsx import)', false);
$line(defined('PASSWORD_ARGON2ID'), 'Argon2id password hashing (bcrypt is used otherwise)', false);
require dirname(__DIR__) . '/includes/bootstrap.php';
try {
    $v = (string) q_val('SELECT VERSION()');
    $line(true, 'Database connection (' . $v . ')');
    $line(strpos($v, '5.5') === 0 || stripos($v, 'maria') !== false || version_compare($v, '5.5', '>='), 'MySQL version is 5.5 or compatible');
    $line((int) q_val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()") >= 60, 'Database tables are installed');
} catch (Throwable $e) {
    $line(false, 'Database connection: ' . $e->getMessage());
}
foreach (['', '/logs', '/sessions', '/uploads', '/imports', '/backups'] as $d) {
    $line(is_dir(STORAGE_PATH . $d) && is_writable(STORAGE_PATH . $d), 'Writable: ' . STORAGE_PATH . $d);
}
try {
    decode_key((string) cfg('index_key'));
    decode_key((string) cfg('backup_key'));
    crypto_key((string) cfg('encryption_active_key'));
    $line(true, 'Encryption keys are valid 32-byte keys');
} catch (Throwable $e) {
    $line(false, 'Encryption keys: ' . $e->getMessage());
}
echo "\nProduction readiness (warnings are expected on a local XAMPP copy):\n";
$line(cfg('app_env') === 'production', "app_env should be 'production' on the live server (now '" . cfg('app_env') . "')", false);
$line(strtolower((string) cfg('db.user')) !== 'root', 'The database account should be a least-privilege account, not root (now ' . cfg('db.user') . ')', cfg('app_env') === 'production');
$line((bool) cfg('force_https'), 'force_https should be true on the live server', false);
$line(strpos((string) $GLOBALS['CNPL_CONFIG_FILE'], APP_ROOT . '/config/') !== 0, 'The configuration file should be outside the web folder (now ' . $GLOBALS['CNPL_CONFIG_FILE'] . ')', false);
echo $ok ? "\nAll required checks passed.\n" : "\nSome required checks failed.\n";
exit($ok ? 0 : 1);
