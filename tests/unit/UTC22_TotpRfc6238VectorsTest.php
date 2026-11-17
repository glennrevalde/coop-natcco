<?php
/**
 * Test Case ID: UTC-22
 * Module: Authentication (MFA)
 * Unit: totp_code()
 * Role: Privileged user signing in with an authenticator app
 * Description: TOTP codes match the RFC 6238 reference values.
 * Expected: For the RFC secret, steps for T=59, 1111111109 and 1234567890 give 287082, 081804 and 005924.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC22_TotpRfc6238VectorsTest extends TestCase
{
public function test_reference_values(): void
{
    $secret = base32_encode('12345678901234567890');
    $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
    $this->assertSame('287082', totp_code($secret, intdiv(59, 30)));
    $this->assertSame('081804', totp_code($secret, intdiv(1111111109, 30)));
    $this->assertSame('005924', totp_code($secret, intdiv(1234567890, 30)));
}
}
