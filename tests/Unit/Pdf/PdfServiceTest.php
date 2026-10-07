<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Exceptions\ValidationException;
use App\Pdf\PageRangeParser;
use App\Pdf\PdfInspector;
use App\Pdf\PdfService;
use PHPUnit\Framework\TestCase;
use Tests\Support\CompressedPdfWriter;
use Tests\Support\TempDirectory;
use Tests\Support\TestPdf;

final class PdfServiceTest extends TestCase
{
    private string $dir;

    private PdfService $pdf;

    protected function setUp(): void
    {
        $this->dir = TempDirectory::create('pdfsvc');
        $this->pdf = new PdfService(100);
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->dir);
    }

    public function testMergeKeepsOrderPageCountAndSizes(): void
    {
        $a = TestPdf::create($this->dir . '/a.pdf', 2, [[595.28, 841.89], [595.28, 841.89]]);
        $b = TestPdf::create($this->dir . '/b.pdf', 1, [[841.89, 595.28]]);
        $c = CompressedPdfWriter::convert(TestPdf::create($this->dir . '/c0.pdf', 3, [[300, 400], [300, 400], [300, 400]]), $this->dir . '/c.pdf');
        $hashes = array_map('sha1_file', [$a, $b, $c]);

        $pages = $this->pdf->merge([$b, $a, $c], $this->dir . '/out.pdf');

        self::assertSame(6, $pages);
        $info = (new PdfInspector())->inspect($this->dir . '/out.pdf');
        self::assertSame(6, $info->pageCount);
        // Sıra: b (yatay), a, a, c, c, c
        self::assertEqualsWithDelta(841.89, $info->pages[0]['width'], 0.01);
        self::assertEqualsWithDelta(595.28, $info->pages[1]['width'], 0.01);
        self::assertEqualsWithDelta(300.0, $info->pages[5]['width'], 0.01);
        // Girdiler değişmedi
        self::assertSame($hashes, array_map('sha1_file', [$a, $b, $c]));
    }

    public function testMergeRequiresTwoFilesAndRespectsPageLimit(): void
    {
        $a = TestPdf::create($this->dir . '/a.pdf', 3);

        try {
            $this->pdf->merge([$a], $this->dir . '/x.pdf');
            self::fail('Expected validation error');
        } catch (ValidationException $e) {
            self::assertSame('operations.merge_min_files', $e->messageKey());
        }

        try {
            (new PdfService(5))->merge([$a, $a], $this->dir . '/y.pdf');
            self::fail('Expected page limit');
        } catch (ValidationException $e) {
            self::assertSame('upload.too_many_pages', $e->messageKey());
        }
    }

    public function testExtractSelectsPagesAndAppliesRotation(): void
    {
        $src = TestPdf::create($this->dir . '/src.pdf', 4);

        $count = $this->pdf->extract($src, $this->dir . '/out.pdf', [4, 1, 3], [1 => 90, 3 => 270]);

        self::assertSame(3, $count);
        $info = (new PdfInspector())->inspect($this->dir . '/out.pdf');
        self::assertSame([0, 90, 270], array_column($info->pages, 'rotation'));

        $this->expectException(ValidationException::class);
        $this->pdf->extract($src, $this->dir . '/bad.pdf', [5]);
    }

    public function testSplitIntoRangesProducesOneFilePerRangeInOrder(): void
    {
        // Her sayfa farklı genişlikte: çıktılardaki sayfaların hangi kaynak sayfadan geldiği ölçülebilir
        $sizes = array_map(static fn (int $i): array => [300.0 + $i * 10, 500.0], range(1, 6));
        $src = TestPdf::create($this->dir . '/src.pdf', 6, $sizes);
        $before = sha1_file($src);

        $ranges = PageRangeParser::parse('1-2, 5, 3-4', 6);
        $inspector = new PdfInspector();
        $widths = [];
        foreach ($ranges as $i => $range) {
            $out = $this->dir . '/part-' . ($i + 1) . '.pdf';
            self::assertSame(count(PageRangeParser::pages($range)), $this->pdf->extract($src, $out, PageRangeParser::pages($range)));
            $widths[] = array_map(static fn (array $p): int => (int) round($p['width']), $inspector->inspect($out)->pages);
        }

        self::assertSame([[310, 320], [350], [330, 340]], $widths);
        self::assertSame($before, sha1_file($src), 'Kaynak dosya değişmemeli');
    }

    public function testRotationNormalization(): void
    {
        self::assertSame(90, PdfService::normalizeRotation(450));
        self::assertSame(270, PdfService::normalizeRotation(-90));
        self::assertSame(0, PdfService::normalizeRotation(360));

        $this->expectException(ValidationException::class);
        PdfService::normalizeRotation(45);
    }

    public function testRefusesToOverwriteOutput(): void
    {
        $a = TestPdf::create($this->dir . '/a.pdf', 1);
        file_put_contents($this->dir . '/exists.pdf', 'x');

        $this->expectException(\App\Exceptions\ProcessingException::class);
        $this->pdf->extract($a, $this->dir . '/exists.pdf', [1]);
    }
}
