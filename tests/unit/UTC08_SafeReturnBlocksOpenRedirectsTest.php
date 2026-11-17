<?php
/**
 * Test Case ID: UTC-08
 * Module: Security
 * Unit: safe_return()
 * Role: Any signed-in user following a return link
 * Description: Return addresses after login or form submission must stay inside the application.
 * Expected: External, protocol-relative and back-slash addresses fall back to the given page; paths under /coop-natcco/ are allowed.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC08_SafeReturnBlocksOpenRedirectsTest extends TestCase
{
public function test_external_targets_fall_back(): void
{
    foreach (['https://evil.example/', '//evil.example/x', '/\\evil.example', 'javascript:alert(1)', '/other-app/x', ''] as $to) {
        $this->assertSame('/coop-natcco/admin/index.php', safe_return($to, 'admin/index.php'), 'accepted ' . $to);
    }
}

public function test_internal_targets_are_kept(): void
{
    $this->assertSame('/coop-natcco/admin/cooperatives.php?action=view&id=5', safe_return('/coop-natcco/admin/cooperatives.php?action=view&id=5', 'admin/index.php'));
}
}
