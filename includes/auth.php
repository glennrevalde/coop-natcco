<?php
/**
 * Authentication, sessions, MFA state, permissions (RBAC) and password policy.
 */
declare(strict_types=1);

const DUMMY_HASH = '$2y$12$1hiOJicnduoLeCrdqaLYTO.DfaICrfJXtFBXm4u1aUDlI3wCR90xW';

function auth_boot(): void
{
    $GLOBALS['CNPL_USER'] = null;
    $uid = $_SESSION['uid'] ?? null;
    $tok = $_SESSION['sid_token'] ?? null;
    if (!$uid || !$tok) {
        return;
    }
    $sess = q_one('SELECT * FROM user_sessions WHERE session_hash = ? AND user_id = ?', [token_hash((string) $tok), (int) $uid]);
    if (!$sess || $sess['revoked_at']) {
        session_reset_local();
        flash('info', 'Your session has ended. Please sign in again.');
        return;
    }
    $idle = max(5, setting_int('session_idle_minutes', 30)) * 60;
    $abs = max(1, setting_int('session_absolute_hours', 12)) * 3600;
    $last = strtotime($sess['last_activity_at']);
    if (time() - $last > $idle || time() - strtotime($sess['created_at']) > $abs) {
        q('UPDATE user_sessions SET revoked_at = ?, revoked_reason = ? WHERE id = ?', [now(), 'timeout', $sess['id']]);
        auth_event('session_expired', (int) $uid);
        session_reset_local();
        flash('info', 'Your session expired after a period of inactivity. Please sign in again.');
        return;
    }
    $user = q_one('SELECT * FROM users WHERE id = ?', [(int) $uid]);
    if (!$user || $user['account_status'] !== 'active') {
        q('UPDATE user_sessions SET revoked_at = ?, revoked_reason = ? WHERE id = ?', [now(), 'account_inactive', $sess['id']]);
        session_reset_local();
        return;
    }
    if (time() - $last > 60) {
        q('UPDATE user_sessions SET last_activity_at = ? WHERE id = ?', [now(), $sess['id']]);
    }
    unset($user['password_hash']);
    $user['session_row_id'] = (int) $sess['id'];
    $user['mfa_verified'] = (int) $sess['mfa_verified'];
    $GLOBALS['CNPL_USER'] = $user;
}

function current_user(): ?array
{
    return $GLOBALS['CNPL_USER'] ?? null;
}

function uid(): ?int
{
    return isset($GLOBALS['CNPL_USER']['id']) ? (int) $GLOBALS['CNPL_USER']['id'] : null;
}

function preferred_password_algo()
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

function password_make(string $plain): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
}

function password_policy_errors(string $pw, array $user = []): array
{
    $errors = [];
    $min = max(10, setting_int('password_min_length', 12));
    if (mb_strlen($pw) < $min) {
        $errors[] = "Use at least {$min} characters.";
    }
    if (mb_strlen($pw) > 128) {
        $errors[] = 'Use at most 128 characters.';
    }
    $classes = (int) preg_match('/[a-z]/', $pw) + (int) preg_match('/[A-Z]/', $pw) + (int) preg_match('/\d/', $pw) + (int) preg_match('/[^a-zA-Z\d]/', $pw);
    if ($classes < 3) {
        $errors[] = 'Mix at least three of: lowercase letters, uppercase letters, numbers and symbols.';
    }
    $lower = mb_strtolower($pw);
    foreach ([$user['username'] ?? '', strtok((string) ($user['email'] ?? ''), '@')] as $part) {
        if ($part && mb_strlen($part) >= 4 && strpos($lower, mb_strtolower($part)) !== false) {
            $errors[] = 'Do not include your username or email address.';
            break;
        }
    }
    $common = ['password', 'qwerty', '123456', 'letmein', 'welcome', 'admin', 'coopnatcco', 'natcco', 'partylist', 'iloveyou', 'p@ssw0rd'];
    foreach ($common as $c) {
        if (strpos(preg_replace('/[^a-z0-9@]/', '', $lower), $c) !== false) {
            $errors[] = 'Avoid common words and patterns such as "' . $c . '".';
            break;
        }
    }
    return $errors;
}

/**
 * Check credentials. Returns ['status' => ok|mfa|fail|locked].
 * The same generic message is shown for every failure to prevent account enumeration.
 */
