<?php
/**
 * Test Case ID: UTC-15
 * Module: Data protection
 * Unit: encrypt_value() with encryption_active_key
 * Role: System administrator rotating keys
 * Description: After switching to a new key, values encrypted with the old key still decrypt.
 * Expected: New values carry the k2 prefix, old k1 values still decrypt, and both decrypt to their original text.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC15_KeyRotationKeepsOldDataTest extends TestCase
{
private $saved;

protected function setUp(): void
{
    $this->saved = $GLOBALS['CNPL_CONFIG']['encryption_active_key'];
}

protected function tearDown(): void
{
    $GLOBALS['CNPL_CONFIG']['encryption_active_key'] = $this->saved;
}

public function test_old_and_new_keys(): void
{
    $old = encrypt_value('old record');
    $GLOBALS['CNPL_CONFIG']['encryption_active_key'] = 'k2';
    $new = encrypt_value('new record');
    $this->assertStringStartsWith('k1:', $old);
    $this->assertStringStartsWith('k2:', $new);
    $this->assertSame('old record', decrypt_value($old));
    $this->assertSame('new record', decrypt_value($new));
}
}
