<?php
/**
 * Test Case ID: UTC-19
 * Module: Authentication
 * Unit: password_policy_errors()
 * Role: Any user setting a password
 * Description: Passwords may not contain the username or the email name.
 * Expected: A password containing the username, or the part of the email before @, returns the personal-information error.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC19_PasswordPolicyPersonalInfoTest extends TestCase
{
public function test_username_and_email_are_rejected(): void
{
    $msg = 'Do not include your username or email address.';
    $user = ['username' => 'lsantos', 'email' => 'louie.santos@example.org'];
    $this->assertContains($msg, password_policy_errors('My-LSANTOS-pass-99', $user));
    $this->assertContains($msg, password_policy_errors('Louie.Santos#2026x', $user));
    $this->assertNotContains($msg, password_policy_errors('Harbor-Lantern-2026!', $user));
}
}
