<?php
/**
 * Test Case ID: UTC-30
 * Module: Access control
 * Unit: scope_sql_for()
 * Role: User with no scope assignment
 * Description: A user with no scope, or a scope the table cannot match, sees nothing.
 * Expected: The condition is 0=1 for an empty scope and for a city scope on a table that only has a region column.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC30_ScopeDenyByDefaultTest extends TestCase
{
public function test_empty_scope(): void
{
    $params = [];
    $this->assertSame('0=1', scope_sql_for(['national' => false, 'regions' => [], 'provinces' => [], 'cities' => [], 'coops' => []], ['region' => 'x.region_id'], $params));
    $this->assertSame('0=1', scope_sql_for([], ['region' => 'x.region_id'], $params));
    $this->assertSame('0=1', scope_sql_for(['national' => false, 'cities' => [7]], ['region' => 'x.region_id'], $params));
    $this->assertSame([], $params);
}
}
