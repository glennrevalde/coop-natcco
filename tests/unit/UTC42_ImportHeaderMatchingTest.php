<?php
/**
 * Test Case ID: UTC-42
 * Module: Data import
 * Unit: import_norm()
 * Role: Data Encoder mapping import columns
 * Description: Column headings are matched regardless of case, spaces and punctuation.
 * Expected: "CDA Registration No." and "cda_registration_no" normalise to the same key.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC42_ImportHeaderMatchingTest extends TestCase
{
public function test_normalisation(): void
{
    $this->assertSame('cdaregistrationno', import_norm('CDA Registration No.'));
    $this->assertSame(import_norm('CDA Registration No.'), import_norm('cda_registration_no'));
    $this->assertSame('dateregistered', import_norm(' Date  Registered '));
}
}
