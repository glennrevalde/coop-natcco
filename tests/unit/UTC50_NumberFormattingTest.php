<?php
/**
 * Test Case ID: UTC-50
 * Module: Reports
 * Unit: fmt_pct() / fmt_num() / human_size()
 * Role: Any user reading a report
 * Description: Report figures format safely, including division by zero.
 * Expected: fmt_pct(1, 0) is empty; 25 of 200 is 12.50%; 1234567 shows as 1,234,567; 1536 bytes shows as 2 KB and 2 MiB as 2.0 MB.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC50_NumberFormattingTest extends TestCase
{
public function test_formatting(): void
{
    $this->assertSame('', fmt_pct(1, 0));
    $this->assertSame('12.50%', fmt_pct(25, 200));
    $this->assertSame('1,234,567', fmt_num(1234567));
    $this->assertSame('', fmt_num(null));
    $this->assertSame('2 KB', human_size(1536));
    $this->assertSame('1 KB', human_size(10));
    $this->assertSame('2.0 MB', human_size(2 * 1024 * 1024));
}
}
