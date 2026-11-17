<?php
/**
 * Secure uploads: extension allowlist, real MIME and signature checks, size
 * limits, randomized names, storage outside the public folder, and image
 * re-encoding (strips metadata and any embedded payloads).
 */
declare(strict_types=1);

const MEDIA_KINDS = [
    'image' => ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'],
    'document' => ['pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'import' => ['csv' => 'text/csv', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
];

function media_allowed(string $kind): array
{
    if ($kind === 'attachment') {
        return MEDIA_KINDS['image'] + MEDIA_KINDS['document'];
    }
    return MEDIA_KINDS[$kind] ?? [];
}

function media_accept_attr(string $kind): string
{
    return implode(',', array_map(fn($e) => '.' . $e, array_keys(media_allowed($kind))));
}

/** Has a file been submitted in this field? */
function upload_present(string $field): bool
{
    return isset($_FILES[$field]) && is_array($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Validate and store an uploaded file. Returns the media_assets id.
 * Throws ValidationError with a user-friendly message on failure.
 */
function media_upload(array $file, string $kind, string $visibility = 'private', ?string $title = null): int
{
    $info = media_validate_upload($file, $kind);
    return media_store($info, $visibility, $title);
}

function media_validate_upload(array $file, string $kind): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new ValidationError('Invalid upload.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = [UPLOAD_ERR_INI_SIZE => 'The file is larger than the server allows.', UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Try again.', UPLOAD_ERR_NO_FILE => 'No file was selected.'][$file['error']] ?? 'The upload failed.';
        throw new ValidationError($msg);
    }
    if (!is_uploaded_file($file['tmp_name']) && !defined('CNPL_TESTING')) {
        throw new ValidationError('Invalid upload.');
    }
    $maxMb = max(1, setting_int('upload_max_mb', 10));
    if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxMb * 1024 * 1024) {
        throw new ValidationError("Files must be smaller than {$maxMb} MB.");
    }
    $original = basename(str_replace('\\', '/', (string) $file['name']));
    $original = preg_replace('/[^\w.\- ()]+/u', '_', $original);
    $parts = explode('.', strtolower($original));
    $ext = count($parts) > 1 ? end($parts) : '';
    // Reject double extensions that hide executable types (e.g. report.php.pdf)
    $dangerous = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'phps', 'pht', 'cgi', 'pl', 'py', 'sh', 'exe', 'bat', 'cmd', 'js', 'html', 'htm', 'svg', 'shtml', 'htaccess', 'asp', 'aspx', 'jsp'];
    foreach (array_slice($parts, 1) as $p) {
        if (in_array($p, $dangerous, true)) {
            security_event('upload_rejected', 'medium', 'Rejected upload with dangerous extension: ' . mb_substr($original, 0, 120));
            throw new ValidationError('This file type is not allowed.');
        }
    }
    $allowed = media_allowed($kind);
    if (!isset($allowed[$ext])) {
        throw new ValidationError('Allowed file types: ' . strtoupper(implode(', ', array_keys($allowed))) . '.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 16);
    $ok = false;
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $ok = $mime === 'image/jpeg' && strncmp($head, "\xFF\xD8\xFF", 3) === 0;
            break;
        case 'png':
            $ok = $mime === 'image/png' && strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0;
            break;
        case 'gif':
            $ok = $mime === 'image/gif' && (strncmp($head, 'GIF87a', 6) === 0 || strncmp($head, 'GIF89a', 6) === 0);
            break;
        case 'webp':
            $ok = $mime === 'image/webp' && strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP';
            break;
        case 'pdf':
            $ok = $mime === 'application/pdf' && strncmp($head, '%PDF-', 5) === 0;
            break;
        case 'docx':
        case 'xlsx':
        case 'pptx':
            $ok = strncmp($head, "PK\x03\x04", 4) === 0 && in_array($mime, [$allowed[$ext], 'application/zip', 'application/octet-stream'], true);
            break;
        case 'csv':
            $ok = in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'], true) && strpos($head, "\0") === false;
            break;
    }
    if (!$ok) {
        security_event('upload_rejected', 'low', "Rejected upload: content does not match .{$ext} ({$mime})");
        throw new ValidationError('The file content does not match its type.');
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) && @getimagesize($file['tmp_name']) === false) {
        throw new ValidationError('The image could not be read.');
    }
    return ['tmp' => $file['tmp_name'], 'original' => mb_substr($original, 0, 191), 'ext' => $ext === 'jpeg' ? 'jpg' : $ext, 'mime' => $allowed[$ext]];
}

