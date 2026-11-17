<?php
/**
 * Test Case ID: UTC-02
 * Module: Security
 * Unit: csv_safe()
 * Role: Any user who exports data
 * Description: Signed numbers in exports stay numeric.
 * Expected: Numeric values such as -12.50 and +63 stay exactly as they are; only non-numeric text with a leading sign is prefixed.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC02_CsvSafeKeepsSignedNumbersTest extends TestCase
{
public function test_signed_numbers_are_not_prefixed(): void
{
    $this->assertSame('-12.50', csv_safe('-12.50'));
    $this->assertSame('+63', csv_safe('+63'));
    $this->assertSame('-4', csv_safe(-4));
}

public function test_signed_text_is_prefixed(): void
{
    $this->assertSame("'+63 912 000 0000", csv_safe('+63 912 000 0000'));
    $this->assertSame("'-HELLO", csv_safe('-HELLO'));
}
}
