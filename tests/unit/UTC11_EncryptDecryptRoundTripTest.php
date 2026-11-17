<?php
/**
 * Test Case ID: UTC-11
 * Module: Data protection
 * Unit: encrypt_value() / decrypt_value()
 * Role: System (protecting personal data at rest)
 * Description: Sensitive fields are encrypted with AES-256-GCM and decrypt back to the original.
 * Expected: The stored value starts with the key id, differs on every call (random IV) and decrypts to the original text; empty values stay null.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC11_EncryptDecryptRoundTripTest extends TestCase
{
public function test_round_trip(): void
{
    $plain = 'Juan Dela Cruz, 0917 000 0000';
    $a = encrypt_value($plain);
    $b = encrypt_value($plain);
    $this->assertStringStartsWith('k1:', $a);
    $this->assertNotSame($a, $b);
    $this->assertStringNotContainsString('Juan', $a);
    $this->assertSame($plain, decrypt_value($a));
    $this->assertSame($plain, decrypt_value($b));
}

public function test_empty_values(): void
{
    $this->assertNull(encrypt_value(''));
    $this->assertNull(encrypt_value(null));
    $this->assertNull(decrypt_value(null));
}
}
