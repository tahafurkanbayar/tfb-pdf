<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Exceptions\ToolUnavailableException;
use App\Exceptions\ValidationException;
use App\Pdf\Fpdi;
use App\Pdf\Ocr\OcrEngine;
use App\Pdf\PdfInspector;
use App\Pdf\PdfService;
use App\Tools\CommandResult;
use App\Tools\ProcessRunner;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;
use Tests\Support\TestPdf;

final class OcrEngineTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = TempDirectory::create('ocr');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->dir);
    }

    /**
     * Ghostscript / pdftoppm ve Tesseract'ı taklit eden çalıştırıcı.
     */
    private function fakeRunner(string $languages = "List of available languages (2):\neng\ntur\n"): ProcessRunner
    {
        return new class ($languages) extends ProcessRunner {
            /** @var list<list<string>> */
            public array $commands = [];

            public function __construct(private readonly string $languages)
            {
            }

            public function run(array $command, int $timeoutSeconds = 60, ?string $cwd = null, ?array $env = null): CommandResult
            {
                $this->commands[] = $command;
                if (in_array('--list-langs', $command, true)) {
                    return new CommandResult(0, $this->languages, '', false, 1);
                }
                $output = current(array_filter($command, static fn (string $a): bool => str_starts_with($a, '-sOutputFile=')));
                if ($output !== false) {                         // Ghostscript: sayfa görüntüsü
                    imagepng(imagecreatetruecolor(50, 70), substr($output, 13));

                    return new CommandResult(0, '', '', false, 1);
                }
                if (in_array('-singlefile', $command, true)) {    // pdftoppm
                    imagepng(imagecreatetruecolor(50, 70), end($command) . '.png');

                    return new CommandResult(0, '', '', false, 1);
                }
                // Tesseract: <png> <base> -l <langs> --dpi 300 pdf → <base>.pdf
                $pdf = new Fpdi();
                $pdf->AddPage();
                $pdf->Output('F', $command[2] . '.pdf');

                return new CommandResult(0, '', '', false, 1);
            }
        };
    }

    public function testRunsTesseractPerPageAndMergesResult(): void
    {
        $input = TestPdf::create($this->dir . '/scan.pdf', 3);
        $runner = $this->fakeRunner();
        $engine = new OcrEngine($runner, new PdfService(100), '/usr/bin/tesseract', '/usr/bin/gs', null, 'tur+eng+deu', 60);

        self::assertTrue($engine->available());
        $result = $engine->run($input, $this->dir . '/out.pdf', 3, $this->dir);

        self::assertSame(['pages' => 3, 'languages' => 'tur+eng'], $result, 'Kurulu olmayan dil (deu) çıkarılmalı');
        self::assertSame(3, (new PdfInspector())->inspect($this->dir . '/out.pdf')->pageCount);

        $gs = $runner->commands[1];
        self::assertSame('/usr/bin/gs', $gs[0]);
        self::assertContains('-dSAFER', $gs);
        self::assertContains('-dFirstPage=1', $gs);
        $tesseract = $runner->commands[2];
        self::assertSame(['/usr/bin/tesseract'], array_slice($tesseract, 0, 1));
        self::assertSame(['-l', 'tur+eng', '--dpi', '300', 'pdf'], array_slice($tesseract, 3));
        // Geçici sayfa dosyaları temizlenir
        self::assertSame([], glob($this->dir . '/ocr-*') ?: []);
    }

    public function testPdftoppmIsUsedWhenGhostscriptIsMissing(): void
    {
        $input = TestPdf::create($this->dir . '/scan.pdf', 1);
        $runner = $this->fakeRunner();
        $engine = new OcrEngine($runner, new PdfService(100), '/usr/bin/tesseract', null, '/usr/bin/pdftoppm', 'eng', 60);

        $engine->run($input, $this->dir . '/out.pdf', 1, $this->dir);

        self::assertSame('/usr/bin/pdftoppm', $runner->commands[1][0]);
        self::assertContains('-singlefile', $runner->commands[1]);
        self::assertFileExists($this->dir . '/out.pdf');
    }

    public function testUnavailableWithoutToolsAndPageLimit(): void
    {
        $runner = $this->fakeRunner();
        foreach ([[null, '/usr/bin/gs', null], ['/usr/bin/tesseract', null, null]] as [$tess, $gs, $ppm]) {
            $engine = new OcrEngine($runner, new PdfService(100), $tess, $gs, $ppm, 'eng', 60);
            self::assertFalse($engine->available());
            try {
                $engine->run('x.pdf', 'y.pdf', 1, $this->dir);
                self::fail('Expected unavailable');
            } catch (ToolUnavailableException $e) {
                self::assertSame('errors.tool_unavailable', $e->messageKey());
            }
        }

        $engine = new OcrEngine($runner, new PdfService(100), '/usr/bin/tesseract', '/usr/bin/gs', null, 'eng', 60);
        $this->expectException(ValidationException::class);
        $engine->run('x.pdf', 'y.pdf', OcrEngine::MAX_PAGES + 1, $this->dir);
    }

    public function testNoLanguageDataMeansUnavailable(): void
    {
        $engine = new OcrEngine($this->fakeRunner("List of available languages (0):\n"), new PdfService(100), '/usr/bin/tesseract', '/usr/bin/gs', null, 'tur', 60);

        $this->expectException(ToolUnavailableException::class);
        $engine->languages();
    }
}
