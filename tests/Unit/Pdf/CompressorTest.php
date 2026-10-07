<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Pdf\Compression\Compressor;
use App\Pdf\Fpdi;
use App\Pdf\Parser\ExtendedPdfParser;
use App\Pdf\PdfInspector;
use App\Tools\CommandResult;
use App\Tools\ProcessRunner;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use Tests\Support\TempDirectory;
use Tests\Support\TestPdf;

final class CompressorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD yok');
        }
        $this->dir = TempDirectory::create('compress');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->dir);
    }

    /**
     * Büyük, yüksek kaliteli bir JPEG içeren PDF (taranmış belge benzeri).
     */
    private function photoPdf(): string
    {
        $img = imagecreatetruecolor(2400, 1800);
        mt_srand(42);
        for ($y = 0; $y < 1800; $y += 6) {
            for ($x = 0; $x < 2400; $x += 6) {
                imagefilledrectangle($img, $x, $y, $x + 5, $y + 5, (int) imagecolorallocate($img, mt_rand(0, 255), intdiv($x, 10) % 255, intdiv($y, 8) % 255));
            }
        }
        imagejpeg($img, $this->dir . '/photo.jpg', 95);
        imagedestroy($img);

        $pdf = new Fpdi();
        $pdf->AddPage('L', [841.89, 595.28]);
        $pdf->Image($this->dir . '/photo.jpg', 20, 20, 800, 552);
        $pdf->AddPage();
        $pdf->useUnicodeFont(14);
        $pdf->Text(50, 80, 'İkinci sayfa — metin');
        $pdf->Output('F', $this->dir . '/photo.pdf');

        return $this->dir . '/photo.pdf';
    }

    /**
     * @return list<array{int, int}> PDF'teki JPEG görüntülerin boyutları
     */
    private static function jpegSizes(string $file): array
    {
        $stream = StreamReader::createByFile($file);
        $parser = new ExtendedPdfParser($stream);
        $xref = $parser->getCrossReference();
        $sizes = [];
        for ($n = 1; $n < (int) PdfDictionary::get($xref->getTrailer(), 'Size')->value; $n++) {
            try {
                $object = $xref->getIndirectObject($n);
            } catch (\Throwable) {
                continue;
            }
            if ($object->value instanceof PdfStream && PdfDictionary::get($object->value->value, 'Filter') instanceof PdfName
                && PdfDictionary::get($object->value->value, 'Filter')->value === 'DCTDecode') {
                $sizes[] = [(int) PdfDictionary::get($object->value->value, 'Width')->value, (int) PdfDictionary::get($object->value->value, 'Height')->value];
            }
        }
        $stream->cleanUp();

        return $sizes;
    }

    public function testPhpCompressionDownsamplesJpegAndReallyShrinksFile(): void
    {
        $input = $this->photoPdf();
        $hash = hash_file('sha256', $input);

        $result = (new Compressor(new ProcessRunner(), null, 60))->compress($input, $this->dir . '/out.pdf', 'medium');

        self::assertSame('php', $result['engine']);
        self::assertSame(1, $result['details']['images_optimized']);
        self::assertLessThan($result['size_before'] * 0.7, $result['size_after'], 'Gerçek küçülme bekleniyor');
        self::assertSame(filesize($this->dir . '/out.pdf'), $result['size_after']);
        self::assertSame(2, (new PdfInspector())->inspect($this->dir . '/out.pdf')->pageCount);
        self::assertSame([[1600, 1200]], self::jpegSizes($this->dir . '/out.pdf'));
        self::assertSame([[2400, 1800]], self::jpegSizes($input));
        self::assertSame($hash, hash_file('sha256', $input), 'Girdi değişmemeli');

        // Sonuç gerçek bir JPEG içeriyor mu (GD ile çözülebilir)
        self::assertNotFalse(@imagecreatefromstring($this->extractFirstJpeg($this->dir . '/out.pdf')));
    }

    private function extractFirstJpeg(string $file): string
    {
        $data = (string) file_get_contents($file);
        $start = strpos($data, "\xFF\xD8\xFF");
        $end = strpos($data, "\xFF\xD9", (int) $start);

        return substr($data, (int) $start, (int) $end - (int) $start + 2);
    }

    public function testTextOnlyPdfDoesNotShrinkMeaningfully(): void
    {
        $input = TestPdf::create($this->dir . '/text.pdf', 2);
        $result = (new Compressor(new ProcessRunner(), null, 60))->compress($input, $this->dir . '/out.pdf', 'high');

        self::assertSame(0, $result['details']['images_optimized']);
        // Çağıran (PdfToolService) bu durumda yeni sürüm oluşturmaz
        self::assertGreaterThan($result['size_before'] * (1 - 0.03), $result['size_after']);
    }

    public function testGhostscriptCommandIsSafeAndOutputIsVerified(): void
    {
        $input = TestPdf::create($this->dir . '/in.pdf', 3);
        $runner = new class () extends ProcessRunner {
            /** @var list<list<string>> */
            public array $commands = [];

            public function run(array $command, int $timeoutSeconds = 60, ?string $cwd = null, ?array $env = null): CommandResult
            {
                $this->commands[] = $command;
                // Ghostscript'i taklit et: çıktı dosyasını yaz
                $out = substr((string) current(array_filter($command, static fn (string $a): bool => str_starts_with($a, '-sOutputFile='))), 13);
                copy((string) end($command), $out);

                return new CommandResult(0, '', '', false, 5);
            }
        };

        $result = (new Compressor($runner, '/usr/bin/gs', 60))->compress($input, $this->dir . '/gs-out.pdf', 'high');

        self::assertSame('ghostscript', $result['engine']);
        $command = $runner->commands[0];
        self::assertSame('/usr/bin/gs', $command[0]);
        self::assertContains('-dSAFER', $command);
        self::assertContains('-dPDFSETTINGS=/screen', $command);
        self::assertSame($input, end($command));
    }

    public function testGhostscriptFailureFallsBackToPhp(): void
    {
        $input = TestPdf::create($this->dir . '/in.pdf', 1);
        $failing = new class () extends ProcessRunner {
            public function run(array $command, int $timeoutSeconds = 60, ?string $cwd = null, ?array $env = null): CommandResult
            {
                return new CommandResult(1, '', 'Error: /undefined', false, 5);
            }
        };

        $result = (new Compressor($failing, '/usr/bin/gs', 60))->compress($input, $this->dir . '/out.pdf', 'medium');

        self::assertSame('php', $result['engine']);
        self::assertFileExists($this->dir . '/out.pdf');
    }
}
