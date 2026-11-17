<?php
/**
 * Test Case ID: UTC-26
 * Module: Authentication (MFA)
 * Unit: mfa_recovery_codes()
 * Role: Privileged user enrolling in MFA
 * Description: Recovery codes are unique, readable, and stored only as hashes.
 * Expected: Eight unique codes in the form xxxxx-xxxxx are returned with eight matching SHA-256 hashes that do not contain the codes.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC26_MfaRecoveryCodesTest extends TestCase
{
public function test_codes_and_hashes(): void
{
    [$plain, $stored] = mfa_recovery_codes(8);
    $this->assertCount(8, $plain);
    $this->assertCount(8, array_unique($plain));
    $hashes = explode("\n", $stored);
    $this->assertCount(8, $hashes);
    foreach ($plain as $i => $c) {
        $this->assertMatchesRegularExpression('/^[a-z2-9]{5}-[a-z2-9]{5}$/', $c);
        $this->assertSame(hash('sha256', $c), $hashes[$i]);
        $this->assertStringNotContainsString($c, $stored);
    }
}
}
