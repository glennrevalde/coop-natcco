<?php
/**
 * Test Case ID: UTC-39
 * Module: File uploads
 * Unit: media_validate_upload()
 * Role: Content Manager uploading a publication
 * Description: Genuine PDF and Office files are accepted as documents; a fake PDF is refused.
 * Expected: A %PDF file and a ZIP-based .docx are accepted; a text file named .pdf is refused.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC39_UploadAcceptsPdfAndOfficeTest extends TestCase
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

public function test_pdf_and_docx(): void
{
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    $this->assertSame('pdf', media_validate_upload($this->upload('bulletin.pdf', $pdf), 'document')['ext']);

    $zipPath = tempnam(sys_get_temp_dir(), 'cnplz');
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
    $zip->close();
    $docx = (string) file_get_contents($zipPath);
    unlink($zipPath);
    $this->assertSame('docx', media_validate_upload($this->upload('form.docx', $docx), 'document')['ext']);

    $this->expectException(ValidationError::class);
    media_validate_upload($this->upload('fake.pdf', 'just text pretending'), 'document');
}
}
