<?php
/**
 * Test Case ID: UTC-04
 * Module: Security
 * Unit: sanitize_html()
 * Role: Content Manager saving rich text
 * Description: Script and style blocks are removed from rich text together with their content.
 * Expected: The output keeps the paragraph and contains no <script>, <style> or the script body.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC04_SanitizeRemovesScriptsTest extends TestCase
{
public function test_script_and_style_are_dropped_with_content(): void
{
    $out = sanitize_html('<p>Hello</p><script>alert(document.cookie)</script><style>body{display:none}</style>');
    $this->assertSame('<p>Hello</p>', $out);
    $this->assertStringNotContainsString('alert', $out);
    $this->assertStringNotContainsString('display:none', $out);
}

public function test_nested_and_uppercase_scripts_are_dropped(): void
{
    $out = sanitize_html('<div><SCRIPT SRC="https://evil.example/x.js"></SCRIPT><strong>Safe</strong></div>');
    $this->assertStringNotContainsString('evil.example', $out);
    $this->assertStringContainsString('<strong>Safe</strong>', $out);
}
}
