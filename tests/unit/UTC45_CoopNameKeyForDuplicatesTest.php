<?php
/**
 * Test Case ID: UTC-45
 * Module: Duplicate detection
 * Unit: coop_name_key()
 * Role: Data Encoder registering a cooperative
 * Description: Cooperative names are compared without generic words so near-duplicates are flagged.
 * Expected: "Bayanihan Multi-Purpose Cooperative" and "BAYANIHAN MULTI PURPOSE COOP, INC." give the same key.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC45_CoopNameKeyForDuplicatesTest extends TestCase
{
public function test_coop_key(): void
{
    $a = coop_name_key('Bayanihan Multi-Purpose Cooperative');
    $this->assertSame('bayanihan multi purpose', $a);
    $this->assertSame($a, coop_name_key('BAYANIHAN MULTI PURPOSE COOP, INC.'));
    $this->assertSame($a, coop_name_key('The Bayanihan Multi Purpose Co-op'));
    $this->assertNotSame($a, coop_name_key('Bayanihan Credit Cooperative'));
}
}
