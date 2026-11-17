<?php
/**
 * Test Case ID: UTC-14
 * Module: Data protection
 * Unit: decode_key()
 * Role: System administrator installing keys
 * Description: Encryption keys must be exactly 32 bytes.
 * Expected: A 32-byte base64 key decodes; a short key throws a RuntimeException that points to scripts/generate-keys.php.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC14_DecodeKeyValidationTest extends TestCase
{
public function test_valid_key_decodes(): void
{
    $this->assertSame(32, strlen(decode_key('base64:' . base64_encode(str_repeat('a', 32)))));
}

public function test_short_key_is_rejected(): void
{
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('generate-keys.php');
    decode_key('base64:' . base64_encode('too-short'));
}
}
