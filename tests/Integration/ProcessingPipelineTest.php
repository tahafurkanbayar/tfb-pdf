<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

/**
 * Spec §45 entegrasyon akışı, adım adım:
 * Upload → Database → PDF processing → New version → Hash → Audit event.
 */
final class ProcessingPipelineTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('pipeline');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'pipeline-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testUploadDatabaseProcessingVersionHashAudit(): void
    {
        // 1. Upload
        $document = $this->s->uploadPdf($this->owner, 3, 'Sözleşme.pdf');
        $originalPath = $this->s->versionPath($document, 0);
        $originalHash = hash_file('sha256', $originalPath);

        // 2. Database: belge + sürüm 0 kaydı, hash dosyanın gerçek özetiyle aynı
        $doc = self::db()->first('SELECT * FROM documents WHERE public_id = ?', [$document->publicId]);
        self::assertSame($this->owner, $doc['owner_hash']);
        self::assertSame('Sözleşme.pdf', $doc['original_name']);
        $v0 = self::db()->first('SELECT * FROM document_versions WHERE document_id = ? AND version_number = 0', [$doc['id']]);
        self::assertSame($originalHash, $v0['sha256']);
        self::assertNull($v0['operation_id']);
        self::assertSame(3, (int) $v0['page_count']);

        // 3. PDF processing (döndürme)
        $result = $this->s->tools->rotate($this->owner, $document->publicId, 0, [2 => 90]);
        self::assertSame('completed', $result->status);

        $operation = self::db()->first('SELECT * FROM operations WHERE public_id = ?', [$result->operationId]);
        self::assertSame('rotate', $operation['type']);
        self::assertSame('completed', $operation['status']);
        self::assertSame((int) $doc['id'], (int) $operation['document_id']);
        self::assertNotNull($operation['finished_at']);

        // 4. New version: numara 1, işleme bağlı, orijinal dokunulmadan duruyor
        $versions = self::db()->select('SELECT * FROM document_versions WHERE document_id = ? ORDER BY version_number', [$doc['id']]);
        self::assertSame([0, 1], array_map('intval', array_column($versions, 'version_number')));
        $v1 = $versions[1];
        self::assertSame((int) $operation['id'], (int) $v1['operation_id']);
        self::assertSame($originalHash, hash_file('sha256', $originalPath), 'Orijinal dosya değişmemeli');

        $v1Path = $this->s->versionPath($document, 1);
        self::assertNotSame($originalPath, $v1Path);
        $info = (new \App\Pdf\PdfInspector())->inspect($v1Path);
        self::assertSame([0, 90, 0], array_column($info->pages, 'rotation'));

        // 5. Hash: DB'deki özet, dosyanın özeti, sonuç nesnesi ve dosya boyutu tutarlı
        $v1Hash = hash_file('sha256', $v1Path);
        self::assertSame($v1Hash, $v1['sha256']);
        self::assertSame($v1Hash, $result->versions[0]->sha256);
        self::assertSame(filesize($v1Path), (int) $v1['file_size']);
        self::assertNotSame($originalHash, $v1Hash);

        // 6. Audit event: yükleme ve işlem olayları, girdi/çıktı özetleri, bozulmamış zincir
        $events = self::db()->select('SELECT * FROM audit_events WHERE document_public_id = ? ORDER BY id', [$document->publicId]);
        self::assertSame(['upload', 'rotate'], array_column($events, 'event_type'));
        self::assertSame($originalHash, $events[0]['output_hash']);
        self::assertSame('success', $events[1]['status']);
        self::assertSame($result->operationId, $events[1]['operation_public_id']);
        self::assertSame($originalHash, $events[1]['input_hash']);
        self::assertSame($v1Hash, $events[1]['output_hash']);
        self::assertSame($events[0]['event_hash'], $events[1]['prev_hash']);

        $chain = $this->s->audit->verify();
        self::assertTrue($chain['ok'], (string) $chain['reason']);
        self::assertSame(2, $chain['checked']);
    }

    public function testSecondOperationOnNewVersionContinuesNumberingAndChain(): void
    {
        $document = $this->s->uploadPdf($this->owner, 2);
        $first = $this->s->tools->rotate($this->owner, $document->publicId, 0, [1 => 90]);
        $second = $this->s->tools->watermark($this->owner, $document->publicId, 1, ['text' => 'TASLAK']);

        self::assertSame(2, $second->versions[0]->versionNumber);

        // İkinci işlemin girdisi sürüm 1'dir: audit girdi özeti sürüm 1'in özeti
        $event = self::db()->first("SELECT * FROM audit_events WHERE event_type = 'watermark'");
        self::assertSame($first->versions[0]->sha256, $event['input_hash']);
        self::assertSame($second->versions[0]->sha256, $event['output_hash']);

        // Sürüm 1 dosyası ikinci işlemden sonra da aynı
        self::assertSame($first->versions[0]->sha256, hash_file('sha256', $this->s->versionPath($document, 1)));
        self::assertTrue($this->s->audit->verify()['ok']);
    }
}
