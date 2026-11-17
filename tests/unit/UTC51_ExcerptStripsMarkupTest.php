<?php
/**
 * Test Case ID: UTC-51
 * Module: Website content
 * Unit: excerpt()
 * Role: Visitor reading a news listing
 * Description: Card summaries are plain text, trimmed to length.
 * Expected: Tags are removed, entities decoded, whitespace collapsed, and long text ends with an ellipsis within the limit.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC51_ExcerptStripsMarkupTest extends TestCase
{
public function test_excerpt(): void
{
    $this->assertSame('Hello & welcome to the site', excerpt("<p>Hello &amp; <strong>welcome</strong></p>\n<p>to the site</p>"));
    $long = excerpt(str_repeat('word ', 100), 30);
    $this->assertSame(30, mb_strlen($long));
    $this->assertStringContainsString('…', $long);
}
}
