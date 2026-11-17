<?php
/**
 * Test Case ID: UTC-43
 * Module: Website content
 * Unit: slugify()
 * Role: Content Manager publishing a page
 * Description: Page addresses are built from titles, including ñ and accented letters.
 * Expected: Titles become lowercase hyphenated slugs; ñ becomes n; empty input becomes "item"; length is capped.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC43_SlugifyHandlesFilipinoNamesTest extends TestCase
{
public function test_slugs(): void
{
    $this->assertSame('pasko-sa-paranaque-2026', slugify('Pasko sa Parañaque 2026!'));
    $this->assertSame('cooperative-leaders-gather', slugify('  Cooperative leaders: gather ... '));
    $this->assertSame('item', slugify('!!!'));
    $this->assertSame(10, strlen(slugify(str_repeat('abc ', 20), 10)));
}
}
