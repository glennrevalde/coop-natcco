<?php
/**
 * Unit-test bootstrap.
 *
 * Loads the application's function library in CLI mode with a test-only
 * configuration (tests/fixtures/unit-config.php). The configuration points the
 * database at a closed port, so unit tests never read or change real data:
 * anything that would touch the database falls back to its defaults.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
if (!defined('CNPL_TESTING')) {
    define('CNPL_TESTING', true);
}
putenv('CNPL_CONFIG=' . __DIR__ . '/fixtures/unit-config.php');

require_once __DIR__ . '/lib/TestCase.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/totp.php';
require_once APP_ROOT . '/includes/crud_import.php';

// Tests must not depend on the developer's exception handler printing to STDERR and exiting.
restore_exception_handler();

/** Write a temporary file and return its path (removed at shutdown). */
function test_temp_file(string $contents, string $suffix = ''): string
{
    $path = tempnam(sys_get_temp_dir(), 'cnpl') . $suffix;
    file_put_contents($path, $contents);
    register_shutdown_function(function () use ($path) {
        if (is_file($path)) {
            @unlink($path);
        }
    });
    return $path;
}
