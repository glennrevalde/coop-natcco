<?php
/**
 * Test Case ID: UTC-27
 * Module: Authentication
 * Unit: token_hash() / random_code()
 * Role: User resetting a password or tracking a ticket
 * Description: Reset tokens are stored as SHA-256 hashes and random codes use an unambiguous alphabet.
 * Expected: token_hash() equals sha256; random_code() returns the requested length using only the allowed characters and varies between calls.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC27_TokensAndRandomCodesTest extends TestCase
{
public function test_token_hash(): void
{
    $this->assertSame(hash('sha256', 'abc'), token_hash('abc'));
}

public function test_random_code(): void
{
    $seen = [];
    for ($i = 0; $i < 50; $i++) {
        $c = random_code(10);
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{10}$/', $c);
        $seen[$c] = true;
    }
    $this->assertGreaterThan(45, count($seen));
}
}
