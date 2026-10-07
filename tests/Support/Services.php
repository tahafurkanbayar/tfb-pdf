<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Core\Logger;
use App\Http\UploadedFile;
use App\Domain\Document;
use App\Pdf\Compression\Compressor;
use App\Pdf\PdfInspector;
use App\Tools\ProcessRunner;
use App\Pdf\PdfService;
use App\Pdf\Redaction\Redactor;
use App\Repositories\DocumentRepository;
use App\Repositories\ExpiryRepository;
use App\Repositories\OperationRepository;
use App\Repositories\VersionRepository;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\HashService;
use App\Services\Operations\OperationService;
use App\Services\Operations\PdfToolService;
use App\Services\StorageService;
use App\Services\Upload\UploadValidator;

/**
 * Entegrasyon testleri için gerçek servis grafiği: test veritabanı + geçici storage.
 */
final class Services
{
    public readonly StorageService $storage;
    public readonly AuditService $audit;
    public readonly DocumentService $documents;
    public readonly OperationService $operations;
    public readonly PdfToolService $tools;
    public readonly PdfService $pdf;

    public function __construct(public readonly Database $db, public readonly string $root, int $quota = 0)
    {
        $this->storage = new StorageService($root);
        $this->storage->ensureDirectories();
        $this->audit = new AuditService($db);
        $this->pdf = new PdfService(500);
        $inspector = new PdfInspector();

        $this->documents = new DocumentService(
            $db,
            new DocumentRepository($db),
            new VersionRepository($db),
            new OperationRepository($db),
            new ExpiryRepository($db),
            $this->storage,
            new HashService(),
            $this->audit,
            new UploadValidator($inspector, 50_000_000, 500),
            fn (): bool => false,
            $quota,
            '7d'
        );

        $this->operations = new OperationService(
            $db,
            new DocumentRepository($db),
            new VersionRepository($db),
            new OperationRepository($db),
            new ExpiryRepository($db),
            $this->storage,
            new HashService(),
            $this->audit,
            $this->documents,
            new Logger($root . '/logs', 'error'),
            '7d'
        );

        $this->tools = new PdfToolService($this->operations, $this->documents, $this->pdf, $inspector, 20, new Compressor(new ProcessRunner(), null, 60), new Redactor(new ProcessRunner(), null, 60));
    }

    /**
     * Gerçek bir PDF üretip yükler.
     *
     * @param list<array{float, float}>|null $sizes
     */
    public function uploadPdf(string $owner, int $pages = 2, string $name = 'belge.pdf', ?array $sizes = null): Document
    {
        @mkdir($this->root . '/fixtures');
        $path = TestPdf::create($this->root . '/fixtures/src-' . bin2hex(random_bytes(4)) . '.pdf', $pages, $sizes);

        return $this->documents->upload(UploadedFile::fromPath($path, $name, 'application/pdf'), $owner);
    }

    public function versionPath(Document $document, int $number): string
    {
        return $this->storage->resolve($this->documents->version($document, $number)->storagePath);
    }
}
