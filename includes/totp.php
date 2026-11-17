<?php
/**
 * Time-based one-time passwords (RFC 6238) for multifactor authentication.
 */
declare(strict_types=1);

function base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) {
        $pos = strpos($alphabet, $c);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_new_secret(): string
{
    return base32_encode(random_bytes(20));
}

function totp_code(string $secretB32, int $step): string
{
    $key = base32_decode($secretB32);
    $counter = pack('N*', 0) . pack('N*', $step);
    $hash = hash_hmac('sha1', $counter, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = ((ord($hash[$offset]) & 0x7F) << 24) | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8) | (ord($hash[$offset + 3]) & 0xFF);
    return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Returns the matched time step, or null. Rejects steps already used (replay). */
function totp_verify(string $secretB32, string $code, int $lastUsedStep = 0): ?int
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return null;
    }
    $now = (int) floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        $step = $now + $i;
        if ($step <= $lastUsedStep) {
            continue;
        }
        if (hash_equals(totp_code($secretB32, $step), $code)) {
            return $step;
        }
    }
    return null;
}

function totp_uri(string $secret, string $account): string
{
    $issuer = 'COOP NATCCO Partylist';
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
}

/** Recovery codes: returns [plainCodes, storedHashes]. */
function mfa_recovery_codes(int $n = 8): array
{
    $plain = [];
    $hashes = [];
    for ($i = 0; $i < $n; $i++) {
        $c = strtolower(random_code(5, 'abcdefghjkmnpqrstuvwxyz23456789') . '-' . random_code(5, 'abcdefghjkmnpqrstuvwxyz23456789'));
        $plain[] = $c;
        $hashes[] = hash('sha256', $c);
    }
    return [$plain, implode("\n", $hashes)];
}

/** Verify a TOTP or recovery code for a user; consumes recovery codes. */
function mfa_check_user_code(int $userId, string $code): bool
{
    $cred = q_one('SELECT * FROM mfa_credentials WHERE user_id = ? AND confirmed_at IS NOT NULL', [$userId]);
    if (!$cred) {
        return false;
    }
    $secret = decrypt_value($cred['secret_enc']);
    $code = trim($code);
    if ($secret && preg_match('/^\d{6}$/', preg_replace('/\s/', '', $code))) {
        $step = totp_verify($secret, $code, (int) $cred['last_used_step']);
        if ($step !== null) {
            q('UPDATE mfa_credentials SET last_used_step = ?, updated_at = ? WHERE id = ?', [$step, now(), $cred['id']]);
            return true;
        }
        return false;
    }
    $h = hash('sha256', strtolower($code));
    $codes = array_filter(explode("\n", (string) $cred['recovery_codes']));
    foreach ($codes as $i => $stored) {
        if (hash_equals($stored, $h)) {
            unset($codes[$i]);
            q('UPDATE mfa_credentials SET recovery_codes = ?, updated_at = ? WHERE id = ?', [implode("\n", $codes), now(), $cred['id']]);
            auth_event('mfa_recovery_code_used', $userId, null, count($codes) . ' codes left');
            return true;
        }
    }
    return false;
}
