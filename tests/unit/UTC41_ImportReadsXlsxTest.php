<?php
/**
 * Test Case ID: UTC-41
 * Module: Data import
 * Unit: import_read_file()
 * Role: Data Encoder importing cooperatives
 * Description: XLSX workbooks are read as data, including shared strings, inline strings and skipped columns.
 * Expected: The first sheet is read; text from shared and inline strings and numbers land in the right columns; a gap column stays empty.
 *
 * Complete test steps, assertions, fixtures and doubles are contained in this file.
 * @standalone-utc v1
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class UTC41_ImportReadsXlsxTest extends TestCase
{
private function xlsx(): string
{
    $path = tempnam(sys_get_temp_dir(), 'cnplx') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Coops" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Name</t></si><si><t>Members</t></si><si><r><t>Bayanihan </t></r><r><t>MPC</t></r></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1"><v></v></c><c r="C1" t="s"><v>1</v></c></row>'
        . '<row r="2"><c r="A2" t="s"><v>2</v></c><c r="C2"><v>120</v></c></row>'
        . '<row r="3"><c t="inlineStr"><is><t>Pag-asa Credit</t></is></c><c/><c><v>45</v></c></row>'
        . '</sheetData></worksheet>');
    $zip->close();
    register_shutdown_function(fn() => @unlink($path));
    return $path;
}

public function test_xlsx(): void
{
    [$headers, $rows] = import_read_file($this->xlsx(), 'xlsx');
    $this->assertSame(['Name', '', 'Members'], $headers);
    $this->assertSame(['Bayanihan MPC', '', '120'], $rows[0]);
    $this->assertSame(['Pag-asa Credit', '', '45'], $rows[1]);
}
}
