<?php
/**
 * Test Case ID: UTC-12
 * Module: Data protection
 * Unit: decrypt_value()
 * Role: System (protecting personal data at rest)
 * Description: Altered or malformed ciphertext is rejected instead of returning garbage.
 * Expected: Flipping one byte, truncating, using an unknown key id or a value without a key id returns null.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC12_DecryptRejectsTamperingTest extends TestCase
{
public function test_tampered_values_return_null(): void
{
    $stored = encrypt_value('0917-555-0101');
    $raw = base64_decode(substr($stored, 3));
    $flipped = $raw;
    $flipped[strlen($flipped) - 1] = chr(ord($flipped[strlen($flipped) - 1]) ^ 1);
    $this->assertNull(decrypt_value('k1:' . base64_encode($flipped)));
    $this->assertNull(decrypt_value('k1:' . base64_encode(substr($raw, 0, 20))));
    $this->assertNull(decrypt_value('k9:' . substr($stored, 3)));
    $this->assertNull(decrypt_value('no-key-id'));
}
}
