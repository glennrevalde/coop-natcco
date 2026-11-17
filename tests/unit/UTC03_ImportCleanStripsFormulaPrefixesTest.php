<?php
/**
 * Test Case ID: UTC-03
 * Module: Data import
 * Unit: import_clean()
 * Role: Data Encoder importing a spreadsheet
 * Description: Imported cells are stripped of formula prefixes and control characters.
 * Expected: Leading =, +, @ and non-numeric - are removed, control characters are removed, and negative numbers are kept.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC03_ImportCleanStripsFormulaPrefixesTest extends TestCase
{
public function test_formula_prefixes_are_removed(): void
{
    $this->assertSame('SUM(A1:A3)', import_clean('=SUM(A1:A3)'));
    $this->assertSame('cmd', import_clean('+=@cmd'));
    $this->assertSame('Juan', import_clean('-Juan'));
}

public function test_control_characters_and_spaces_are_removed(): void
{
    $this->assertSame('Dela Cruz', import_clean("  Dela\x00 Cruz\x07 "));
    $this->assertSame('Dela Cruz', import_clean("Dela\x0B Cruz"));
}

public function test_numbers_survive(): void
{
    $this->assertSame('-15', import_clean('-15'));
    $this->assertSame('2026', import_clean(2026));
    $this->assertSame('', import_clean(null));
}
}
