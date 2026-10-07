<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Exceptions\ValidationException;
use App\Pdf\Fpdi;
use App\Pdf\Parser\PngPredictor;
use App\Pdf\PdfInspector;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use Tests\Support\CompressedPdfWriter;
use Tests\Support\TempDirectory;
use Tests\Support\TestPdf;

/**
 * Klasik, xref stream'li (PDF 1.5 object stream) ve hybrid PDF'lerin okunması.
 */
final class PdfParsingTest extends TestCase
{
    private static string $dir;

    private static string $classic;

    private static string $compressed;

    private static string $hybrid;

    public static function setUpBeforeClass(): void
    {
        self::$dir = TempDirectory::create('pdfparse');
        self::$classic = TestPdf::create(self::$dir . '/classic.pdf', 4, [[595.28, 841.89], [841.89, 595.28], [300, 400], [595.28, 841.89]]);
        self::$compressed = CompressedPdfWriter::convert(self::$classic, self::$dir . '/compressed.pdf');
        self::$hybrid = CompressedPdfWriter::convert(self::$classic, self::$dir . '/hybrid.pdf', true);
    }

    public static function tearDownAfterClass(): void
    {
        TempDirectory::remove(self::$dir);
    }

    public function testFixtureReallyUsesCompressedXref(): void
    {
        $data = (string) file_get_contents(self::$compressed);
        self::assertStringContainsString('/Type /XRef', $data);
        self::assertStringContainsString('/Type /ObjStm', $data);
        self::assertStringNotContainsString("\nxref\n", $data);
    }

    public function testStockFpdiCannotReadCompressedXref(): void
    {
        // Uzantımızın neden gerekli olduğunu belgeleyen test
        $stock = new \setasign\Fpdi\Tfpdf\Fpdi();
        try {
            $stock->setSourceFile(self::$compressed);
            self::fail('Stock FPDI was expected to reject compressed xref');
        } catch (CrossReferenceException $e) {
            self::assertSame(CrossReferenceException::COMPRESSED_XREF, $e->getCode());
        }
    }

    public function testInspectorReadsAllVariantsIdentically(): void
    {
        $inspector = new PdfInspector();
        $classic = $inspector->inspect(self::$classic);

        self::assertSame(4, $classic->pageCount);
        self::assertFalse($classic->usesCompressedXref);
        self::assertEqualsWithDelta(841.89, $classic->pages[1]['width'], 0.01);
        self::assertEqualsWithDelta(300.0, $classic->pages[2]['width'], 0.01);

        foreach ([self::$compressed, self::$hybrid] as $file) {
            $info = $inspector->inspect($file);
            self::assertSame(4, $info->pageCount, basename($file));
            self::assertTrue($info->usesCompressedXref, basename($file));
            self::assertSame($classic->pages, $info->pages, basename($file));
        }
    }

    public function testFpdiCanImportPagesFromCompressedPdf(): void
    {
        foreach ([self::$compressed, self::$hybrid] as $file) {
            $pdf = new Fpdi();
            self::assertSame(4, $pdf->setSourceFile($file));
            for ($i = 1; $i <= 4; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }
            $out = self::$dir . '/reimport-' . basename($file);
            $pdf->Output('F', $out);

            $info = (new PdfInspector())->inspect($out);
            self::assertSame(4, $info->pageCount);
            self::assertEqualsWithDelta(300.0, $info->pages[2]['width'], 0.01);
        }
    }

    public function testMaxPagesIsEnforced(): void
    {
        try {
            (new PdfInspector())->inspect(self::$compressed, 3);
            self::fail('Expected too_many_pages');
        } catch (ValidationException $e) {
            self::assertSame('upload.too_many_pages', $e->messageKey());
            self::assertSame(['max' => 3], $e->replace());
        }
    }

    public function testEncryptedPdfIsRecognised(): void
    {
        $data = (string) file_get_contents(self::$classic);
        $data = preg_replace('/trailer\s*<</', "trailer\n<<\n/Encrypt 999 0 R", $data, 1);
        $file = self::$dir . '/encrypted.pdf';
        file_put_contents($file, $data);

        try {
            (new PdfInspector())->inspect($file);
            self::fail('Expected encrypted rejection');
        } catch (ValidationException $e) {
            self::assertSame('upload.encrypted_pdf', $e->messageKey());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function brokenFiles(): iterable
    {
        yield 'random bytes' => [random_bytes(4096)];
        yield 'html renamed' => ['<html><body>not a pdf</body></html>'];
        yield 'header only' => ["%PDF-1.7\n%%EOF\n"];
        yield 'empty' => [''];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenFiles')]
    public function testBrokenFilesAreRejectedAsInvalid(string $content): void
    {
        $file = self::$dir . '/broken-' . bin2hex(random_bytes(3)) . '.pdf';
        file_put_contents($file, $content);

        $this->expectException(ValidationException::class);
        (new PdfInspector())->inspect($file);
    }

    public function testTruncatedPdfIsRejected(): void
    {
        $data = (string) file_get_contents(self::$compressed);
        $file = self::$dir . '/truncated.pdf';
        file_put_contents($file, substr($data, 0, intdiv(strlen($data), 2)));

        $this->expectException(ValidationException::class);
        (new PdfInspector())->inspect($file);
    }

    public function testPngPredictorAllRowTypes(): void
    {
        // 2 sütun, bpp=1. Satır 1: Sub, satır 2: Up, satır 3: Average, satır 4: Paeth, satır 5: None
        $encoded = "\x01\x0A\x05" . "\x02\x01\x01" . "\x03\x02\x03" . "\x04\x00\x00" . "\x00\x07\x08";
        $decoded = PngPredictor::decode($encoded, 2);

        self::assertSame([10, 15, 11, 16, 7, 14, 7, 14, 7, 8], array_values(unpack('C*', $decoded)));
    }
}
