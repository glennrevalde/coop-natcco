<?php
/**
 * Application-layer field encryption (AES-256-GCM) and blind indexes.
 * Keys live in the configuration file outside the database.
 * Stored format:  <keyId>:<base64(iv[12] . tag[16] . ciphertext)>
 */
declare(strict_types=1);

function crypto_key(string $id): string
{
    $keys = (array) cfg('encryption_keys', []);
    if (!isset($keys[$id])) {
        throw new RuntimeException('Unknown encryption key id');
    }
    return decode_key((string) $keys[$id]);
}

function decode_key(string $k): string
{
    if (strpos($k, 'base64:') === 0) {
        $k = base64_decode(substr($k, 7), true) ?: '';
    }
    if (strlen($k) !== 32) {
        throw new RuntimeException('Encryption keys must be 32 bytes. Run scripts/generate-keys.php.');
    }
    return $k;
}

function encrypt_value(?string $plain): ?string
{
    if ($plain === null || $plain === '') {
        return null;
    }
    $id = (string) cfg('encryption_active_key', 'k1');
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', crypto_key($id), OPENSSL_RAW_DATA, $iv, $tag, $id, 16);
    if ($ct === false) {
        throw new RuntimeException('Encryption failed');
    }
    return $id . ':' . base64_encode($iv . $tag . $ct);
}

function decrypt_value(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return null;
    }
    $pos = strpos($stored, ':');
    if ($pos === false) {
        return null;
    }
    $id = substr($stored, 0, $pos);
    $raw = base64_decode(substr($stored, $pos + 1), true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    try {
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', crypto_key($id), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $id);
    } catch (Throwable $e) {
        return null;
    }
    return $plain === false ? null : $plain;
}

function blind_index(?string $value): ?string
{
    $v = mb_strtolower(trim((string) $value));
    if ($v === '') {
        return null;
    }
    return hash_hmac('sha256', $v, decode_key((string) cfg('index_key')));
}

function token_hash(string $token): string
{
    return hash('sha256', $token);
}
