<?php
/**
 * Test Case ID: UTC-31
 * Module: Access control
 * Unit: scope_allows_for()
 * Role: City/Municipal Administrator
 * Description: A city administrator may only touch records in that city.
 * Expected: A record in the assigned city is allowed; another city in the same province and region is refused.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC31_ScopeAllowsCityOnlyTest extends TestCase
{
public function test_city_scope(): void
{
    $scope = ['national' => false, 'regions' => [], 'provinces' => [], 'cities' => [101], 'coops' => []];
    $this->assertTrue(scope_allows_for($scope, 11, 20, 101));
    $this->assertFalse(scope_allows_for($scope, 11, 20, 102));
    $this->assertFalse(scope_allows_for($scope, 11, null, null));
}
}
