<?php
/**
 * Web app manifest: makes the system installable as an app (PWA).
 *
 * The name comes from the system settings (Admin > Settings > Identity), with a
 * default when the setting is empty or the database cannot be reached. No session
 * is opened. Any PHP notice or warning raised while reading the settings is
 * discarded, so the response is always valid JSON.
 */
declare(strict_types=1);

define('CNPL_STATELESS', true);
@ini_set('display_errors', '0');
ob_start();

const MANIFEST_DEFAULT_NAME = 'COOP NATCCO Partylist';
const MANIFEST_DEFAULT_DESCRIPTION = 'COOP NATCCO Partylist official website and National Database Management System';
const MANIFEST_DEFAULT_COLOR = '#00015e';   // sidebar color; the live value is PWA_THEME_COLOR in includes/settings.php

$GLOBALS['PWA_MANIFEST_SENT'] = false;

/** Shorten a name to fit under an app icon (about 12 characters). Used when the settings cannot be read. */
function manifest_short_name(string $name): string
{
    if (mb_strlen($name) <= 12) {
        return $name;
    }
    $out = '';
    foreach (preg_split('/\s+/', $name) as $word) {
        $try = $out === '' ? $word : $out . ' ' . $word;
        if (mb_strlen($try) > 12) {
            break;
        }
        $out = $try;
    }
    return $out !== '' ? $out : mb_substr($name, 0, 12);
}

/** Send the manifest, discarding anything printed before it. */
function manifest_send(array $s): void
{
    if ($GLOBALS['PWA_MANIFEST_SENT']) {
        return;
    }
    $GLOBALS['PWA_MANIFEST_SENT'] = true;
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $icon = function (string $file, string $size, string $purpose) use ($s): array {
        return ['src' => 'assets/icons/' . $file . '?v=' . $s['v'], 'sizes' => $size, 'type' => 'image/png', 'purpose' => $purpose];
    };
    $manifest = [
        'id' => './',
        'name' => $s['name'],
        'short_name' => $s['short_name'],
        'description' => $s['description'],
        'lang' => 'en',
        'dir' => 'ltr',
        'start_url' => './index.php',
        'scope' => './',
        'display' => 'standalone',
        'orientation' => 'any',
        'theme_color' => $s['color'],
        'background_color' => $s['color'],
        'categories' => ['government', 'productivity'],
        'icons' => [
            $icon('icon-192.png', '192x192', 'any'),
            $icon('icon-512.png', '512x512', 'any'),
            $icon('maskable-192.png', '192x192', 'maskable'),
            $icon('maskable-512.png', '512x512', 'maskable'),
        ],
    ];
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

$settings = [
    'name' => MANIFEST_DEFAULT_NAME,
    'short_name' => manifest_short_name(MANIFEST_DEFAULT_NAME),
    'description' => MANIFEST_DEFAULT_DESCRIPTION,
    'color' => MANIFEST_DEFAULT_COLOR,
    'v' => '1',
];

// If anything below stops the script early (a missing configuration, a fatal error),
// the defaults are still sent as valid JSON.
register_shutdown_function(function () use (&$settings) {
    manifest_send($settings);
});

try {
    require __DIR__ . '/includes/bootstrap.php';
    $name = trim(preg_replace('/\s+/', ' ', site_name()));
    if ($name !== '') {
        $settings['name'] = mb_substr($name, 0, 80);
        $settings['short_name'] = app_short_name();
    }
    $tagline = trim((string) setting('site_tagline', ''));
    $settings['description'] = $tagline !== '' ? $settings['name'] . ': ' . $tagline : MANIFEST_DEFAULT_DESCRIPTION;
    $settings['color'] = defined('PWA_THEME_COLOR') ? PWA_THEME_COLOR : MANIFEST_DEFAULT_COLOR;
    $settings['v'] = defined('ASSET_VERSION') ? ASSET_VERSION : '1';
} catch (Throwable $e) {
    // keep the defaults
}

manifest_send($settings);
