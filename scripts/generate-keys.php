<?php
/**
 * Print new random keys for the configuration file.
 * Generate keys BEFORE loading any personal data; data encrypted with one key
 * cannot be read with another (use scripts/rotate-keys.php to change keys later).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
echo "Paste these values into your configuration file:\n\n";
echo "'encryption_keys' => ['k1' => 'base64:" . base64_encode(random_bytes(32)) . "'],\n";
echo "'encryption_active_key' => 'k1',\n";
echo "'index_key'  => 'base64:" . base64_encode(random_bytes(32)) . "',\n";
echo "'backup_key' => 'base64:" . base64_encode(random_bytes(32)) . "',\n\n";
echo "Store a copy of the keys in a secure offline location. Losing them makes encrypted data and backups unreadable.\n";
