<?php
/**
 * Test Case ID: UTC-47
 * Module: Data protection
 * Unit: mask_email() / mask_phone()
 * Role: User without permission to see contact details
 * Description: Contact details are masked for users who may not see them in full.
 * Expected: maria.santos@example.org becomes m••••••@example.org; 0917 123 4567 shows only the last three digits.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC47_MaskingHelpersTest extends TestCase
{
public function test_masks(): void
{
    $this->assertSame('m••••••@example.org', mask_email('maria.santos@example.org'));
    $this->assertSame('a••@example.org', mask_email('ab@example.org'));
    $this->assertSame('••••••••567', mask_phone('0917 123 4567'));
    $this->assertSame('', mask_phone(null));
    $this->assertSame('•••', mask_email('not-an-email'));
}
}
