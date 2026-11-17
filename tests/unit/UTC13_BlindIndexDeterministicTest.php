<?php
/**
 * Test Case ID: UTC-13
 * Module: Data protection
 * Unit: blind_index()
 * Role: System (searching encrypted fields)
 * Description: Blind indexes allow exact-match search on encrypted values without storing the value.
 * Expected: The same value gives the same 64-character HMAC regardless of case and surrounding spaces; different values differ; empty gives null.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC13_BlindIndexDeterministicTest extends TestCase
{
public function test_index_is_stable_and_normalised(): void
{
    $a = blind_index('Maria.Santos@Example.org');
    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a);
    $this->assertSame($a, blind_index('  maria.santos@example.org '));
    $this->assertNotSame($a, blind_index('maria.santos2@example.org'));
    $this->assertStringNotContainsString('maria', $a);
    $this->assertNull(blind_index('   '));
}
}
