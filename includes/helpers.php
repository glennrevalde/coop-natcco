<?php
/**
 * General helpers: escaping, URLs, flash messages, formatting, input.
 */
declare(strict_types=1);

final class ValidationError extends RuntimeException
{
}

final class HttpError extends RuntimeException
{
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Escape a string for use inside a URL query value. */
function eu($value): string
{
    return rawurlencode((string) $value);
}

function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $base = rtrim((string) cfg('base_url', ''), '/');
    }
    return $base;
}

function url(string $path = '', array $params = []): string
{
    if (preg_match('#^https?://#i', $path)) {
        $u = $path;
    } else {
        $u = base_path() . '/' . ltrim($path, '/');
    }
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    if ($params) {
        $u .= (strpos($u, '?') === false ? '?' : '&') . http_build_query($params);
    }
    return $u;
}

function abs_url(string $path = '', array $params = []): string
{
    $rel = url($path, $params);
    $root = rtrim((string) cfg('app_url', ''), '/');
    $base = base_path();
    if ($base !== '' && substr($rel, 0, strlen($base)) === $base) {
        $rel = substr($rel, strlen($base));
    }
    return $root . $rel;
}

/**
 * Release number of the CSS, JS and icon files. Raise it with every release that
 * changes them, so browsers and the app's service worker fetch the new files.
 */
