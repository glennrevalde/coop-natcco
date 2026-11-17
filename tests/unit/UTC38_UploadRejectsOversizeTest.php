<?php
/**
 * Test Case ID: UTC-38
 * Module: File uploads
 * Unit: media_validate_upload()
 * Role: Any user uploading a file
 * Description: Files above the size limit, empty files and failed uploads are refused.
 * Expected: An 11 MB file, a 0-byte file and an upload with UPLOAD_ERR_PARTIAL raise ValidationError with a clear message.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC38_UploadRejectsOversizeTest extends TestCase
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

public function test_too_large(): void
{
    $this->expectException(ValidationError::class);
    $this->expectExceptionMessage('smaller than 10 MB');
    media_validate_upload($this->upload('big.png', $this->pngBytes(), 11 * 1024 * 1024), 'image');
}

public function test_empty_and_partial(): void
{
    try {
        media_validate_upload($this->upload('empty.png', ''), 'image');
        $this->assertTrue(false, 'accepted empty file');
    } catch (ValidationError $e) {
        $this->assertStringContainsString('smaller than', $e->getMessage());
    }
    $f = $this->upload('x.png', $this->pngBytes());
    $f['error'] = UPLOAD_ERR_PARTIAL;
    try {
        media_validate_upload($f, 'image');
        $this->assertTrue(false, 'accepted partial upload');
    } catch (ValidationError $e) {
        $this->assertSame('The upload was interrupted. Try again.', $e->getMessage());
    }
}
}
