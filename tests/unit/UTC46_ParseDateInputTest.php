<?php
/**
 * Test Case ID: UTC-46
 * Module: Data import
 * Unit: parse_date_input() / valid_date()
 * Role: Data Encoder importing dates
 * Description: Dates are accepted in ISO, US month/day/year and Excel serial forms; impossible dates are refused.
 * Expected: 2026-03-15, 3/15/2026 and Excel serial 46096 all give 2026-03-15; 2026-02-30 is not a valid date; blank gives null.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC46_ParseDateInputTest extends TestCase
{
public function test_formats(): void
{
    $this->assertSame('2026-03-15', parse_date_input('2026-03-15'));
    $this->assertSame('2026-03-15', parse_date_input('3/15/2026'));
    $this->assertSame('2026-03-15', parse_date_input('46096'));
    $this->assertNull(parse_date_input(''));
    $this->assertNull(parse_date_input('not a date'));
}

public function test_impossible_dates(): void
{
    $this->assertFalse(valid_date('2026-02-30'));
    $this->assertFalse(valid_date('1850-01-01'));
    $this->assertTrue(valid_date('2028-02-29'));
    $this->assertNotSame('2026-02-30', parse_date_input('2026-02-30'));
}
}
