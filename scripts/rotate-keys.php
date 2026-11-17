<?php
/**
 * Re-encrypt every encrypted column with the active key (controlled key rotation).
 * 1. Add a new key id to 'encryption_keys' (keep the old one) and set 'encryption_active_key' to it.
 * 2. Take a backup.  3. Run: php scripts/rotate-keys.php
 * 4. When it reports 0 rows left on old keys, the old key may be removed from the configuration.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$active = (string) cfg('encryption_active_key');
$targets = [
    'persons' => ['email_enc', 'phone_enc'], 'members' => ['email_enc', 'phone_enc', 'address_enc'], 'event_registrations' => ['email_enc', 'phone_enc'],
    'helpdesk_tickets' => ['requester_email_enc', 'requester_phone_enc'], 'contact_messages' => ['email_enc'], 'privacy_requests' => ['requester_email_enc'], 'mfa_credentials' => ['secret_enc'],
];
$total = 0;
foreach ($targets as $table => $cols) {
    foreach ($cols as $col) {
        $rows = q_all("SELECT id, `{$col}` v FROM `{$table}` WHERE `{$col}` IS NOT NULL AND `{$col}` NOT LIKE ?", [$active . ':%']);
        foreach ($rows as $r) {
            $plain = decrypt_value($r['v']);
            if ($plain === null) {
                fwrite(STDERR, "Could not decrypt {$table}.{$col} #{$r['id']} (missing key?)\n");
                continue;
            }
            q("UPDATE `{$table}` SET `{$col}` = ? WHERE id = ?", [encrypt_value($plain), $r['id']]);
            $total++;
        }
    }
}
$smtp = setting('smtp_password_enc');
if ($smtp !== '' && strpos($smtp, $active . ':') !== 0 && ($p = decrypt_value($smtp)) !== null) {
    setting_set('smtp_password_enc', encrypt_value($p), null);
    $total++;
}
audit_log('key_rotation', 'security', null, "Re-encrypted {$total} values with key {$active}");
echo "Re-encrypted {$total} values with key '{$active}'.\n";
