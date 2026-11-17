<?php
/**
 * Test Case ID: UTC-32
 * Module: Access control
 * Unit: scope_allows_for()
 * Role: Cooperative Administrator
 * Description: A cooperative-level user may only touch records of that cooperative.
 * Expected: The assigned cooperative is allowed; another cooperative in the same city is refused; a national scope allows everything.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC32_ScopeAllowsCooperativeOnlyTest extends TestCase
{
public function test_coop_scope(): void
{
    $scope = ['national' => false, 'regions' => [], 'provinces' => [], 'cities' => [], 'coops' => [5]];
    $this->assertTrue(scope_allows_for($scope, 11, 20, 101, 5));
    $this->assertFalse(scope_allows_for($scope, 11, 20, 101, 6));
    $this->assertTrue(scope_allows_for(['national' => true], 1, null, null, 999));
}
}
