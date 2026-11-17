<?php
/**
 * Test Case ID: UTC-18
 * Module: Authentication
 * Unit: password_policy_errors()
 * Role: Any user setting a password
 * Description: Passwords must mix at least three character types.
 * Expected: An all-lowercase password and a lowercase-plus-digits password each return the character-mix error.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC18_PasswordPolicyCharacterClassesTest extends TestCase
{
public function test_too_few_classes(): void
{
    $msg = 'Mix at least three of: lowercase letters, uppercase letters, numbers and symbols.';
    $this->assertContains($msg, password_policy_errors('harborlanternriver'));
    $this->assertContains($msg, password_policy_errors('harborlantern2026'));
    $this->assertNotContains($msg, password_policy_errors('Harborlantern2026'));
}
}
