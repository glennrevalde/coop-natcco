<?php
/**
 * Test Case ID: UTC-52
 * Module: User interface
 * Unit: status_text()
 * Role: Any user viewing a status
 * Description: Statuses are shown as plain coloured text, not capsules, and are escaped.
 * Expected: Approved renders as <span class="st st-ok">Approved</span>; unknown statuses use the muted tone; labels are escaped.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC52_StatusTextIsPlainLabelTest extends TestCase
{
public function test_status_text(): void
{
    $this->assertSame('<span class="st st-ok">Approved</span>', status_text('approved'));
    $this->assertSame('<span class="st st-bad">Rejected</span>', status_text('rejected'));
    $this->assertSame('<span class="st st-muted">Something odd</span>', status_text('something_odd'));
    $this->assertSame('<span class="st st-warn">&lt;b&gt;</span>', status_text('pending', '<b>'));
    $this->assertStringNotContainsString('badge', status_text('approved'));
}
}
