<?php
/**
 * Test Case ID: UTC-06
 * Module: Security
 * Unit: sanitize_html() / safe_href()
 * Role: Content Manager saving rich text
 * Description: Links using javascript:, data: or protocol-relative addresses are neutralised.
 * Expected: The href attribute is removed for javascript:, data:, vbscript: and //host links, including mixed case and embedded whitespace; https, mailto, tel and relative links are kept.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC06_SanitizeBlocksDangerousUrlsTest extends TestCase
{
public function test_dangerous_hrefs_are_removed(): void
{
    foreach (['javascript:alert(1)', ' JaVaScRiPt:alert(1)', "java\tscript:alert(1)", 'data:text/html;base64,PHNjcmlwdD4=', 'vbscript:msgbox(1)', '//evil.example/x'] as $href) {
        $out = sanitize_html('<a href="' . htmlspecialchars($href, ENT_QUOTES) . '">x</a>');
        $this->assertStringNotContainsString('href', $out, 'href kept for ' . $href);
    }
}

public function test_safe_hrefs_are_kept(): void
{
    $this->assertTrue(safe_href('https://coop.example/a'));
    $this->assertTrue(safe_href('mailto:info@example.org'));
    $this->assertTrue(safe_href('tel:+6328000000'));
    $this->assertTrue(safe_href('/coop-natcco/page.php?slug=x'));
    $this->assertTrue(safe_href('#section'));
}
}
