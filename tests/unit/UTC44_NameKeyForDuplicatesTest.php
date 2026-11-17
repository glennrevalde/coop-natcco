<?php
/**
 * Test Case ID: UTC-44
 * Module: Duplicate detection
 * Unit: name_key()
 * Role: Data Encoder adding a person
 * Description: Person names are normalised so the same person is detected as a possible duplicate.
 * Expected: Case, punctuation and extra spaces do not change the key; the key orders last|first|middle.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC44_NameKeyForDuplicatesTest extends TestCase
{
public function test_name_key(): void
{
    $this->assertSame('dela cruz|juan|santos', name_key('Juan', 'Dela Cruz', 'Santos'));
    $this->assertSame(name_key('Juan', 'Dela Cruz', 'Santos'), name_key(' JUAN ', 'dela-cruz', 'santos.'));
    $this->assertNotSame(name_key('Juan', 'Dela Cruz'), name_key('Juana', 'Dela Cruz'));
}
}
