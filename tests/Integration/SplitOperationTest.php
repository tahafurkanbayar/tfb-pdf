<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Pdf\PdfInspector;
use App\Repositories\DocumentRepository;
use App\Repositories\OperationRepository;
use App\Repositories\VersionRepository;
use App\Services\Operations\OperationArchiveService;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

final class SplitOperationTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('split');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'split-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testRangesModeCreatesOneVersionPerRange(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 12);
        $original = hash_file('sha256', $this->s->versionPath($doc, 0));

        $result = $this->s->tools->split($this->owner, $doc->publicId, null, 'ranges', "1-3\n5\n8-12");

        self::assertSame('completed', $result->status);
        self::assertSame([1, 2, 3], array_map(fn ($v) => $v->versionNumber, $result->versions));
        self::assertSame(['1-3', '5', '8-12'], array_map(fn ($v) => $v->label, $result->versions));
        self::assertSame([3, 1, 5], array_map(fn ($v) => $v->pageCount, $result->versions));
        foreach ($result->versions as $version) {
            $path = $this->s->storage->resolve($version->storagePath);
            self::assertSame($version->pageCount, (new PdfInspector())->inspect($path)->pageCount);
            self::assertSame($version->sha256, hash_file('sha256', $path));
        }
        self::assertSame($original, hash_file('sha256', $this->s->versionPath($doc, 0)), 'Orijinal değişmemeli');
    }

    public function testEachAndExtractModes(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 4);

        $each = $this->s->tools->split($this->owner, $doc->publicId, 0, 'each');
        self::assertCount(4, $each->versions);
        self::assertSame(['1', '2', '3', '4'], array_map(fn ($v) => $v->label, $each->versions));

        $extract = $this->s->tools->split($this->owner, $doc->publicId, 0, 'extract', '4, 1-2');
        self::assertCount(1, $extract->versions);
        self::assertSame(3, $extract->versions[0]->pageCount);
        self::assertSame('4, 1-2', $extract->versions[0]->label);
        // Sürüm numaraları sürer: each 1-4, extract 5
        self::assertSame(5, $extract->versions[0]->versionNumber);
    }

    public function testInvalidRangesAreRejectedBeforeAnyOperationIsRecorded(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 3);

        foreach (['5-1' => 'split.range_reversed', '1-9' => 'split.range_out_of_bounds', 'x' => 'split.range_invalid', '' => 'split.range_empty'] as $input => $key) {
            try {
                $this->s->tools->split($this->owner, $doc->publicId, null, 'ranges', (string) $input);
                self::fail('Expected ' . $key);
            } catch (ValidationException $e) {
                self::assertSame($key, $e->messageKey());
            }
        }

        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM operations'));
    }

    public function testZipArchiveOfOutputs(): void
    {
        if (!OperationArchiveService::available()) {
            self::markTestSkipped('ext-zip yok (php -d extension=zip ile çalıştırın)');
        }

        $doc = $this->s->uploadPdf($this->owner, 3, 'Fatura.pdf');
        $result = $this->s->tools->split($this->owner, $doc->publicId, null, 'each');

        $archives = new OperationArchiveService(
            new OperationRepository(self::db()),
            new DocumentRepository(self::db()),
            new VersionRepository(self::db()),
            $this->s->storage,
            $this->s->audit
        );
        $zip = $archives->build($result->operationId, $this->owner);

        self::assertSame('Fatura-split.zip', $zip['name']);
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($zip['path']));
        self::assertSame(3, $archive->numFiles);
        self::assertSame('Fatura-v001-p1.pdf', $archive->getNameIndex(0));
        $archive->close();

        $this->expectException(\App\Exceptions\NotFoundException::class);
        $archives->build($result->operationId, hash('sha256', 'stranger'));
    }
}
