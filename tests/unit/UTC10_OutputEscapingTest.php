<?php
/**
 * Test Case ID: UTC-10
 * Module: Security
 * Unit: e() / nl2p()
 * Role: Any visitor viewing user-supplied text
 * Description: Text printed into pages is HTML-escaped, including quotes.
 * Expected: Angle brackets, ampersands and both quote types are escaped (&quot; and &apos;); nl2p() escapes before adding paragraphs.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC10_OutputEscapingTest extends TestCase
{
public function test_e_escapes_markup_and_quotes(): void
{
    $this->assertSame('&lt;script&gt;x&lt;/script&gt; &amp; &quot;a&quot; &apos;b&apos;', e('<script>x</script> & "a" \'b\''));
    $this->assertSame('', e(null));
}

public function test_nl2p_escapes_then_wraps(): void
{
    $this->assertSame('<p>Line &lt;b&gt;1<br />' . "\n" . 'Line 2</p><p>Next</p>', nl2p("Line <b>1\nLine 2\n\nNext"));
}
}