function auth_attempt(string $identifier, string $password): array
{
    $identifier = trim($identifier);
    $idKey = mb_strtolower($identifier);
    $ip = client_ip();
    $threshold = max(3, setting_int('lockout_threshold', 5));
    $lockSec = max(1, setting_int('lockout_minutes', 15)) * 60;

    if (throttle_locked('login_id', $idKey) || throttle_locked('login_ip', $ip)) {
        auth_event('login_blocked', null, $identifier, 'Attempt during lockout');
        return ['status' => 'locked'];
    }
    $user = $identifier === '' ? null : q_one('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$identifier, $identifier]);
    $valid = password_verify($password, $user['password_hash'] ?? DUMMY_HASH) && $user !== null;

    if (!$valid || $user['account_status'] !== 'active') {
        $reason = !$user ? 'unknown_account' : (!$valid ? 'bad_password' : 'account_' . $user['account_status']);
        throttle_hit('login_id', $idKey, $threshold, $lockSec, $lockSec);
        throttle_hit('login_ip', $ip, $threshold * 4, $lockSec, $lockSec);
        auth_event('login_failed', $user['id'] ?? null, $identifier, $reason);
        audit_log('login_failed', 'auth', isset($user['id']) ? (int) $user['id'] : null, 'Failed sign-in for "' . mb_substr($identifier, 0, 80) . '"', 'failure');
        if (throttle_locked('login_id', $idKey)) {
            auth_event('account_locked', $user['id'] ?? null, $identifier, "Locked after {$threshold} failed attempts");
            security_event('account_lockout', 'medium', 'Sign-in locked for "' . mb_substr($identifier, 0, 80) . '" after repeated failures', isset($user['id']) ? (int) $user['id'] : null);
        }
        $recent = (int) q_val("SELECT COUNT(*) FROM authentication_events WHERE ip_address = ? AND event_type = 'login_failed' AND created_at >= ?", [$ip, date('Y-m-d H:i:s', time() - 900)]);
        if ($recent === 20) {
            security_event('bruteforce_suspected', 'high', "20 failed sign-ins from {$ip} within 15 minutes");
        }
        return ['status' => 'fail'];
    }

    throttle_clear('login_id', $idKey);
    if (password_needs_rehash($user['password_hash'], preferred_password_algo())) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_make($password), $user['id']]);
    }
    if ((int) $user['mfa_enabled'] === 1) {
        session_regenerate_id(true);
        $_SESSION['mfa_pending'] = ['uid' => (int) $user['id'], 'at' => time(), 'tries' => 0];
        auth_event('password_ok_mfa_required', (int) $user['id'], $identifier);
        return ['status' => 'mfa', 'user' => $user];
    }
    complete_login($user, false);
    return ['status' => 'ok', 'user' => $user];
}

