<?php
/**
 * Test Case ID: UTC-17
 * Module: Authentication
 * Unit: password_policy_errors()
 * Role: Any user setting a password
 * Description: Passwords shorter than the minimum length or longer than 128 characters are rejected.
 * Expected: An 8-character password returns the "at least 12 characters" error; a 129-character password returns the maximum-length error.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC17_PasswordPolicyLengthTest extends TestCase
{
public function test_short_password(): void
{
    $errors = password_policy_errors('Ab1!efgh');
    $this->assertContains('Use at least 12 characters.', $errors);
}

public function test_very_long_password(): void
{
    $errors = password_policy_errors(str_repeat('Ab1!', 32) . 'x');
    $this->assertContains('Use at most 128 characters.', $errors);
}
}
