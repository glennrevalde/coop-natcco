<?php
/**
 * Test Case ID: UTC-07
 * Module: Security
 * Unit: sanitize_html()
 * Role: Content Manager saving rich text
 * Description: Only allow-listed tags and attributes survive; unknown tags are unwrapped.
 * Expected: iframes, forms and style attributes are removed, unknown tags keep only their text, and allowed formatting stays.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC07_SanitizeAllowlistTest extends TestCase
{
public function test_disallowed_markup_is_removed(): void
{
    $out = sanitize_html('<iframe src="https://evil.example"></iframe><p style="color:red" class="x">Text</p><form action="/x"><input name="a"></form><marquee>Hi</marquee>');
    $this->assertStringNotContainsString('<iframe', $out);
    $this->assertStringNotContainsString('style=', $out);
    $this->assertStringNotContainsString('class=', $out);
    $this->assertStringNotContainsString('<form', $out);
    $this->assertStringNotContainsString('<marquee', $out);
    $this->assertStringContainsString('<p>Text</p>', $out);
    $this->assertStringContainsString('Hi', $out);
}

public function test_allowed_formatting_is_kept(): void
{
    $in = '<h2>Title</h2><ul><li><em>One</em></li></ul><blockquote>Q</blockquote>';
    $this->assertSame($in, sanitize_html($in));
    $this->assertSame('', sanitize_html('   '));
}
}
