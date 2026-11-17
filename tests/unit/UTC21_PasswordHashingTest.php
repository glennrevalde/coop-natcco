<?php
/**
 * Test Case ID: UTC-21
 * Module: Authentication
 * Unit: password_make()
 * Role: System (storing passwords)
 * Description: Passwords are stored as salted one-way hashes.
 * Expected: The hash is Argon2id when available (bcrypt otherwise), never contains the password, verifies with password_verify and differs each time.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC21_PasswordHashingTest extends TestCase
{
public function test_hash_properties(): void
{
    $h1 = password_make('Harbor-Lantern-2026!');
    $h2 = password_make('Harbor-Lantern-2026!');
    $this->assertMatchesRegularExpression(defined('PASSWORD_ARGON2ID') ? '/^\$argon2id\$/' : '/^\$2y\$12\$/', $h1);
    $this->assertStringNotContainsString('Harbor', $h1);
    $this->assertNotSame($h1, $h2);
    $this->assertTrue(password_verify('Harbor-Lantern-2026!', $h1));
    $this->assertFalse(password_verify('harbor-lantern-2026!', $h1));
}
}
