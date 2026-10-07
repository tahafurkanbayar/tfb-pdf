<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Domain\Document;
use App\Domain\DocumentVersion;
use App\Exceptions\NotFoundException;
use App\Exceptions\StorageException;
use App\Exceptions\ToolUnavailableException;
use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Repositories\DocumentRepository;
use App\Repositories\ExpiryRepository;
use App\Repositories\OperationRepository;
use App\Repositories\VersionRepository;
use App\Security\Hmac;
use App\Services\Upload\UploadValidator;
use App\Services\Upload\ValidatedUpload;
use App\Support\FilenameSanitizer;
use App\Support\Size;

/**
 * Belge yaşam döngüsü: yükleme, listeleme, erişim, indirme, saklama süresi, silme.
 * Controller'dan bağımsızdır; gelecekteki REST API / mobil istemci aynı servisi kullanır.
 */
final class DocumentService
{
    /**
     * @param \Closure(): bool $officeAvailable Office → PDF yeteneği (LibreOffice) mevcut mu
     */
    public function __construct(
        private readonly Database $db,
        private readonly DocumentRepository $documents,
        private readonly VersionRepository $versions,
        private readonly OperationRepository $operations,
        private readonly ExpiryRepository $expiry,
        private readonly StorageService $storage,
        private readonly HashService $hash,
        private readonly AuditService $audit,
        private readonly UploadValidator $validator,
        private readonly \Closure $officeAvailable,
        private readonly int $maxStoragePerOwner,
        private readonly string $defaultExpiry,
    ) {
    }

    /**
     * Yükle → doğrula → orijinali koru → SHA-256 → kayıt + audit (tek transaction).
     */
    public function upload(UploadedFile $file, string $ownerHash, ?string $expiryPolicy = null, bool $allowOffice = false): Document
    {
        $policy = $expiryPolicy ?? $this->defaultExpiry;
        ExpiryPolicy::assertValid($policy);

        $validated = $this->validate($file, $allowOffice);
        $this->assertQuota($ownerHash, $validated->size);

        $publicId = Hmac::publicId();
        $relative = StorageService::originalPath($publicId, $validated->extension);

        // Geçici yüklemeyi depoya al (move_uploaded_file), sonra özet
        $tmpDir = $this->storage->createTempDirectory();
        $staged = $tmpDir . '/upload';
        try {
            if (!$validated->file->moveTo($staged)) {
                throw new StorageException('Cannot move uploaded file.');
            }
            $sha256 = $this->hash->file($staged);
            $this->storage->moveIntoPlace($staged, $relative);
        } finally {
            $this->storage->deleteTempDirectory($tmpDir);
        }

        try {
            return $this->db->transaction(function () use ($publicId, $ownerHash, $validated, $relative, $sha256, $policy): Document {
                $document = $this->documents->create(
                    $publicId,
                    $ownerHash,
                    $validated->originalName,
                    $validated->isPdf() ? Document::SOURCE_PDF : Document::SOURCE_OFFICE,
                    $validated->mimeType
                );
                $this->versions->create(
                    $document->id,
                    0,
                    null,
                    'original.' . $validated->extension,
                    $relative,
                    $validated->mimeType,
                    $validated->size,
                    $sha256,
                    $validated->pdf?->pageCount
                );
                $this->expiry->set($document->id, $policy, ExpiryPolicy::expiresAt($policy, time()));

                $this->audit->record(
                    AuditService::UPLOAD,
                    ownerHash: $ownerHash,
                    documentPublicId: $publicId,
                    outputHash: $sha256,
                    metadata: [
                        'kind' => $validated->kind,
                        'size' => $validated->size,
                        'pages' => $validated->pdf?->pageCount,
                        'expiry' => $policy,
                    ]
                );

                return $document;
            });
        } catch (\Throwable $e) {
            // Kayıt oluşturulamadıysa sahipsiz dosya bırakma
            $this->storage->deleteDocumentFiles($publicId);
            throw $e;
        }
    }

