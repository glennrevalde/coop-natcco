<?php
/**
 * Test Case ID: UTC-37
 * Module: File uploads
 * Unit: media_validate_upload()
 * Role: Any user uploading a file
 * Description: File types outside the allowlist are refused.
 * Expected: setup.exe and logo.svg are refused as not allowed; notes.txt is refused with the list of allowed types.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC37_UploadRejectsDisallowedTypesTest extends TestCase
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

public function test_blocked_types(): void
{
    foreach (['setup.exe', 'logo.svg', 'page.html'] as $name) {
        try {
            media_validate_upload($this->upload($name, 'MZ....'), 'attachment');
            $this->assertTrue(false, 'accepted ' . $name);
        } catch (ValidationError $e) {
            $this->assertSame('This file type is not allowed.', $e->getMessage());
        }
    }
}

public function test_unknown_type(): void
{
    $this->expectException(ValidationError::class);
    $this->expectExceptionMessage('Allowed file types: JPG, JPEG, PNG, WEBP, GIF');
    media_validate_upload($this->upload('notes.txt', 'plain text'), 'image');
}
}
