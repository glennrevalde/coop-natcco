<?php
/**
 * Test Case ID: UTC-01
 * Module: Security
 * Unit: csv_safe()
 * Role: Any user who exports data
 * Description: Exported cells that start with a spreadsheet formula character are neutralised.
 * Expected: Values starting with =, @, a tab, or a non-numeric + or - are prefixed with an apostrophe.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC01_CsvSafeNeutralisesFormulasTest extends TestCase
{
public function test_formula_cells_get_an_apostrophe(): void
{
    // Step 1: values an attacker could type into a name or address field
    $cases = ['=HYPERLINK("http://x","click")', '@SUM(A1:A9)', '+cmd|" /C calc"!A0', '-2+3+cmd|" /C calc"!A0', "\t=1+1"];
    // Step 2: each must come back with a leading apostrophe so spreadsheets show it as text
    foreach ($cases as $v) {
        $this->assertSame("'" . $v, csv_safe($v));
    }
}

public function test_ordinary_values_are_unchanged(): void
{
    $this->assertSame('Bayanihan Multi-Purpose Cooperative', csv_safe('Bayanihan Multi-Purpose Cooperative'));
    $this->assertSame('', csv_safe(null));
    $this->assertSame('1250', csv_safe(1250));
}
}
