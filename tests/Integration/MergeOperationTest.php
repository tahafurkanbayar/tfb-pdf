<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

/**
 * Yükle → DB → PDF işleme → yeni sürüm → hash → audit (spec §45 entegrasyon akışı) — birleştirme ile.
 */
final class MergeOperationTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('merge');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'merge-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testMergeCreatesNewDocumentAndKeepsOriginalsIntact(): void
    {
        $a = $this->s->uploadPdf($this->owner, 2, 'Rapor.pdf');
        $b = $this->s->uploadPdf($this->owner, 3, 'Ek.pdf');
        $originalA = hash_file('sha256', $this->s->versionPath($a, 0));
        $originalB = hash_file('sha256', $this->s->versionPath($b, 0));

        $result = $this->s->tools->merge($this->owner, [['document' => $b->publicId], ['document' => $a->publicId, 'version' => 0]]);

        self::assertSame('completed', $result->status);
        self::assertNotNull($result->document);
        self::assertSame('Ek-merged.pdf', $result->document->originalName);
        self::assertCount(1, $result->versions);
        $version = $result->versions[0];
        self::assertSame(1, $version->versionNumber);
        self::assertSame('v001.pdf', $version->filename);
        self::assertSame(5, $version->pageCount);

        // SHA-256 kaydı dosyayla eşleşiyor
        $path = $this->s->storage->resolve($version->storagePath);
        self::assertSame(hash_file('sha256', $path), $version->sha256);

        // Orijinaller değişmedi
        self::assertSame($originalA, hash_file('sha256', $this->s->versionPath($a, 0)));
        self::assertSame($originalB, hash_file('sha256', $this->s->versionPath($b, 0)));

        // Operation ve audit kayıtları
        $op = self::db()->first('SELECT * FROM operations WHERE public_id = ?', [$result->operationId]);
        self::assertSame('completed', $op['status']);
        self::assertSame('fpdi', $op['engine']);
        $event = self::db()->first("SELECT * FROM audit_events WHERE event_type = 'merge'");
        self::assertSame($result->document->publicId, $event['document_public_id']);
        self::assertSame($version->sha256, $event['output_hash']);
        self::assertSame($originalB, $event['input_hash']);
        self::assertTrue($this->s->audit->verify()['ok']);

        self::assertContains('warnings.links_removed', $result->warnings);
    }

    public function testMergeRejectsForeignDocumentsAndSingleInput(): void
    {
        $mine = $this->s->uploadPdf($this->owner, 1);
        $theirs = $this->s->uploadPdf(hash('sha256', 'stranger'), 1);

        try {
            $this->s->tools->merge($this->owner, [['document' => $mine->publicId], ['document' => $theirs->publicId]]);
            self::fail('Foreign document must not be accessible');
        } catch (NotFoundException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(ValidationException::class);
        $this->s->tools->merge($this->owner, [['document' => $mine->publicId]]);
    }

    public function testTamperedInputStopsOperationAndIsRecordedAsFailed(): void
    {
        $a = $this->s->uploadPdf($this->owner, 1);
        $b = $this->s->uploadPdf($this->owner, 1);
        // Depodaki dosyayı sonradan değiştir (ör. disk hatası / müdahale)
        file_put_contents($this->s->versionPath($b, 0), "\n", FILE_APPEND);

        try {
            $this->s->tools->merge($this->owner, [['document' => $a->publicId], ['document' => $b->publicId]]);
            self::fail('Expected integrity failure');
        } catch (\App\Exceptions\ProcessingException $e) {
            self::assertSame('operations.integrity_failed', $e->messageKey());
        }

        self::assertSame('failed', self::db()->scalar("SELECT status FROM operations WHERE type = 'merge'"));
        self::assertSame('failed', self::db()->scalar("SELECT status FROM audit_events WHERE event_type = 'merge'"));
        self::assertSame(2, (int) self::db()->scalar('SELECT COUNT(*) FROM documents'), 'Başarısız birleştirme yeni belge bırakmamalı');
        self::assertSame([], glob($this->root . '/versions/*/*/*') ?: []);
        self::assertSame([], glob($this->root . '/temporary/*') ?: [], 'Geçici dosyalar temizlenmeli');
    }
}
