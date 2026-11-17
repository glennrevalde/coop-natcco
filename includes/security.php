<?php
/**
 * HTTP security headers, CSRF protection, throttling and form traps.
 */
declare(strict_types=1);

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    $trusted = (array) cfg('trusted_proxies', []);
    return $trusted && in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted, true)
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header_remove('X-Powered-By');
    $csp = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "media-src 'self'",
        "frame-src https://www.youtube-nocookie.com https://player.vimeo.com https://www.google.com",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "manifest-src 'self'",
        "worker-src 'self'",
    ];
    if (is_https()) {
        $csp[] = 'upgrade-insecure-requests';
    }
    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    if (is_https() && is_production()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}

// ------------------------------------------------------------------ CSRF
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_enforce(): void
{
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $max = ini_parse_bytes((string) ini_get('post_max_size'));
    if ($max > 0 && $len > $max) {
        abort(413, 'The upload is larger than the server allows. Choose a smaller file and try again.');
    }
    // Reject cross-origin form posts
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== 'null') {
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        $self = $_SERVER['HTTP_HOST'] ?? '';
        $originHost = $host . ($port ? ':' . $port : '');
        if (strcasecmp($originHost, $self) !== 0) {
            security_event('csrf_cross_origin', 'medium', 'Rejected cross-origin POST from ' . mb_substr($origin, 0, 100));
            abort(403, 'Cross-origin requests are not allowed.');
        }
    }
    $sent = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $expected = (string) ($_SESSION['_csrf'] ?? '');
    if ($expected === '' || $sent === '' || !hash_equals($expected, $sent)) {
        security_event('csrf_failed', 'low', 'Invalid or missing CSRF token on ' . mb_substr(strtok($_SERVER['REQUEST_URI'] ?? '', '?'), 0, 150));
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            json_out(['ok' => false, 'error' => 'Session expired. Reload the page.'], 403);
        }
        // 403 rather than the non-standard 419: Apache turns unknown status codes into 500.
        render_error_page(403, 'Session expired', 'Your form session expired. Go back, reload the page and try again.');
        exit;
    }
}

function ini_parse_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '') {
        return 0;
    }
    $n = (int) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g':
            $n *= 1024;
            // no break
        case 'm':
            $n *= 1024;
            // no break
        case 'k':
            $n *= 1024;
    }
    return $n;
}

// -------------------------------------------------------------- throttle
/**
 * Count an attempt. Returns false when the key is locked.
 */
function throttle_hit(string $bucket, string $key, int $max, int $windowSec, int $lockSec): bool
{
    $hash = hash('sha256', $bucket . '|' . $key);
    $row = q_one('SELECT * FROM throttles WHERE throttle_key = ?', [$hash]);
    $now = time();
    if ($row && $row['locked_until'] && strtotime($row['locked_until']) > $now) {
        return false;
    }
    if (!$row || strtotime($row['first_attempt_at']) < $now - $windowSec) {
        if ($row) {
            q('UPDATE throttles SET attempts = 1, first_attempt_at = ?, last_attempt_at = ?, locked_until = NULL WHERE id = ?', [now(), now(), $row['id']]);
        } else {
            q_insert('throttles', ['throttle_key' => $hash, 'bucket' => $bucket, 'attempts' => 1, 'first_attempt_at' => now(), 'last_attempt_at' => now()]);
        }
        return true;
    }
    $attempts = (int) $row['attempts'] + 1;
    $locked = $attempts >= $max ? date('Y-m-d H:i:s', $now + $lockSec) : null;
    q('UPDATE throttles SET attempts = ?, last_attempt_at = ?, locked_until = ? WHERE id = ?', [$attempts, now(), $locked, $row['id']]);
    return true;
}

function throttle_locked(string $bucket, string $key): bool
{
    $row = q_one('SELECT locked_until FROM throttles WHERE throttle_key = ?', [hash('sha256', $bucket . '|' . $key)]);
    return $row && $row['locked_until'] && strtotime($row['locked_until']) > time();
}

function throttle_clear(string $bucket, string $key): void
{
    q('DELETE FROM throttles WHERE throttle_key = ?', [hash('sha256', $bucket . '|' . $key)]);
}

/** Rate limit for public forms (per IP). Aborts with 429 when exceeded. */
function public_form_limit(string $bucket, int $max = 5, int $windowSec = 600): void
{
    $ip = client_ip();
    if (throttle_locked($bucket, $ip)) {
        abort(429);
    }
    throttle_hit($bucket, $ip, $max, $windowSec, $windowSec);
}

// ------------------------------------------------------------ form traps
function honeypot_field(): string
{
    $_SESSION['_form_started'] = time();
    return '<div class="hp" aria-hidden="true"><label for="hp_website">Leave this field empty</label><input type="text" id="hp_website" name="website_url" tabindex="-1" autocomplete="off"></div>';
}

/** True when the submission looks automated. */
function honeypot_tripped(): bool
{
    if (!empty($_POST['website_url'])) {
        return true;
    }
    $started = (int) ($_SESSION['_form_started'] ?? 0);
    return $started > 0 && (time() - $started) < 2;
}
