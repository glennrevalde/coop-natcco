<?php
/**
 * Test Case ID: UTC-36
 * Module: File uploads
 * Unit: media_validate_upload()
 * Role: Any user uploading a file
 * Description: A PHP script renamed to .jpg is refused because its content is not a JPEG.
 * Expected: ValidationError "The file content does not match its type." is raised.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC36_UploadRejectsDisguisedScriptTest extends TestCase
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

public function test_php_as_jpg(): void
{
    $this->expectException(ValidationError::class);
    $this->expectExceptionMessage('does not match');
    media_validate_upload($this->upload('avatar.jpg', '<?php system($_GET["c"]); ?>'), 'image');
}
}
