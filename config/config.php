<?php
/**
 * COOP NATCCO Partylist - application configuration (LOCAL XAMPP COPY)
 *
 * This copy is set up for local development at http://localhost/coop-natcco
 * and matches the keys used to generate the sample-data SQL file.
 *
 * For production:
 *   1. Copy config.sample.php to a folder OUTSIDE the web root, for example
 *      /var/www/coop-natcco-private/config.php (it is found automatically when it
 *      sits in a folder named "coop-natcco-private" next to the app folder), or
 *      point the CNPL_CONFIG environment variable at it.
 *   2. Generate new keys:  php scripts/generate-keys.php
 *   3. Set app_env to 'production', force_https to true and use a dedicated,
 *      least-privilege database account (never root).
 *   4. Import database/coop_natcco_clean.sql (never the sample data).
 */
return [
    'app_env'    => 'local',               // 'local' or 'production'
    'app_debug'  => false,                 // never true on a public server
    'base_url'   => '/coop-natcco',        // URL path of the app; '' when served at the domain root
    'app_url'    => 'http://localhost/coop-natcco', // absolute URL used in emails
    'timezone'   => 'Asia/Manila',
    'force_https' => false,

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'coop_natcco',
        // Local XAMPP default. The app refuses to run as root when app_env is 'production'.
        'user' => 'root',
        'pass' => 'Jeglen',
    ],

    // AES-256-GCM keys for field-level encryption. Add a new key id and switch
    // 'encryption_active_key' to rotate; then run scripts/rotate-keys.php.
    'encryption_keys' => [
        'k1' => 'base64:2TUvPD3Sm5jK4OPFVcp0eqe2bFJMH4L7aGvLW2z4h8k=',
    ],
    'encryption_active_key' => 'k1',
    'index_key'  => 'base64:T1ciNOq3tOqTuiaDQwZA4NXCHN1gG/Dm4mxTz1mqRGQ=',   // HMAC key for searchable blind indexes
    'backup_key' => 'base64:rRM4uOrFCo2mfInIAGENAO0Sxxg/ZP+fZ36kR3iLKm0=',   // encrypts database backups

    // Leave empty to use the storage folder inside the app (protected by .htaccess).
    // In production point this to a folder outside the web root.
    'storage_path' => '',

    // IP addresses of reverse proxies whose X-Forwarded-For header may be trusted.
    'trusted_proxies' => [],
];
