<?php
/**
 * Test Case ID: UTC-35
 * Module: File uploads
 * Unit: media_validate_upload()
 * Role: Any user uploading a file
 * Description: Files with a hidden executable extension are refused even if the content is a real image.
 * Expected: shell.php.png, report.phtml.pdf and x.svg.png raise ValidationError "This file type is not allowed."
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC35_UploadRejectsDoubleExtensionTest extends TestCase
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

public function test_double_extensions(): void
{
    foreach (['shell.php.png', 'report.phtml.pdf', 'x.svg.png', 'run.PHP5.png'] as $name) {
        try {
            media_validate_upload($this->upload($name, $this->pngBytes()), 'attachment');
            $this->assertTrue(false, 'accepted ' . $name);
        } catch (ValidationError $e) {
            $this->assertSame('This file type is not allowed.', $e->getMessage());
        }
    }
}
}
