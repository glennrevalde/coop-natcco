<?php
/**
 * Test Case ID: UTC-20
 * Module: Authentication
 * Unit: password_policy_errors()
 * Role: Any user setting a password
 * Description: Passwords built on common words or the organisation name are rejected.
 * Expected: Passwords containing "password", "P@ssw0rd" or "CoopNatcco" return a common-words error.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC20_PasswordPolicyCommonWordsTest extends TestCase
{
public function test_common_words(): void
{
    foreach (['MyPassword-2026!', 'Xx-P@ssw0rd-2026', 'Coop-Natcco-2026!'] as $pw) {
        $errors = password_policy_errors($pw);
        $found = array_filter($errors, fn($e) => strpos($e, 'Avoid common words') === 0);
        $this->assertNotEmpty($found, 'accepted ' . $pw);
    }
}
}
