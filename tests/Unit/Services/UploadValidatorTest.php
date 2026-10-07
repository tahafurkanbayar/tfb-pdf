<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Pdf\PdfInspector;
use App\Services\Upload\UploadValidator;
use PHPUnit\Framework\TestCase;
use Tests\Support\CompressedPdfWriter;
use Tests\Support\TempDirectory;
use Tests\Support\TestPdf;
use Tests\Support\TinyZip;

final class UploadValidatorTest extends TestCase
{
    private static string $dir;

    private static string $pdf;

    public static function setUpBeforeClass(): void
    {
        self::$dir = TempDirectory::create('upload');
        self::$pdf = TestPdf::create(self::$dir . '/valid.pdf', 2);
    }

    public static function tearDownAfterClass(): void
    {
        TempDirectory::remove(self::$dir);
    }

    private function validator(int $maxSize = 10_000_000, int $maxPages = 50): UploadValidator
    {
        return new UploadValidator(new PdfInspector(), $maxSize, $maxPages);
    }

    private function file(string $content, string $clientName, ?string $copyFrom = null): UploadedFile
    {
        $path = self::$dir . '/' . bin2hex(random_bytes(4));
        $copyFrom !== null ? copy($copyFrom, $path) : file_put_contents($path, $content);

        return UploadedFile::fromPath($path, $clientName, 'application/pdf');
    }

    private function assertRejected(callable $fn, string $expectedKey): void
    {
        try {
            $fn();
            self::fail('Expected rejection with ' . $expectedKey);
        } catch (ValidationException $e) {
            self::assertSame($expectedKey, $e->messageKey());
        }
    }

    public function testAcceptsValidPdfAndCleansName(): void
    {
        $result = $this->validator()->validatePdf($this->file('', '../../gizli/Rapor Ş.PDF', self::$pdf));

        self::assertSame('pdf', $result->kind);
        self::assertSame('application/pdf', $result->mimeType);
        self::assertSame('Rapor Ş.PDF', $result->originalName);
        self::assertSame(2, $result->pdf?->pageCount);
    }

    public function testAcceptsCompressedXrefPdf(): void
    {
        $compressed = CompressedPdfWriter::convert(self::$pdf, self::$dir . '/c.pdf');
        $result = $this->validator()->validatePdf($this->file('', 'modern.pdf', $compressed));

        self::assertSame(2, $result->pdf?->pageCount);
    }

    public function testRejectsPdfContentWithWrongExtension(): void
    {
        $this->assertRejected(fn () => $this->validator()->validatePdf($this->file('', 'virus.exe', self::$pdf)), 'upload.unsupported_type');
        $this->assertRejected(fn () => $this->validator()->validatePdf($this->file('', 'noext', self::$pdf)), 'upload.unsupported_type');
    }

    public function testRejectsNonPdfContentWithPdfExtension(): void
    {
        $this->assertRejected(fn () => $this->validator()->validatePdf($this->file('<?php system($_GET["c"]); ?>', 'shell.pdf')), 'upload.invalid_pdf');
        $this->assertRejected(fn () => $this->validator()->validatePdf($this->file('<html>x</html>', 'page.pdf')), 'upload.invalid_pdf');
        // İmza var ama yapı bozuk
        $this->assertRejected(fn () => $this->validator()->validatePdf($this->file("%PDF-1.4\ngarbage", 'broken.pdf')), 'upload.invalid_pdf');
    }

    public function testSizeAndPageLimits(): void
    {
        $this->assertRejected(fn () => $this->validator()->validatePdf($this->file('', 'empty.pdf')), 'upload.empty_file');
        $this->assertRejected(fn () => $this->validator(maxSize: 100)->validatePdf($this->file('', 'big.pdf', self::$pdf)), 'upload.file_too_large');
        $this->assertRejected(fn () => $this->validator(maxPages: 1)->validatePdf($this->file('', 'long.pdf', self::$pdf)), 'upload.too_many_pages');
    }

    public function testUploadErrorCodes(): void
    {
        $cases = [
            UPLOAD_ERR_INI_SIZE => 'upload.server_limit',
            UPLOAD_ERR_FORM_SIZE => 'upload.file_too_large',
            UPLOAD_ERR_PARTIAL => 'upload.partial',
            UPLOAD_ERR_NO_FILE => 'upload.no_file',
            UPLOAD_ERR_NO_TMP_DIR => 'upload.invalid_file',
        ];
        foreach ($cases as $code => $key) {
            $file = new UploadedFile('', 'a.pdf', 'application/pdf', 0, $code, false);
            $this->assertRejected(fn () => $this->validator()->validatePdf($file), $key);
        }
    }

    public function testRealHttpUploadCheckRejectsArbitraryPaths(): void
    {
        // Gerçek HTTP yüklemesi gibi işaretli ama is_uploaded_file() false: sunucudaki keyfi dosya okunamaz
        $file = new UploadedFile(self::$pdf, 'a.pdf', 'application/pdf', (int) filesize(self::$pdf), UPLOAD_ERR_OK, true);
        $this->assertRejected(fn () => $this->validator()->validatePdf($file), 'upload.invalid_file');
    }

    public function testOfficeValidation(): void
    {
        $docx = TinyZip::build(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>']);
        $result = $this->validator()->validateOffice($this->file($docx, 'teklif.docx'));
        self::assertSame('office', $result->kind);
        self::assertSame('docx', $result->extension);

        // docx uzantılı ama içerik Excel paketi
        $xlsx = TinyZip::build(['[Content_Types].xml' => '<Types/>', 'xl/workbook.xml' => '<workbook/>']);
        $this->assertRejected(fn () => $this->validator()->validateOffice($this->file($xlsx, 'sahte.docx')), 'upload.invalid_file');

        // Makrolu paket
        $macro = TinyZip::build(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<x/>', 'word/vbaProject.bin' => 'x']);
        $this->assertRejected(fn () => $this->validator()->validateOffice($this->file($macro, 'makro.docx')), 'upload.invalid_file');

        // Eski .doc (OLE) imzası + WordDocument akışı
        $ole = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 500) . mb_convert_encoding('WordDocument', 'UTF-16LE', 'UTF-8') . str_repeat("\0", 100);
        self::assertSame('doc', $this->validator()->validateOffice($this->file($ole, 'eski.doc'))->extension);
        $this->assertRejected(fn () => $this->validator()->validateOffice($this->file($ole, 'eski.xls')), 'upload.invalid_file');

        $this->assertRejected(fn () => $this->validator()->validateOffice($this->file($docx, 'x.docm')), 'upload.unsupported_type');
        $this->assertRejected(fn () => $this->validator()->validateOffice($this->file('', 'x.pdf', self::$pdf)), 'upload.unsupported_type');
    }
}
