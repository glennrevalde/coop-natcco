<?php
/**
 * Test Case ID: UTC-49
 * Module: Audit trail
 * Unit: diff_changes()
 * Role: Auditor reviewing record history
 * Description: Change logs list only changed fields and hide protected values.
 * Expected: Timestamps are ignored, unchanged fields are skipped, and fields marked protected show [protected] instead of the value.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC49_DiffChangesRedactionTest extends TestCase
{
public function test_diff(): void
{
    $old = ['name' => 'Old Coop', 'phone' => '0917', 'status' => 'active', 'updated_at' => '2026-01-01'];
    $new = ['name' => 'New Coop', 'phone' => '0918', 'status' => 'active', 'updated_at' => '2026-02-01'];
    $d = diff_changes($old, $new, ['phone']);
    $this->assertSame(['from' => 'Old Coop', 'to' => 'New Coop'], $d['name']);
    $this->assertSame(['from' => '[protected]', 'to' => '[protected]'], $d['phone']);
    $this->assertArrayNotHasKey('status', $d);
    $this->assertArrayNotHasKey('updated_at', $d);
}
}
