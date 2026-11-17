<?php
/**
 * Test Case ID: UTC-40
 * Module: Data import
 * Unit: import_read_file()
 * Role: Data Encoder importing cooperatives
 * Description: CSV files with a UTF-8 byte-order mark, semicolons and blank lines are read correctly.
 * Expected: Headers are returned without the BOM, semicolon-delimited cells are split, and blank rows are dropped.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC40_ImportReadsCsvTest extends TestCase
{
public function test_csv_with_bom_and_semicolons(): void
{
    $path = test_temp_file("\xEF\xBB\xBFCooperative name;Region;Members\nBayanihan MPC;Region VII;120\n\n;;\nPag-asa Credit;NCR;45\n");
    [$headers, $rows] = import_read_file($path, 'csv');
    $this->assertSame(['Cooperative name', 'Region', 'Members'], $headers);
    $this->assertCount(2, $rows);
    $this->assertSame(['Pag-asa Credit', 'NCR', '45'], $rows[1]);
}

public function test_empty_file(): void
{
    $this->expectException(ValidationError::class);
    $this->expectExceptionMessage('no rows');
    import_read_file(test_temp_file(''), 'csv');
}
}
