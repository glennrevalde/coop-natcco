<?php
/**
 * Test Case ID: UTC-05
 * Module: Security
 * Unit: sanitize_html()
 * Role: Content Manager saving rich text
 * Description: Event-handler attributes are removed from every element.
 * Expected: onerror, onclick and onmouseover attributes are gone; the link text and its https address remain.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC05_SanitizeRemovesEventHandlersTest extends TestCase
{
public function test_event_handlers_are_removed(): void
{
    $out = sanitize_html('<a href="https://coop.example/page" onclick="steal()" onmouseover="x()">Read more</a><img src="x" onerror="alert(1)">');
    $this->assertStringNotContainsString('onclick', $out);
    $this->assertStringNotContainsString('onmouseover', $out);
    $this->assertStringNotContainsString('onerror', $out);
    $this->assertStringContainsString('href="https://coop.example/page"', $out);
    $this->assertStringContainsString('Read more', $out);
}

public function test_external_links_get_noopener(): void
{
    $out = sanitize_html('<a href="https://coop.example/">Site</a>');
    $this->assertStringContainsString('rel="noopener noreferrer"', $out);
}
}