    public function validate(UploadedFile $file, bool $allowOffice): ValidatedUpload
    {
        $ext = FilenameSanitizer::extension($file->clientName);
        if (in_array($ext, UploadValidator::OFFICE_EXTENSIONS, true)) {
            if (!$allowOffice) {
                throw new ValidationException('Office upload not allowed here', 'upload.unsupported_type', ['types' => 'PDF']);
            }
            if (!($this->officeAvailable)()) {
                throw new ToolUnavailableException('LibreOffice not available', 'errors.tool_unavailable');
            }

            return $this->validator->validateOffice($file);
        }

        return $this->validator->validatePdf($file);
    }

    public function assertQuota(string $ownerHash, int $additionalBytes): void
    {
        if ($this->maxStoragePerOwner <= 0) {
            return;
        }
        if ($this->documents->storageUsage($ownerHash) + $additionalBytes > $this->maxStoragePerOwner) {
            throw new ValidationException('Storage quota exceeded', 'upload.quota_exceeded', ['max' => Size::format($this->maxStoragePerOwner)]);
        }
    }

    /**
     * Sahibe ait belge; değilse "bulunamadı" (varlığı sızdırılmaz).
     */
    public function get(string $publicId, ?string $ownerHash): Document
    {
        $document = ($ownerHash !== null && preg_match('/^[a-f0-9]{32}$/', $publicId))
            ? $this->documents->findForOwner($publicId, $ownerHash)
            : null;

        if ($document === null) {
            throw new NotFoundException('Document not found or not owned');
        }

        return $document;
    }

    /**
     * @return list<DocumentVersion>
     */
    public function versions(Document $document): array
    {
        return $this->versions->listForDocument($document->id);
    }

    /**
     * Bütünlük doğrulaması: her sürümün SHA-256'sı diskten yeniden hesaplanır ve kayıtla karşılaştırılır.
     * Özet yalnızca dosyanın kaydedildiğinden beri değişip değişmediğini gösterir; hukuki doğrulama değildir.
     *
     * @return list<array{version: int, expected: string, actual: ?string, status: string}> status: ok | mismatch | missing
     */
    public function verifyIntegrity(Document $document): array
    {
        $results = [];
        foreach ($this->versions($document) as $version) {
            $path = $this->storage->resolve($version->storagePath);
            $actual = is_file($path) ? $this->hash->file($path) : null;
            $results[] = [
                'version' => $version->versionNumber,
                'expected' => $version->sha256,
                'actual' => $actual,
                'status' => $actual === null ? 'missing' : (hash_equals($version->sha256, $actual) ? 'ok' : 'mismatch'),
            ];
        }

        return $results;
    }

    /**
     * Sürümlerin kökeni: her sürüm hangi sürüm(ler)den hangi işlemle üretildi.
     *
     * @param list<DocumentVersion> $versions
     * @return array<int, array{inputs: list<int>, external: int}> version id => [aynı belgedeki kaynak sürüm no'ları, başka belgelerden girdi sayısı]
     */
    public function versionSources(Document $document, array $versions): array
    {
        $operations = [];
        foreach ($this->operations->listForDocument($document->id) as $op) {
            $operations[(int) $op['id']] = json_decode((string) ($op['input_versions'] ?? '[]'), true) ?: [];
        }

        $numbers = [];
        foreach ($versions as $v) {
            $numbers[$v->id] = $v->versionNumber;
        }

        $sources = [];
        foreach ($versions as $v) {
            if ($v->operationId === null || !isset($operations[$v->operationId])) {
                continue;
            }
            $inputs = [];
            $external = 0;
            foreach ($operations[$v->operationId] as $inputId) {
                isset($numbers[(int) $inputId]) ? $inputs[] = $numbers[(int) $inputId] : $external++;
            }
            $sources[$v->id] = ['inputs' => $inputs, 'external' => $external];
        }

        return $sources;
    }

    public function version(Document $document, int $versionNumber): DocumentVersion
    {
        $version = $this->versions->find($document->id, $versionNumber);
        if ($version === null) {
            throw new NotFoundException('Version not found');
        }

        return $version;
    }

