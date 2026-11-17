<?php
/**
 * Test Case ID: UTC-25
 * Module: Authentication (MFA)
 * Unit: base32_encode() / base32_decode() / totp_new_secret()
 * Role: System (MFA enrolment)
 * Description: MFA secrets are 160-bit base32 strings that decode back to the same bytes.
 * Expected: New secrets are 32 base32 characters; random bytes survive an encode/decode round trip.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC25_Base32RoundTripTest extends TestCase
{
public function test_round_trip(): void
{
    for ($i = 0; $i < 20; $i++) {
        $bytes = random_bytes(20);
        $this->assertSame($bytes, base32_decode(base32_encode($bytes)));
    }
    $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', totp_new_secret());
    $this->assertNotSame(totp_new_secret(), totp_new_secret());
}
}
