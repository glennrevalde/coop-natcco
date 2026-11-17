<?php
/**
 * Loaded first by every page. Sets up configuration, error handling,
 * database, security headers, session, CSRF protection and the current user.
 */
declare(strict_types=1);

if (defined('APP_ROOT')) {
    return;
}
define('APP_ROOT', dirname(__DIR__));
define('IS_CLI', PHP_SAPI === 'cli');

// ---------------------------------------------------------------- config
(function () {
    $candidates = [];
    $env = getenv('CNPL_CONFIG');
    if ($env) {
        $candidates[] = $env;
    }
    $candidates[] = dirname(APP_ROOT) . '/coop-natcco-private/config.php';
    $candidates[] = APP_ROOT . '/config/config.php';
    foreach ($candidates as $file) {
        if (is_file($file)) {
            $GLOBALS['CNPL_CONFIG'] = require $file;
            $GLOBALS['CNPL_CONFIG_FILE'] = $file;
            return;
        }
    }
    http_response_code(500);
    echo 'Configuration file not found. Copy config/config.sample.php and follow docs/INSTALLATION.md.';
    exit;
})();

function cfg(string $key, $default = null)
{
    $value = $GLOBALS['CNPL_CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function is_production(): bool
{
    return cfg('app_env') === 'production';
}

define('STORAGE_PATH', rtrim(cfg('storage_path') ?: APP_ROOT . '/storage', '/\\'));
date_default_timezone_set((string) cfg('timezone', 'Asia/Manila'));
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------- errors
error_reporting(E_ALL);
ini_set('display_errors', cfg('app_debug') && !is_production() ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-errors.log');

set_error_handler(function (int $no, string $str, string $file, int $line) {
    if (!(error_reporting() & $no) || $no === E_DEPRECATED || $no === E_USER_DEPRECATED) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

set_exception_handler(function (Throwable $e) {
    $ref = strtoupper(bin2hex(random_bytes(4)));
    $line = sprintf("[%s] ref=%s %s: %s in %s:%d\n%s\n", date('Y-m-d H:i:s'), $ref, get_class($e), $e->getMessage(),
        $e->getFile(), $e->getLine(), $e->getTraceAsString());
    @file_put_contents(STORAGE_PATH . '/logs/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    if (IS_CLI) {
        fwrite(STDERR, $line);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $debug = cfg('app_debug') && !is_production();
    render_error_page(500, 'Something went wrong', 'The request could not be completed. If this keeps happening, contact the system administrator and mention reference ' . $ref . '.', $debug ? $line : null);
    exit;
});

require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/crypto.php';
require APP_ROOT . '/includes/settings.php';
require APP_ROOT . '/includes/security.php';
require APP_ROOT . '/includes/audit.php';
require APP_ROOT . '/includes/session.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/scope.php';
require APP_ROOT . '/includes/mailer.php';
require APP_ROOT . '/includes/notify.php';
require APP_ROOT . '/includes/media.php';
require APP_ROOT . '/includes/sanitize.php';

if (!IS_CLI) {
    if (cfg('force_https') && !is_https()) {
        $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? '');
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
    send_security_headers();
    session_boot();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !defined('SKIP_CSRF')) {
        csrf_enforce();
    }
    auth_boot();
}