    public function latestPdfVersion(Document $document): ?DocumentVersion
    {
        $pdfs = array_filter($this->versions($document), static fn (DocumentVersion $v): bool => $v->isPdf());

        return $pdfs === [] ? null : end($pdfs);
    }

    /**
     * İndirme için dosya yolu. Dosyanın varlığı, depo içinde olduğu ve özeti kontrol edilir.
     */
    public function pathForDownload(Document $document, DocumentVersion $version, bool $audit, string $actor = 'owner'): string
    {
        $path = $this->storage->resolve($version->storagePath);
        if (!is_file($path)) {
            throw new StorageException('Stored file is missing: version ' . $version->id);
        }

        if ($audit) {
            $this->audit->record(
                AuditService::DOWNLOAD,
                actor: $actor,
                ownerHash: $document->ownerHash,
                documentPublicId: $document->publicId,
                outputHash: $version->sha256,
                metadata: ['version' => $version->versionNumber]
            );
        }

        return $path;
    }

    public function downloadName(Document $document, DocumentVersion $version): string
    {
        $extension = FilenameSanitizer::extension($version->filename) ?: 'pdf';
        $suffix = $version->isOriginal() ? '' : sprintf('v%03d', $version->versionNumber);

        return FilenameSanitizer::downloadName($document->originalName, $suffix, $extension);
    }

    public function setExpiry(Document $document, string $policy): ?string
    {
        ExpiryPolicy::assertValid($policy);
        $expiresAt = ExpiryPolicy::expiresAt($policy, time());

        $this->db->transaction(function () use ($document, $policy, $expiresAt): void {
            $this->expiry->set($document->id, $policy, $expiresAt);
            $this->audit->record(
                AuditService::EXPIRY_CHANGED,
                ownerHash: $document->ownerHash,
                documentPublicId: $document->publicId,
                metadata: ['policy' => $policy, 'expires_at' => $expiresAt]
            );
        });

        return $expiresAt;
    }

    /**
     * @return array{policy: string, expires_at: ?string}|null
     */
    public function expiry(Document $document): ?array
    {
        return $this->expiry->get($document->id);
    }

    /**
     * Belgeyi ve tüm sürümlerini kalıcı olarak siler. Audit kaydı kalır.
     * Önce veritabanı (transaction), sonra dosyalar; dosya silme yarıda kalırsa temizlik görevi yetim dizinleri toplar.
     */
    public function delete(Document $document, string $eventType = AuditService::DELETE, string $actor = 'owner'): void
    {
        $versions = $this->versions($document);

        $this->db->transaction(function () use ($document, $versions, $eventType, $actor): void {
            $this->documents->delete($document->id);
            $this->audit->record(
                $eventType,
                actor: $actor,
                ownerHash: $document->ownerHash,
                documentPublicId: $document->publicId,
                metadata: [
                    'versions' => count($versions),
                    'hashes' => array_map(static fn (DocumentVersion $v): string => $v->sha256, $versions),
                ]
            );
        });

        $this->storage->deleteDocumentFiles($document->publicId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForOwner(?string $ownerHash, int $limit = 50, int $offset = 0): array
    {
        return $ownerHash === null ? [] : $this->documents->listForOwner($ownerHash, $limit, $offset);
    }

    public function storageUsage(?string $ownerHash): int
    {
        return $ownerHash === null ? 0 : $this->documents->storageUsage($ownerHash);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function operationsForDocument(Document $document): array
    {
        return $this->operations->listForDocument($document->id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expiringSoon(?string $ownerHash, int $withinHours = 48): array
    {
        return $ownerHash === null ? [] : $this->expiry->expiringSoon($ownerHash, gmdate('Y-m-d H:i:s', time() + $withinHours * 3600));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentOperations(?string $ownerHash, int $limit = 8): array
    {
        return $ownerHash === null ? [] : $this->operations->listForOwner($ownerHash, $limit);
    }
}
