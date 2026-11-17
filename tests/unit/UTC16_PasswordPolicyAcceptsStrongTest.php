<?php
/**
 * Test Case ID: UTC-16
 * Module: Authentication
 * Unit: password_policy_errors()
 * Role: Any user setting a password
 * Description: A long password mixing character types is accepted.
 * Expected: No policy errors are returned for "Harbor-Lantern-2026!".
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC16_PasswordPolicyAcceptsStrongTest extends TestCase
{
public function test_strong_password_passes(): void
{
    $this->assertSame([], password_policy_errors('Harbor-Lantern-2026!', ['username' => 'mreyes', 'email' => 'mreyes@example.org']));
    $this->assertSame([], password_policy_errors('CnplDemo#2026'));
}
}