function media_store(array $info, string $visibility, ?string $title): int
{
    $dir = 'uploads/' . date('Y/m');
    $abs = STORAGE_PATH . '/' . $dir;
    if (!is_dir($abs) && !mkdir($abs, 0750, true) && !is_dir($abs)) {
        throw new RuntimeException('Upload folder is not writable');
    }
    $name = bin2hex(random_bytes(16));
    $ext = $info['ext'];
    $width = $height = null;
    $thumb = 0;
    $target = $abs . '/' . $name . '.' . $ext;

    if (in_array($ext, ['jpg', 'png', 'gif', 'webp'], true) && function_exists('imagecreatetruecolor')) {
        [$img, $w, $h] = image_load($info['tmp'], $ext);
        if (!$img) {
            throw new ValidationError('The image could not be processed.');
        }
        if ($ext === 'gif') {
            $ext = 'png';
            $info['mime'] = 'image/png';
            $target = $abs . '/' . $name . '.png';
        }
        [$out, $width, $height] = image_resize($img, $w, $h, 1920);
        image_save($out, $target, $ext);
        [$t] = image_resize($img, $w, $h, 480);
        image_save($t, $abs . '/' . $name . '_t.' . $ext, $ext);
        $thumb = 1;
    } else {
        if (!move_uploaded_file($info['tmp'], $target)) {
            if (!(defined('CNPL_TESTING') && copy($info['tmp'], $target))) {
                throw new RuntimeException('Could not store the upload');
            }
        }
        if (in_array($ext, ['jpg', 'png', 'gif', 'webp'], true)) {
            $sz = @getimagesize($target);
            $width = $sz[0] ?? null;
            $height = $sz[1] ?? null;
        }
    }
    @chmod($target, 0640);
    return q_insert('media_assets', [
        'original_name' => $info['original'], 'stored_name' => $name . '.' . $ext, 'stored_path' => $dir,
        'mime_type' => $info['mime'], 'extension' => $ext, 'file_size' => (int) filesize($target), 'sha256' => hash_file('sha256', $target),
        'width' => $width, 'height' => $height, 'has_thumb' => $thumb, 'visibility' => $visibility === 'public' ? 'public' : 'private',
        'title' => $title ? mb_substr($title, 0, 191) : null, 'alt_text' => $title ? mb_substr($title, 0, 255) : null,
        'uploaded_by' => uid(), 'created_at' => now(),
    ]);
}

function image_load(string $path, string $ext): array
{
    switch ($ext) {
        case 'jpg':
            $img = @imagecreatefromjpeg($path);
            if ($img && function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                $o = (int) ($exif['Orientation'] ?? 1);
                if ($o === 3) {
                    $img = imagerotate($img, 180, 0);
                } elseif ($o === 6) {
                    $img = imagerotate($img, -90, 0);
                } elseif ($o === 8) {
                    $img = imagerotate($img, 90, 0);
                }
            }
            break;
        case 'png':
            $img = @imagecreatefrompng($path);
            break;
        case 'gif':
            $img = @imagecreatefromgif($path);
            break;
        case 'webp':
            $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
            break;
        default:
            $img = false;
    }
    if (!$img) {
        return [null, 0, 0];
    }
    return [$img, imagesx($img), imagesy($img)];
}

function image_resize($img, int $w, int $h, int $max): array
{
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return [$out, $nw, $nh];
}

function image_save($img, string $path, string $ext): void
{
    switch ($ext) {
        case 'png':
            imagepng($img, $path, 6);
            break;
        case 'webp':
            function_exists('imagewebp') ? imagewebp($img, $path, 82) : imagepng($img, $path);
            break;
        default:
            $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            imagejpeg($bg, $path, 84);
    }
}

function media_url(?int $id, bool $thumb = false): string
{
    if (!$id) {
        return '';
    }
    return url('file.php', ['id' => $id, 's' => $thumb ? 't' : null]);
}

function media_row(?int $id): ?array
{
    return $id ? q_one('SELECT * FROM media_assets WHERE id = ?', [$id]) : null;
}

function media_path(array $m, bool $thumb = false): string
{
    $name = $m['stored_name'];
    if ($thumb && (int) $m['has_thumb'] === 1) {
        $name = preg_replace('/\.(\w+)$/', '_t.$1', $name);
    }
    return STORAGE_PATH . '/' . $m['stored_path'] . '/' . $name;
}

function human_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}

/**
 * Decide whether the current visitor may download a private file.
 */
function media_can_access(array $m): bool
{
    if ($m['visibility'] === 'public' && !$m['archived_at']) {
        return true;
    }
    $mid = (int) $m['id'];
    // Help desk requesters who proved ownership of a ticket in this session
    $tracked = $_SESSION['tracked_tickets'] ?? [];
    if ($tracked) {
        $p = [$mid];
        $ok = q_val('SELECT COUNT(*) FROM ticket_attachments WHERE media_id = ? AND is_internal = 0 AND ' . sql_in('ticket_id', $tracked, $p), $p);
        if ($ok) {
            return true;
        }
    }
    $u = current_user();
    if (!$u) {
        return false;
    }
    if ((int) $m['uploaded_by'] === (int) $u['id']) {
        return true;
    }
    // Help desk attachments
    $t = q_one('SELECT t.* FROM ticket_attachments a JOIN helpdesk_tickets t ON t.id = a.ticket_id WHERE a.media_id = ? LIMIT 1', [$mid]);
    if ($t) {
        require_once APP_ROOT . '/includes/helpdesk.php';
        return ticket_can_view($t);
    }
    // Record attachments: module view permission + record within scope
    $ra = q_one('SELECT * FROM record_attachments WHERE media_id = ? AND archived_at IS NULL LIMIT 1', [$mid]);
    if ($ra) {
        require_once APP_ROOT . '/includes/modules.php';
        return module_record_accessible($ra['module'], (int) $ra['record_id']);
    }
    // Media linked to election sources or appointments
    if (q_val('SELECT COUNT(*) FROM election_sources WHERE document_media_id = ?', [$mid])) {
        return can('elections.view');
    }
    if (q_val('SELECT COUNT(*) FROM officer_appointments WHERE document_media_id = ?', [$mid])) {
        return can('officers.view');
    }
    // Content drafts (private media referenced by unpublished content)
    if (can('content.view') || can('media.view')) {
        return true;
    }
    return is_super();
}
