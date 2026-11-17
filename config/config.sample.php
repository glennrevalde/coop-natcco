<?php
/**
 * COOP NATCCO Partylist - production configuration template.
 *
 * Save the filled-in copy OUTSIDE the public web root:
 *   <parent of app folder>/coop-natcco-private/config.php
 * or set the CNPL_CONFIG environment variable to its full path.
 * Generate the keys with:  php scripts/generate-keys.php
 */
return [
    'app_env'    => 'production',
    'app_debug'  => false,
    'base_url'   => '',                                   // '' when served at https://www.coopnatccopartylist.coop/
    'app_url'    => 'https://www.coopnatccopartylist.coop',
    'timezone'   => 'Asia/Manila',
    'force_https' => true,

    'db' => [
        'host' => '127.0.0.1',        // MySQL 5.5 must listen on localhost or a private network only
        'port' => 3306,
        'name' => 'coop_natcco',
        'user' => 'cnpl_app',         // least-privilege account, see docs/INSTALLATION.md
        'pass' => 'CHANGE-ME',
    ],

    'encryption_keys' => [
        'k1' => 'base64:REPLACE-WITH-GENERATED-KEY',
    ],
    'encryption_active_key' => 'k1',
    'index_key'  => 'base64:REPLACE-WITH-GENERATED-KEY',
    'backup_key' => 'base64:REPLACE-WITH-GENERATED-KEY',

    'storage_path' => '/var/lib/coop-natcco/storage',     // outside the web root, writable by the web server user
    'trusted_proxies' => [],
];
