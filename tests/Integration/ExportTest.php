<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ToolUnavailableException;
use App\Repositories\DocumentRepository;
use App\Repositories\OperationRepository;
use App\Services\ExportService;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

/**
 * Veri dışa aktarma (spec §24).
 */
final class ExportTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('export');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'export-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    private function exporter(): ExportService
    {
        return new ExportService(new DocumentRepository(self::db()), new OperationRepository(self::db()), $this->s->documents, $this->s->audit, $this->s->storage, 'TFB PDF');
    }

    public function testUnavailableWithoutZipExtension(): void
    {
        if (ExportService::available()) {
            self::markTestSkipped('ext-zip yüklü; bu test zip yokken davranışı sınar');
        }
        $doc = $this->s->uploadPdf($this->owner, 1);

        try {
            $this->exporter()->exportDocument($doc);
            self::fail('Expected unavailable');
        } catch (ToolUnavailableException $e) {
            self::assertSame('export.unavailable', $e->messageKey());
        }
    }

    public function testExportAllContainsFilesMetadataHistoryAuditAndManifest(): void
    {
        if (!ExportService::available()) {
            self::markTestSkipped('ext-zip yok (php -d extension=zip ile çalıştırın)');
        }

        $a = $this->s->uploadPdf($this->owner, 2, 'Rapor.pdf');
        $this->s->tools->rotate($this->owner, $a->publicId, 0, [1 => 90]);
        $deleted = $this->s->uploadPdf($this->owner, 1, 'Silinen.pdf');
        $this->s->documents->delete($deleted);
        $other = $this->s->uploadPdf(hash('sha256', 'stranger'), 1, 'Baskasi.pdf');

        $export = $this->exporter()->exportAll($this->owner);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($export['path']));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $folder = 'documents/' . $a->publicId . '/';
        foreach (['original.pdf', 'v001.pdf', 'metadata.json', 'operations.json'] as $file) {
            self::assertContains($folder . $file, $names);
        }
        foreach (['audit-log.json', 'documents.json', 'manifest.json', 'README.txt'] as $file) {
            self::assertContains($file, $names);
        }
        self::assertEmpty(preg_grep('/' . $other->publicId . '/', $names), 'Başka sahibin belgesi dahil edilmemeli');

        // Manifest: içerikteki her dosyanın SHA-256'sı doğru
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        foreach ($manifest['files'] as $entry => $hash) {
            self::assertSame($hash, hash('sha256', (string) $zip->getFromName($entry)), $entry);
        }
        self::assertSame($this->s->documents->version($a, 1)->sha256, $manifest['files'][$folder . 'v001.pdf']);

        $metadata = json_decode((string) $zip->getFromName($folder . 'metadata.json'), true);
        self::assertSame('Rapor.pdf', $metadata['original_name']);
        self::assertSame([0, 1], array_column($metadata['versions'], 'number'));
        $operations = json_decode((string) $zip->getFromName($folder . 'operations.json'), true);
        self::assertSame('rotate', $operations[0]['type']);

        // Silinmiş belgenin audit kayıtları dahil, başkasınınkiler değil
        $audit = json_decode((string) $zip->getFromName('audit-log.json'), true);
        $docIds = array_unique(array_column($audit, 'document_id'));
        self::assertContains($deleted->publicId, $docIds);
        self::assertNotContains($other->publicId, $docIds);
        $zip->close();

        // Export olayı audit'e yazıldı
        self::assertSame('export', self::db()->scalar("SELECT event_type FROM audit_events WHERE event_type = 'export'"));
        self::assertTrue($this->s->audit->verify()['ok']);
    }

    public function testSingleDocumentExport(): void
    {
        if (!ExportService::available()) {
            self::markTestSkipped('ext-zip yok');
        }
        $doc = $this->s->uploadPdf($this->owner, 1, 'Tek Belge.pdf');

        $export = $this->exporter()->exportDocument($doc);

        self::assertSame('Tek Belge-export.zip', $export['name']);
        $zip = new \ZipArchive();
        $zip->open($export['path']);
        self::assertNotFalse($zip->locateName('documents/' . $doc->publicId . '/original.pdf'));
        $zip->close();
    }
}
