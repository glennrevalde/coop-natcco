<?php
/**
 * Hardened PHP session configuration.
 */
declare(strict_types=1);

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $path = STORAGE_PATH . '/sessions';
    if (is_dir($path) && is_writable($path)) {
        session_save_path($path);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    if (PHP_VERSION_ID < 80400) {
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
    }
    ini_set('session.gc_maxlifetime', (string) (max(1, setting_int('session_absolute_hours', 12)) * 3600));
    session_name(is_https() ? '__Host-CNPLSESS' : 'CNPLSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => is_https() ? '/' : (base_path() === '' ? '/' : base_path() . '/'),
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Clear the local session without touching the database row. */
function session_reset_local(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
