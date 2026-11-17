<?php
/**
 * Test Case ID: UTC-34
 * Module: File uploads
 * Unit: media_validate_upload()
 * Role: Content Manager uploading a photo
 * Description: A genuine PNG with a safe name is accepted.
 * Expected: The result reports extension png, MIME image/png, and a cleaned original name.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC34_UploadAcceptsValidImageTest extends TestCase
{
private function upload(string $name, string $contents, ?int $size = null): array
{
    $path = test_temp_file($contents);
    return ['name' => $name, 'type' => 'application/octet-stream', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => $size ?? strlen($contents)];
}

private function pngBytes(): string
{
    $img = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($img);
    return (string) ob_get_clean();
}

public function test_valid_png(): void
{
    $info = media_validate_upload($this->upload('Team photo (1).png', $this->pngBytes()), 'image');
    $this->assertSame('png', $info['ext']);
    $this->assertSame('image/png', $info['mime']);
    $this->assertSame('Team photo (1).png', $info['original']);
}
}
