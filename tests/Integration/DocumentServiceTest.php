<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Repositories\DocumentRepository;
use App\Repositories\ExpiryRepository;
use App\Repositories\OperationRepository;
use App\Repositories\VersionRepository;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\HashService;
use App\Services\StorageService;
use App\Services\Upload\UploadValidator;
use App\Pdf\PdfInspector;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TempDirectory;
use Tests\Support\TestPdf;
use Tests\Support\TestSchema;

final class DocumentServiceTest extends DatabaseTestCase
{
    private string $root;

    private StorageService $storage;

    private AuditService $audit;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('docsvc');
        $this->storage = new StorageService($this->root);
        $this->storage->ensureDirectories();
        $this->audit = new AuditService(self::db());
        $this->owner = hash('sha256', 'owner-a');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    private function service(int $quota = 0): DocumentService
    {
        $db = self::db();

        return new DocumentService(
            $db,
            new DocumentRepository($db),
            new VersionRepository($db),
            new OperationRepository($db),
            new ExpiryRepository($db),
            $this->storage,
            new HashService(),
            $this->audit,
            new UploadValidator(new PdfInspector(), 50_000_000, 100),
            fn (): bool => false,
            $quota,
            '7d'
        );
    }

    private function pdfUpload(string $name = 'Sözleşme.pdf', int $pages = 3): UploadedFile
    {
        $path = TestPdf::create($this->root . '/temporary/src-' . bin2hex(random_bytes(3)) . '.pdf', $pages);

        return UploadedFile::fromPath($path, $name, 'application/pdf');
    }

    public function testUploadStoresOriginalWithHashExpiryAndAudit(): void
    {
        $upload = $this->pdfUpload();
        $expectedHash = hash_file('sha256', $upload->tmpPath);

        $document = $this->service()->upload($upload, $this->owner);

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $document->publicId);
        self::assertSame('Sözleşme.pdf', $document->originalName);

        $versions = $this->service()->versions($document);
        self::assertCount(1, $versions);
        $original = $versions[0];
        self::assertSame(0, $original->versionNumber);
        self::assertSame('original.pdf', $original->filename);
        self::assertSame($expectedHash, $original->sha256);
        self::assertSame(3, $original->pageCount);
        self::assertSame($expectedHash, hash_file('sha256', $this->storage->resolve($original->storagePath)));
        // Kullanıcı dosya adı yolda kullanılmaz
        self::assertStringNotContainsString('Sözleşme', $original->storagePath);

        $expiry = $this->service()->expiry($document);
        self::assertSame('7d', $expiry['policy']);
        self::assertNotNull($expiry['expires_at']);

        $events = $this->audit->forDocument($document->publicId);
        self::assertSame('upload', $events[0]['event_type']);
        self::assertSame($expectedHash, $events[0]['output_hash']);
        self::assertTrue($this->audit->verify()['ok']);
    }

    public function testOtherOwnerCannotAccessDocument(): void
    {
        $document = $this->service()->upload($this->pdfUpload(), $this->owner);

        $this->expectException(NotFoundException::class);
        $this->service()->get($document->publicId, hash('sha256', 'owner-b'));
    }

    public function testInvalidIdsAreNotFound(): void
    {
        foreach (["../../etc/passwd", "' OR 1=1 --", str_repeat('a', 33)] as $id) {
            try {
                $this->service()->get($id, $this->owner);
                self::fail('Expected not found');
            } catch (NotFoundException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testQuotaIsEnforced(): void
    {
        $upload = $this->pdfUpload();

        try {
            $this->service(quota: 100)->upload($upload, $this->owner);
            self::fail('Expected quota error');
        } catch (ValidationException $e) {
            self::assertSame('upload.quota_exceeded', $e->messageKey());
        }

        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM documents'));
    }

    public function testInvalidUploadLeavesNoFilesOrRows(): void
    {
        $path = $this->root . '/temporary/bad.pdf';
        file_put_contents($path, '%PDF-1.4 nope');

        try {
            $this->service()->upload(UploadedFile::fromPath($path, 'bad.pdf'), $this->owner);
            self::fail('Expected validation error');
        } catch (ValidationException) {
        }

        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM documents'));
        self::assertSame([], glob($this->root . '/documents/*') ?: []);
    }

    public function testDeleteRemovesFilesAndRowsButKeepsAudit(): void
    {
        $document = $this->service()->upload($this->pdfUpload(), $this->owner);
        $path = $this->storage->resolve($this->service()->versions($document)[0]->storagePath);

        $this->service()->delete($document);

        self::assertFileDoesNotExist($path);
        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM documents'));
        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM document_versions'));
        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM file_expiry'));

        $events = array_column($this->audit->forDocument($document->publicId), 'event_type');
        self::assertSame(['delete', 'upload'], $events);
        self::assertTrue($this->audit->verify()['ok']);
    }

    public function testExpiryChange(): void
    {
        $document = $this->service()->upload($this->pdfUpload(), $this->owner, 'never');
        self::assertNull($this->service()->expiry($document)['expires_at']);

        $this->service()->setExpiry($document, '1d');
        $expiry = $this->service()->expiry($document);
        self::assertSame('1d', $expiry['policy']);
        self::assertEqualsWithDelta(time() + 86400, strtotime($expiry['expires_at'] . ' UTC'), 5);

        $this->expectException(ValidationException::class);
        $this->service()->setExpiry($document, '999d');
    }

    public function testAuditChainDetectsTampering(): void
    {
        $this->service()->upload($this->pdfUpload(), $this->owner);
        $this->service()->upload($this->pdfUpload('ikinci.pdf'), $this->owner);
        self::assertSame(2, $this->audit->verify()['checked']);

        // Bir kaydın sonradan değiştirilmesi
        self::db()->execute("UPDATE audit_events SET output_hash = REPEAT('f', 64) ORDER BY id LIMIT 1");
        $result = $this->audit->verify();

        self::assertFalse($result['ok']);
        self::assertSame('event_hash', $result['reason']);
    }

    public function testAuditChainDetectsDeletion(): void
    {
        $this->service()->upload($this->pdfUpload(), $this->owner);
        $this->service()->upload($this->pdfUpload('ikinci.pdf'), $this->owner);
        $this->service()->upload($this->pdfUpload('ucuncu.pdf'), $this->owner);

        // Ortadaki kaydın silinmesi
        $middle = (int) self::db()->scalar('SELECT id FROM audit_events ORDER BY id LIMIT 1 OFFSET 1');
        self::db()->execute('DELETE FROM audit_events WHERE id = ?', [$middle]);

        $result = $this->audit->verify();
        self::assertFalse($result['ok']);
        self::assertSame('prev_hash', $result['reason']);
    }
}
