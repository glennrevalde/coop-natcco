<?php
/**
 * Test Case ID: UTC-09
 * Module: Website content
 * Unit: video_embed_url()
 * Role: Content Manager adding a gallery video
 * Description: Only YouTube and Vimeo links become embeddable videos.
 * Expected: YouTube links become youtube-nocookie embed URLs, Vimeo links become player URLs, and any other address returns null.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC09_VideoEmbedAllowlistTest extends TestCase
{
public function test_known_hosts_are_converted(): void
{
    $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', video_embed_url('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', video_embed_url('https://youtu.be/dQw4w9WgXcQ'));
    $this->assertSame('https://player.vimeo.com/video/76979871', video_embed_url('https://vimeo.com/76979871'));
}

public function test_other_addresses_are_rejected(): void
{
    $this->assertNull(video_embed_url('http://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    $this->assertNull(video_embed_url('https://evil.example/youtube.com/watch?v=dQw4w9WgXcQ'));
    $this->assertNull(video_embed_url('javascript:alert(1)'));
    $this->assertNull(video_embed_url(''));
}
}
