<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ProcessingException;
use App\Exceptions\ToolUnavailableException;
use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Pdf\Office\OfficeConverter;
use App\Tools\CommandResult;
use App\Tools\ProcessRunner;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestPdf;
use Tests\Support\TestSchema;
use Tests\Support\TinyZip;

final class OfficeConvertOperationTest extends DatabaseTestCase
{
    private string $root;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('office');
        $this->owner = hash('sha256', 'office-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    /**
     * LibreOffice'i taklit eder: --outdir içine document.pdf yazar.
     */
    private function fakeSoffice(bool $succeed = true): ProcessRunner
    {
        return new class ($succeed) extends ProcessRunner {
            /** @var list<array{list<string>, ?array<string, string>}> */
            public array $calls = [];

            public function __construct(private readonly bool $succeed)
            {
            }

            public function run(array $command, int $timeoutSeconds = 60, ?string $cwd = null, ?array $env = null): CommandResult
            {
                $this->calls[] = [$command, $env];
                if (!$this->succeed) {
                    return new CommandResult(1, '', 'Error: source file could not be loaded', false, 5);
                }
                $outDir = $command[array_search('--outdir', $command, true) + 1];
                TestPdf::create($outDir . '/document.pdf', 2, null, 'Dönüştürülen');

                return new CommandResult(0, 'convert ' . end($command), '', false, 5);
            }
        };
    }

    private function docx(): UploadedFile
    {
        file_put_contents($this->root . '/teklif.docx', TinyZip::build(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>']));

        return UploadedFile::fromPath($this->root . '/teklif.docx', 'Teklif.docx');
    }

    public function testDocxIsStoredAsOriginalAndConvertedToPdfVersion(): void
    {
        $runner = $this->fakeSoffice();
        $s = new Services(self::db(), $this->root, 0, $runner);

        $document = $s->documents->upload($this->docx(), $this->owner, null, allowOffice: true);
        $original = $s->documents->version($document, 0);
        self::assertSame('original.docx', $original->filename);
        self::assertNull($original->pageCount);

        $result = $s->tools->officeConvert($this->owner, $document->publicId);

        self::assertSame('completed', $result->status);
        self::assertSame(1, $result->versions[0]->versionNumber);
        self::assertSame('application/pdf', $result->versions[0]->mimeType);
        self::assertSame(2, $result->versions[0]->pageCount);
        self::assertContains('warnings.font_substitution', $result->warnings);
        self::assertContains('warnings.office_signatures', $result->warnings);
        // Orijinal docx korunur
        self::assertSame($original->sha256, hash_file('sha256', $s->versionPath($document, 0)));

        [$command, $env] = $runner->calls[0];
        self::assertSame('/usr/bin/soffice', $command[0]);
        self::assertContains('--headless', $command);
        self::assertStringStartsWith('-env:UserInstallation=file:///', $command[1]);
        self::assertStringEndsWith('document.docx', end($command), 'Uzantılı sabit adlı kopya ile çalışılmalı');
        self::assertArrayHasKey('HOME', $env);

        // Belge artık PDF araçlarında kullanılabilir (en son PDF sürümü)
        self::assertSame(1, $s->documents->latestPdfVersion($document)?->versionNumber);
    }

    public function testConversionFailureIsRecorded(): void
    {
        $s = new Services(self::db(), $this->root, 0, $this->fakeSoffice(false));
        $document = $s->documents->upload($this->docx(), $this->owner, null, allowOffice: true);

        try {
            $s->tools->officeConvert($this->owner, $document->publicId);
            self::fail('Expected failure');
        } catch (ProcessingException $e) {
            self::assertSame('office.conversion_failed', $e->messageKey());
        }
        self::assertSame('failed', self::db()->scalar("SELECT status FROM operations WHERE type = 'office_convert'"));
    }

    public function testOfficeUploadAndConversionAreRefusedWithoutLibreOffice(): void
    {
        $s = new Services(self::db(), $this->root);

        try {
            $s->documents->upload($this->docx(), $this->owner, null, allowOffice: true);
            self::fail('Expected tool unavailable');
        } catch (ToolUnavailableException $e) {
            self::assertSame('errors.tool_unavailable', $e->messageKey());
        }

        // Office yüklemesine izin verilmeyen yerlerde (PDF araçları) docx türü reddedilir
        $this->expectException(ValidationException::class);
        $s->documents->upload($this->docx(), $this->owner);
    }

    public function testPdfDocumentCannotBeOfficeConverted(): void
    {
        $s = new Services(self::db(), $this->root, 0, $this->fakeSoffice());
        $pdf = $s->uploadPdf($this->owner, 1);

        $this->expectException(ValidationException::class);
        $s->tools->officeConvert($this->owner, $pdf->publicId);
    }

    public function testConverterUnavailableWithoutBinary(): void
    {
        self::assertFalse((new OfficeConverter(new ProcessRunner(), null, 10))->available());
    }
}