const ASSET_VERSION = '1.1.0';

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = ASSET_VERSION . (is_file($file) ? '.' . filemtime($file) : '');
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Current request URL path + query (used for return links). */
function current_url(array $override = []): string
{
    $q = $_GET;
    foreach ($override as $k => $v) {
        if ($v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $path . ($q ? '?' . http_build_query($q) : '');
}

function redirect(string $to, int $code = 303): void
{
    if (!preg_match('#^(https?://|/)#', $to)) {
        $to = url($to);
    }
    header('Location: ' . $to, true, $code);
    exit;
}

/** Only allow redirects back into this application. */
function safe_return(?string $to, string $fallback): string
{
    if ($to && preg_match('#^/[^/\\\\]#', $to) && strpos($to, base_path() . '/') === 0) {
        return $to;
    }
    return url($fallback);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): string
{
    $out = '';
    foreach ($_SESSION['_flash'] ?? [] as $f) {
        $role = $f['type'] === 'error' ? 'alert' : 'status';
        $out .= '<div class="notice notice-' . e($f['type']) . '" role="' . $role . '">' . e($f['message']) . '</div>';
    }
    unset($_SESSION['_flash']);
    return $out;
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function get(string $key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    return (is_string($v) && ctype_digit($v)) ? (int) $v : $default;
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? null;
    return (is_string($v) && ctype_digit($v)) ? (int) $v : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function slugify(string $text, int $max = 120): string
{
    $text = mb_strtolower($text);
    $text = strtr($text, ['ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return substr($text !== '' ? $text : 'item', 0, $max);
}

function name_key(?string $first, ?string $last, ?string $middle = ''): string
{
    $n = fn($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower((string) $s)));
    return mb_substr($n($last) . '|' . $n($first) . '|' . $n($middle), 0, 191);
}

function coop_name_key(string $name): string
{
    $s = mb_strtolower($name);
    $s = preg_replace('/\b(cooperative|coop|co-op|inc|incorporated|the)\b/u', ' ', $s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', (string) $s);
    return mb_substr(trim(preg_replace('/\s+/', ' ', (string) $s)), 0, 191);
}

function full_name(array $r, string $prefix = ''): string
{
    $parts = [$r[$prefix . 'first_name'] ?? '', $r[$prefix . 'middle_name'] ?? '', $r[$prefix . 'last_name'] ?? '', $r[$prefix . 'suffix'] ?? ''];
    $mid = trim((string) $parts[1]);
    $name = trim($parts[0] . ' ' . ($mid !== '' ? mb_substr($mid, 0, 1) . '. ' : '') . $parts[2] . ' ' . $parts[3]);
    return preg_replace('/\s+/', ' ', $name);
}

function fmt_date(?string $d, string $format = 'M j, Y'): string
{
    if (!$d || strpos($d, '0000') === 0) {
        return '';
    }
    $t = strtotime($d);
    return $t ? date($format, $t) : '';
}

function fmt_datetime(?string $d): string
{
    return fmt_date($d, 'M j, Y g:i A');
}

function fmt_num($n, int $dec = 0): string
{
    return $n === null || $n === '' ? '' : number_format((float) $n, $dec);
}

function fmt_pct($part, $whole, int $dec = 2): string
{
    if (!$whole) {
        return '';
    }
    return number_format(100 * (float) $part / (float) $whole, $dec) . '%';
}

function time_ago(?string $d): string
{
    if (!$d) {
        return '';
    }
    $diff = time() - strtotime($d);
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' hr ago';
    }
    if ($diff < 86400 * 7) {
        return floor($diff / 86400) . ' days ago';
    }
    return fmt_date($d);
}

function mask_email(?string $email): string
{
    if (!$email || strpos($email, '@') === false) {
        return $email ? '•••' : '';
    }
    [$u, $d] = explode('@', $email, 2);
    return mb_substr($u, 0, 1) . str_repeat('•', max(2, min(6, mb_strlen($u) - 1))) . '@' . $d;
}

function mask_phone(?string $phone): string
{
    if (!$phone) {
        return '';
    }
    $digits = preg_replace('/\D/', '', $phone);
    return str_repeat('•', max(0, strlen($digits) - 3)) . substr($digits, -3);
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = (array) cfg('trusted_proxies', []);
    if ($trusted && in_array($ip, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $cand = end($parts);
        if (filter_var($cand, FILTER_VALIDATE_IP)) {
            $ip = $cand;
        }
    }
    return substr($ip, 0, 45);
}

function user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function random_code(int $len = 10, string $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'): string
{
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

/** Prevent spreadsheet formula injection in exported cells. */
function csv_safe($v): string
{
    $s = (string) ($v ?? '');
    if ($s !== '' && (in_array($s[0], ['=', '@', "\t", "\r"], true) || (in_array($s[0], ['+', '-'], true) && !is_numeric($s)))) {
        $s = "'" . $s;
    }
    return $s;
}

/** Remove formula prefixes from imported cells. */
function import_clean($v): string
{
    $s = trim((string) ($v ?? ''));
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    while ($s !== '' && in_array($s[0], ['=', '+', '@', "\t", "\r"], true)) {
        $s = ltrim(substr($s, 1));
    }
    if ($s !== '' && $s[0] === '-' && !is_numeric($s)) {
        $s = ltrim(substr($s, 1));
    }
    return $s;
}

function label_from_key(string $key): string
{
    return ucfirst(str_replace('_', ' ', $key));
}

/** Plain-text status label (no capsule), colored by meaning. */
function status_text(?string $status, ?string $label = null): string
{
    $status = (string) $status;
    $tone = [
        'approved' => 'ok', 'verified' => 'ok', 'active' => 'ok', 'published' => 'ok', 'resolved' => 'ok', 'current' => 'ok',
        'completed' => 'ok', 'done' => 'ok', 'attended' => 'ok', 'registered' => 'ok', 'closed' => 'muted', 'sent' => 'ok',
        'pending' => 'warn', 'in_review' => 'warn', 'awaiting_response' => 'warn', 'needs_update' => 'warn', 'waitlisted' => 'warn',
        'unverified' => 'warn', 'scheduled' => 'info', 'open' => 'info', 'assigned' => 'info', 'in_progress' => 'info',
        'received' => 'info', 'new' => 'info', 'draft' => 'muted', 'archived' => 'muted', 'inactive' => 'muted', 'ended' => 'muted',
        'replaced' => 'muted', 'cancelled' => 'bad', 'rejected' => 'bad', 'suspended' => 'bad', 'dissolved' => 'bad',
        'terminated' => 'bad', 'deactivated' => 'bad', 'failed' => 'bad', 'denied' => 'bad', 'critical' => 'bad', 'high' => 'bad',
        'urgent' => 'bad', 'medium' => 'warn', 'low' => 'muted', 'normal' => 'info', 'postponed' => 'warn', 'escalated' => 'bad',
        'contained' => 'warn', 'dismissed' => 'muted', 'reviewed' => 'ok', 'success' => 'ok', 'failure' => 'bad',
    ][$status] ?? 'muted';
    return '<span class="st st-' . $tone . '">' . e($label ?? label_from_key($status)) . '</span>';
}

function render_error_page(int $code, string $title, string $message, ?string $debug = null): void
{
    if (headers_sent()) {
        // Part of the page was already sent: show the error inline without a second document.
        echo '<div class="notice notice-error" role="alert"><strong>' . e($title) . '.</strong> ' . e($message) . '</div>' . ($debug ? '<pre class="debug">' . e($debug) . '</pre>' : '');
        return;
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
    }
    $css = function_exists('asset') ? asset('css/site.css') : '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . '</title><link rel="stylesheet" href="' . e($css) . '"></head><body class="error-body">'
        . '<main class="error-wrap"><p class="error-code">' . $code . '</p><h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<p><a class="btn btn-primary" href="' . e(url('index.php')) . '">Go to the home page</a></p>'
        . ($debug ? '<pre class="debug">' . e($debug) . '</pre>' : '') . '</main></body></html>';
}

function abort(int $code, string $message = ''): void
{
    $titles = [400 => 'Bad request', 403 => 'Access denied', 404 => 'Page not found', 405 => 'Method not allowed',
        413 => 'Upload too large', 429 => 'Too many requests', 500 => 'Server error'];
    $defaults = [403 => 'You do not have permission to open this page.', 404 => 'The page or record you are looking for does not exist or is not available to you.',
        429 => 'Too many attempts. Please wait a few minutes and try again.'];
    render_error_page($code, $titles[$code] ?? 'Error', $message ?: ($defaults[$code] ?? 'The request could not be completed.'));
    exit;
}

/** Simple pager: returns [page, perPage, offset]. */
function pager_params(int $default = 25): array
{
    $per = get_int('per', $default);
    if (!in_array($per, [10, 25, 50, 100], true)) {
        $per = $default;
    }
    $page = max(1, get_int('page', 1));
    return [$page, $per, ($page - 1) * $per];
}

function pager_html(int $total, int $page, int $per): string
{
    $pages = (int) max(1, ceil($total / $per));
    if ($total === 0) {
        return '';
    }
    $from = ($page - 1) * $per + 1;
    $to = min($total, $page * $per);
    $h = '<nav class="pager" aria-label="Pagination"><span class="pager-info">Showing ' . fmt_num($from) . ' to ' . fmt_num($to) . ' of ' . fmt_num($total) . '</span><span class="pager-links">';
    if ($page > 1) {
        $h .= '<a href="' . e(current_url(['page' => $page - 1])) . '" rel="prev">Previous</a>';
    }
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    if ($start > 1) {
        $h .= '<a href="' . e(current_url(['page' => 1])) . '">1</a>' . ($start > 2 ? '<span class="gap">…</span>' : '');
    }
    for ($i = $start; $i <= $end; $i++) {
        $h .= $i === $page ? '<span class="current" aria-current="page">' . $i . '</span>' : '<a href="' . e(current_url(['page' => $i])) . '">' . $i . '</a>';
    }
    if ($end < $pages) {
        $h .= ($end < $pages - 1 ? '<span class="gap">…</span>' : '') . '<a href="' . e(current_url(['page' => $pages])) . '">' . $pages . '</a>';
    }
    if ($page < $pages) {
        $h .= '<a href="' . e(current_url(['page' => $page + 1])) . '" rel="next">Next</a>';
    }
    return $h . '</span></nav>';
}

function excerpt(?string $html, int $len = 180): string
{
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8')));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len - 1)) . '…' : $t;
}

function valid_date(?string $d): bool
{
    if (!$d || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return false;
    }
    [$y, $m, $dd] = array_map('intval', explode('-', $d));
    return checkdate($m, $dd, $y) && $y >= 1900 && $y <= 2100;
}

function parse_date_input(?string $s): ?string
{
    $s = trim((string) $s);
    if ($s === '') {
        return null;
    }
    if (valid_date($s)) {
        return $s;
    }
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m) && checkdate((int) $m[1], (int) $m[2], (int) $m[3])) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
    }
    if (is_numeric($s) && (float) $s > 20000 && (float) $s < 80000) { // Excel serial date
        return date('Y-m-d', (int) round(((float) $s - 25569) * 86400));
    }
    $t = strtotime($s);
    return $t ? date('Y-m-d', $t) : null;
}

function parse_datetime_input(?string $s): ?string
{
    $s = trim((string) $s);
    if ($s === '') {
        return null;
    }
    $s = str_replace('T', ' ', $s);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $s)) {
        $t = strtotime($s);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }
    return null;
}

function public_published_sql(string $alias = ''): string
{
    $a = $alias ? $alias . '.' : '';
    return "{$a}status = 'published' AND {$a}archived_at IS NULL AND ({$a}publish_at IS NULL OR {$a}publish_at <= ?)";
}
