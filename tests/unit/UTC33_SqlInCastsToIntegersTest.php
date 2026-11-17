<?php
/**
 * Test Case ID: UTC-33
 * Module: Security
 * Unit: sql_in()
 * Role: System (building scoped queries)
 * Description: Lists of ids are bound as integers, so injected text cannot change the query.
 * Expected: Values are cast to integers, duplicates are removed, placeholders match the parameter count, and an empty list gives 0=1.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC33_SqlInCastsToIntegersTest extends TestCase
{
public function test_values_are_integers(): void
{
    $params = [];
    $sql = sql_in('c.id', ['5', '5', '7) OR (1=1', 9], $params);
    $this->assertSame('c.id IN (?,?,?)', $sql);
    $this->assertSame([5, 7, 9], $params);
}

public function test_empty_list(): void
{
    $params = [];
    $this->assertSame('0=1', sql_in('c.id', [], $params));
    $this->assertSame([], $params);
}
}
