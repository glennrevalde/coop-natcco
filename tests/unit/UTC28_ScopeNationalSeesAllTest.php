<?php
/**
 * Test Case ID: UTC-28
 * Module: Access control
 * Unit: scope_sql_for()
 * Role: National Administrator
 * Description: A national-scope user gets an unrestricted condition.
 * Expected: The condition is 1=1 and no parameters are added.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC28_ScopeNationalSeesAllTest extends TestCase
{
public function test_national(): void
{
    $params = [];
    $sql = scope_sql_for(['national' => true, 'regions' => [], 'provinces' => [], 'cities' => [], 'coops' => []], ['region' => 'c.region_id'], $params);
    $this->assertSame('1=1', $sql);
    $this->assertSame([], $params);
}
}
