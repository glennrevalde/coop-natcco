<?php
/**
 * Test Case ID: UTC-24
 * Module: Authentication (MFA)
 * Unit: totp_verify()
 * Role: Privileged user signing in with an authenticator app
 * Description: Codes that are not six digits are refused without checking the secret.
 * Expected: Empty, five-digit, seven-digit and alphabetic codes return null; spaces inside a valid code are ignored.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC24_TotpRejectsMalformedTest extends TestCase
{
public function test_malformed_codes(): void
{
    $secret = totp_new_secret();
    foreach (['', '12345', '1234567', 'abcdef'] as $c) {
        $this->assertNull(totp_verify($secret, $c, 0));
    }
    $code = totp_code($secret, (int) floor(time() / 30));
    $this->assertNotNull(totp_verify($secret, substr($code, 0, 3) . ' ' . substr($code, 3), 0));
}
}
