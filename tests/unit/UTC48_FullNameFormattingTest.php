<?php
/**
 * Test Case ID: UTC-48
 * Module: Records
 * Unit: full_name()
 * Role: Any user viewing a person record
 * Description: Names are shown with a middle initial and suffix.
 * Expected: First, middle, last and suffix give "Juan S. Dela Cruz Jr."; missing parts leave no double spaces.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC48_FullNameFormattingTest extends TestCase
{
public function test_full_name(): void
{
    $this->assertSame('Juan S. Dela Cruz Jr.', full_name(['first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz', 'suffix' => 'Jr.']));
    $this->assertSame('Maria Reyes', full_name(['first_name' => 'Maria', 'middle_name' => '', 'last_name' => 'Reyes']));
    $this->assertSame('Ana B. Lim', full_name(['p_first_name' => 'Ana', 'p_middle_name' => 'Bautista', 'p_last_name' => 'Lim'], 'p_'));
}
}
