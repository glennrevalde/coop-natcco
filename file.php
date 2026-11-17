<?php
/**
 * Serves uploaded files from protected storage after an access check.
 * Uploads are never executed and are sent with safe headers.
 */
require __DIR__ . '/includes/bootstrap.php';

$m = media_row(get_int('id'));
if (!$m || ($m['archived_at'] && !current_user())) {
    abort(404);
}
if (!media_can_access($m)) {
    if ($m['visibility'] === 'private') {
        audit_log('file_denied', 'media', (int) $m['id'], 'Denied download of a protected file', 'denied');
    }
    abort(current_user() ? 403 : 404);
}
$thumb = get('s') === 't';
$path = media_path($m, $thumb);
if (!is_file($path)) {
    $path = media_path($m, false);
    if (!is_file($path)) {
        abort(404);
    }
}
if ($m['visibility'] === 'private') {
    audit_log('file_download', 'media', (int) $m['id'], 'Downloaded protected file');
    no_cache_headers();
} else {
    $etag = '"' . substr($m['sha256'], 0, 32) . ($thumb ? '-t' : '') . '"';
    header('Cache-Control: public, max-age=604800');
    header('ETag: ' . $etag);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
}
$inline = strpos($m['mime_type'], 'image/') === 0 || $m['mime_type'] === 'application/pdf';
$name = preg_replace('/[^\w.\- ()]+/u', '_', $m['original_name']);
header('Content-Type: ' . $m['mime_type']);
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($m['original_name']));
header('Content-Length: ' . filesize($path));
readfile($path);