function complete_login(array $user, bool $mfaVerified): void
{
    session_regenerate_id(true);
    $flash = $_SESSION['_flash'] ?? null;
    $_SESSION = [];
    if ($flash) {
        $_SESSION['_flash'] = $flash;
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['sid_token'] = $token;
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    q_insert('user_sessions', [
        'user_id' => (int) $user['id'], 'session_hash' => token_hash($token), 'ip_address' => client_ip(),
        'user_agent' => user_agent(), 'mfa_verified' => $mfaVerified ? 1 : 0, 'created_at' => now(), 'last_activity_at' => now(),
    ]);
    q('UPDATE users SET last_login_at = ?, last_login_ip = ? WHERE id = ?', [now(), client_ip(), $user['id']]);
    unset($user['password_hash']);
    $GLOBALS['CNPL_USER'] = $user;
    auth_event('login_success', (int) $user['id'], $user['username'], $mfaVerified ? 'with MFA' : 'password only');
    audit_log('login_success', 'auth', (int) $user['id'], 'Signed in' . ($mfaVerified ? ' with MFA' : ''));
}

function auth_logout(string $reason = 'logout'): void
{
    $u = current_user();
    if ($u) {
        q('UPDATE user_sessions SET revoked_at = ?, revoked_reason = ? WHERE id = ?', [now(), $reason, $u['session_row_id']]);
        auth_event('logout', (int) $u['id'], $u['username']);
        audit_log('logout', 'auth', (int) $u['id'], 'Signed out');
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $GLOBALS['CNPL_USER'] = null;
}

function revoke_user_sessions(int $userId, string $reason, ?int $exceptSessionId = null): int
{
    $params = [now(), $reason, $userId];
    $sql = 'UPDATE user_sessions SET revoked_at = ?, revoked_reason = ? WHERE user_id = ? AND revoked_at IS NULL';
    if ($exceptSessionId) {
        $sql .= ' AND id <> ?';
        $params[] = $exceptSessionId;
    }
    return q($sql, $params)->rowCount();
}

// ------------------------------------------------------------- RBAC
function user_role_rows(int $userId): array
{
    static $cache = [];
    if (!isset($cache[$userId])) {
        $cache[$userId] = q_all("SELECT r.* FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND ur.assignment_status = 'active' ORDER BY r.rank_level DESC", [$userId]);
    }
    return $cache[$userId];
}

function user_permissions(?int $userId = null): array
{
    static $cache = [];
    $userId = $userId ?? uid();
    if (!$userId) {
        return [];
    }
    if (!isset($cache[$userId])) {
        $rows = q_col("SELECT DISTINCT p.slug FROM user_roles ur JOIN role_permissions rp ON rp.role_id = ur.role_id
                       JOIN permissions p ON p.id = rp.permission_id WHERE ur.user_id = ? AND ur.assignment_status = 'active'", [$userId]);
        $cache[$userId] = array_fill_keys($rows, true);
    }
    return $cache[$userId];
}

function can(string $perm, ?int $userId = null): bool
{
    return isset(user_permissions($userId)[$perm]);
}

function can_any(array $perms): bool
{
    foreach ($perms as $p) {
        if (can($p)) {
            return true;
        }
    }
    return false;
}

function has_role(string $slug, ?int $userId = null): bool
{
    $userId = $userId ?? uid();
    if (!$userId) {
        return false;
    }
    foreach (user_role_rows($userId) as $r) {
        if ($r['slug'] === $slug) {
            return true;
        }
    }
    return false;
}

function is_super(?int $userId = null): bool
{
    return has_role('super_admin', $userId);
}

function user_rank(?int $userId = null): int
{
    $userId = $userId ?? uid();
    $rows = $userId ? user_role_rows($userId) : [];
    return $rows ? (int) $rows[0]['rank_level'] : 0;
}

function is_privileged_user(int $userId): bool
{
    foreach (user_role_rows($userId) as $r) {
        if ((int) $r['is_privileged'] === 1) {
            return true;
        }
    }
    return false;
}

function mfa_enrollment_required(array $user): bool
{
    return setting('mfa_required_privileged', '1') === '1' && (int) $user['mfa_enabled'] === 0 && is_privileged_user((int) $user['id']);
}

/** Gate for every authenticated page. */
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        redirect('account/login.php');
    }
    no_cache_headers();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $free = ['logout.php', 'change-password.php', 'mfa-setup.php'];
    if ((int) $u['must_change_password'] === 1 && !in_array($script, $free, true)) {
        redirect('account/change-password.php');
    }
    if (mfa_enrollment_required($u) && !in_array($script, $free, true)) {
        flash('info', 'Your role requires multifactor authentication. Set it up to continue.');
        redirect('account/mfa-setup.php');
    }
    return $u;
}

function require_perm($perm): array
{
    $u = require_login();
    $perms = (array) $perm;
    if (!can_any($perms)) {
        audit_log('access_denied', 'auth', null, 'Missing permission ' . implode(' or ', $perms) . ' for ' . mb_substr(strtok($_SERVER['REQUEST_URI'] ?? '', '?'), 0, 120), 'denied');
        abort(403);
    }
    return $u;
}

function deny(string $why = ''): void
{
    audit_log('access_denied', 'auth', null, $why ?: ('Denied ' . mb_substr($_SERVER['REQUEST_URI'] ?? '', 0, 150)), 'denied');
    abort(403);
}

// ------------------------------------------------------------- tokens
function create_user_token(int $userId, string $purpose, int $minutes): string
{
    $token = bin2hex(random_bytes(32));
    q("UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL", [now(), $userId, $purpose]);
    q_insert('password_reset_tokens', [
        'user_id' => $userId, 'token_hash' => token_hash($token), 'purpose' => $purpose,
        'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60), 'created_ip' => IS_CLI ? null : client_ip(), 'created_at' => now(),
    ]);
    return $token;
}

function find_user_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $row = q_one('SELECT t.*, u.username, u.email, u.full_name, u.account_status FROM password_reset_tokens t JOIN users u ON u.id = t.user_id
                  WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > ?', [token_hash($token), now()]);
    return $row ?: null;
}
