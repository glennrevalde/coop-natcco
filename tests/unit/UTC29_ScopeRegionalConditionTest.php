<?php
/**
 * Test Case ID: UTC-29
 * Module: Access control
 * Unit: scope_sql_for()
 * Role: Regional Administrator
 * Description: A regional user is limited to rows in the assigned region, using bound parameters.
 * Expected: The condition is (c.region_id IN (?)) with the region id as the only parameter.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC29_ScopeRegionalConditionTest extends TestCase
{
public function test_region(): void
{
    $params = [];
    $scope = ['national' => false, 'regions' => [11], 'provinces' => [], 'cities' => [], 'coops' => []];
    $sql = scope_sql_for($scope, ['region' => 'c.region_id', 'province' => 'c.province_id', 'city' => 'c.city_id', 'coop' => 'c.id'], $params);
    $this->assertSame('(c.region_id IN (?))', $sql);
    $this->assertSame([11], $params);
}

public function test_combined_scope(): void
{
    $params = [];
    $scope = ['national' => false, 'regions' => [], 'provinces' => [20], 'cities' => [], 'coops' => [5, 9]];
    $sql = scope_sql_for($scope, ['region' => 'c.region_id', 'province' => 'c.province_id', 'coop' => 'c.id'], $params);
    $this->assertSame('(c.province_id IN (?) OR c.id IN (?,?))', $sql);
    $this->assertSame([20, 5, 9], $params);
}
}
