<?php
/**
 * Test Case ID: UTC-23
 * Module: Authentication (MFA)
 * Unit: totp_verify()
 * Role: Privileged user signing in with an authenticator app
 * Description: A current code is accepted once; replaying the same code is refused.
 * Expected: The current code returns its time step; verifying it again with that step as last-used returns null; a wrong code returns null.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC23_TotpVerifyAndReplayTest extends TestCase
{
public function test_accept_once(): void
{
    $secret = totp_new_secret();
    $step = (int) floor(time() / 30);
    $code = totp_code($secret, $step);
    $used = totp_verify($secret, $code, 0);
    $this->assertNotNull($used);
    $this->assertNull(totp_verify($secret, $code, $used));
    $window = [totp_code($secret, $step - 1), $code, totp_code($secret, $step + 1)];
    $wrong = '000000';
    while (in_array($wrong, $window, true)) {
        $wrong = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    $this->assertNull(totp_verify($secret, $wrong, 0));
}

public function test_codes_outside_the_window_fail(): void
{
    $secret = totp_new_secret();
    $old = totp_code($secret, (int) floor(time() / 30) - 5);
    $this->assertNull(totp_verify($secret, $old, 0));
}
}
