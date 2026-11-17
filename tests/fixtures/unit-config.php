<?php
/**
 * Configuration used ONLY by the unit tests (tests/bootstrap.php).
 * The keys below are test keys and are never used by the application.
 * The database points at a closed port so unit tests cannot touch real data.
 */
$storage = sys_get_temp_dir() . '/cnpl-unit-storage';
foreach (['', '/logs', '/uploads', '/cache'] as $d) {
    if (!is_dir($storage . $d)) {
        @mkdir($storage . $d, 0700, true);
    }
}
return [
    'app_env'    => 'testing',
    'app_debug'  => false,
    'base_url'   => '/coop-natcco',
    'app_url'    => 'http://localhost/coop-natcco',
    'timezone'   => 'Asia/Manila',
    'force_https' => false,
    'db' => ['host' => '127.0.0.1', 'port' => 1, 'name' => 'unit_tests_no_database', 'user' => 'none', 'pass' => ''],
    'encryption_keys' => [
        'k1' => 'base64:yXadAFZon9AIZ9z+LCdekqiFGi7v8o97KdZlLvh0gyk=',
        'k2' => 'base64:OGZQEEcdxYsqndIiToLdQ37SLjhLKDlog+cL98aIEuI=',
    ],
    'encryption_active_key' => 'k1',
    'index_key'  => 'base64:fxRaPh/VZTP4bojmqTHoL3Gh7xEOcRalJ8QUxLp9S4E=',
    'backup_key' => 'base64:7kVgOU9zc2ldWz92car+BSYFThAFf3ezd/TAbm4lapM=',
    'storage_path' => $storage,
    'trusted_proxies' => [],
];
