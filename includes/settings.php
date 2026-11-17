<?php
/**
 * Website settings stored in website_settings.
 */
declare(strict_types=1);

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        try {
            $cache = q_pairs('SELECT setting_key, setting_value FROM website_settings');
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    $v = $all[$key] ?? null;
    return ($v === null || $v === '') ? $default : (string) $v;
}

function setting_int(string $key, int $default): int
{
    $v = setting($key, (string) $default);
    return ctype_digit($v) ? (int) $v : $default;
}

function setting_set(string $key, ?string $value, ?int $userId): void
{
    $exists = q_val('SELECT COUNT(*) FROM website_settings WHERE setting_key = ?', [$key]);
    if ($exists) {
        q('UPDATE website_settings SET setting_value = ?, updated_by = ?, updated_at = ? WHERE setting_key = ?', [$value, $userId, now(), $key]);
    } else {
        q_insert('website_settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'general', 'updated_by' => $userId, 'updated_at' => now()]);
    }
    settings_all(true);
}

function site_name(): string
{
    return setting('site_name', 'COOP NATCCO Partylist');
}

function logo_url(): string
{
    $id = setting_int('logo_media_id', 0);
    return $id ? url('file.php', ['id' => $id]) : asset('img/logo.jpg');
}
