<?php
/**
 * Test Case ID: UTC-53
 * Module: User interface
 * Unit: url()
 * Role: Any user following a link
 * Description: Links are built under the configured base path and skip empty parameters.
 * Expected: url("admin/x.php", ["id" => 5, "q" => ""]) gives /coop-natcco/admin/x.php?id=5.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC53_UrlBuildingTest extends TestCase
{
public function test_url(): void
{
    $this->assertSame('/coop-natcco/admin/x.php?id=5', url('admin/x.php', ['id' => 5, 'q' => '', 'z' => null]));
    $this->assertSame('/coop-natcco/', url());
    $this->assertSame('/coop-natcco/search.php?q=a%26b', url('search.php', ['q' => 'a&b']));
}
}
