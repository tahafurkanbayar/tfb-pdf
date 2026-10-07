<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Core\Database;
use App\Core\Logger;
use App\Domain\Document;
use App\Domain\DocumentVersion;
use App\Exceptions\AppException;
use App\Exceptions\ProcessingException;
use App\Repositories\DocumentRepository;
use App\Repositories\ExpiryRepository;
use App\Repositories\OperationRepository;
use App\Repositories\VersionRepository;
use App\Security\Hmac;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\ExpiryPolicy;
use App\Services\HashService;
use App\Services\StorageService;
use App\Support\FilenameSanitizer;

/**
 * Tüm PDF işlemlerinin ortak akışı (spec §11, §44):
 *
 *   operation kaydı (processing)
 *   → işlemci geçici dizinde çalışır (orijinal ve önceki sürümler yalnızca okunur)
 *   → SHA-256
 *   → TRANSACTION { belge satırı kilidi → sürüm no → dosyayı versions/'a taşı → version kaydı
 *                   → operation completed → audit }
 *   → hata: rollback, taşınan dosyalar silinir, operation failed + audit (failed)
 */
final class OperationService
{
    public function __construct(
        private readonly Database $db,
        private readonly DocumentRepository $documents,
        private readonly VersionRepository $versions,
        private readonly OperationRepository $operations,
        private readonly ExpiryRepository $expiry,
        private readonly StorageService $storage,
        private readonly HashService $hash,
        private readonly AuditService $audit,
        private readonly DocumentService $documentService,
        private readonly Logger $logger,
        private readonly string $defaultExpiry,
    ) {
    }

    /**
     * @param list<array{Document, DocumentVersion}> $inputs
     * @param array<string, mixed> $params Kayda geçecek (hassas olmayan) parametreler
     * @param \Closure(string, list<string>): ProcessResult $processor (geçici dizin, girdi dosya yolları)
     * @param string|null $newDocumentName Doluysa çıktılar yeni bir belge olarak kaydedilir (birleştirme)
     */
    public function run(
        string $type,
        string $ownerHash,
        array $inputs,
        array $params,
        \Closure $processor,
        ?string $newDocumentName = null,
    ): OperationResult {
        if ($inputs === []) {
            throw new ProcessingException('Operation without inputs');
        }

        $started = microtime(true);
        $operationPublicId = Hmac::publicId();
        $targetDocument = $newDocumentName === null ? $inputs[0][0] : null;
        $inputHashes = array_map(static fn (array $i): string => $i[1]->sha256, $inputs);

        $operationId = $this->operations->create(
            $operationPublicId,
            $targetDocument?->id,
            $ownerHash,
            $type,
            $params,
            array_map(static fn (array $i): int => $i[1]->id, $inputs)
        );

        $tmpDir = $this->storage->createTempDirectory();
        $moved = [];

        try {
            $inputPaths = array_map(fn (array $i): string => $this->inputPath($i[1]), $inputs);

            @set_time_limit(300);
            $result = $processor($tmpDir, $inputPaths);

            if (!$result->changed || $result->outputs === []) {
                return $this->finishWithoutChange($operationId, $operationPublicId, $type, $ownerHash, $targetDocument, $inputHashes, $result, $started);
            }

            $outputs = [];
            $totalSize = 0;
            foreach ($result->outputs as $output) {
                if (!is_file($output->path) || filesize($output->path) === 0) {
                    throw new ProcessingException('Processor produced no file');
                }
                $size = (int) filesize($output->path);
                $totalSize += $size;
                $outputs[] = [$output, $this->hash->file($output->path), $size];
            }
            $this->documentService->assertQuota($ownerHash, $totalSize);

            [$document, $versions] = $this->db->transaction(function () use (
                $targetDocument, $newDocumentName, $ownerHash, $outputs, $operationId, $operationPublicId,
                $type, $inputHashes, $result, $started, &$moved
            ): array {
                $document = $targetDocument ?? $this->createGeneratedDocument($ownerHash, (string) $newDocumentName);
                $this->documents->lockForUpdate($document->id);
                $number = $this->versions->nextNumber($document->id);

                $created = [];
                foreach ($outputs as [$output, $sha256, $size]) {
                    $relative = StorageService::versionPath($document->publicId, $number, $output->extension);
                    $this->storage->moveIntoPlace($output->path, $relative);
                    $moved[] = $relative;

                    $created[] = $this->versions->create(
                        $document->id,
                        $number,
                        $operationId,
                        StorageService::versionFilename($number, $output->extension),
                        $relative,
                        $output->mimeType,
                        $size,
                        $sha256,
                        $output->pageCount,
                        $output->label
                    );
                    $number++;
                }

                $this->documents->touch($document->id);
                $this->operations->finish(
                    $operationId,
                    OperationRepository::STATUS_COMPLETED,
                    $document->id,
                    $result->meta + ['warnings' => $result->warnings, 'outputs' => count($created)],
                    $result->engine,
                    self::elapsed($started)
                );
                $this->audit->record(
                    $type,
                    AuditService::STATUS_SUCCESS,
                    ownerHash: $ownerHash,
                    documentPublicId: $document->publicId,
                    operationPublicId: $operationPublicId,
                    inputHash: $inputHashes[0] ?? null,
                    outputHash: $created[0]->sha256,
                    metadata: [
                        'engine' => $result->engine,
                        'inputs' => $inputHashes,
                        'outputs' => array_map(static fn (DocumentVersion $v): array => ['version' => $v->versionNumber, 'sha256' => $v->sha256], $created),
                    ] + $result->meta
                );

                return [$document, $created];
            });

            return new OperationResult($operationPublicId, $type, OperationRepository::STATUS_COMPLETED, $document, $versions, $result->warnings, $result->meta);
        } catch (\Throwable $e) {
            foreach ($moved as $relative) {
                try {
                    $this->storage->delete($relative);
                } catch (\Throwable) {
                    // temizlik görevi yetim dosyaları toplar
                }
            }
            $this->recordFailure($operationId, $operationPublicId, $type, $ownerHash, $targetDocument, $inputHashes, $e, $started);
            throw $e;
        } finally {
            $this->storage->deleteTempDirectory($tmpDir);
        }
    }

    private function inputPath(DocumentVersion $version): string
    {
        $path = $this->storage->resolve($version->storagePath);
        if (!is_file($path)) {
            throw new ProcessingException('Input file missing for version ' . $version->id);
        }
        // Girdi bütünlüğü: dosya kaydedildiğinden beri değişmemiş olmalı
        if (!$this->hash->verify($path, $version->sha256)) {
            throw new ProcessingException('Input hash mismatch for version ' . $version->id, 'operations.integrity_failed');
        }

        return $path;
    }

    private function createGeneratedDocument(string $ownerHash, string $name): Document
    {
        $document = $this->documents->create(
            Hmac::publicId(),
            $ownerHash,
            FilenameSanitizer::clean($name, 'document.pdf'),
            Document::SOURCE_GENERATED,
            'application/pdf'
        );
        $this->expiry->set($document->id, $this->defaultExpiry, ExpiryPolicy::expiresAt($this->defaultExpiry, time()));

        return $document;
    }

    /**
     * @param list<string> $inputHashes
     */
    private function finishWithoutChange(int $operationId, string $operationPublicId, string $type, string $ownerHash, ?Document $document, array $inputHashes, ProcessResult $result, float $started): OperationResult
    {
        $this->db->transaction(function () use ($operationId, $operationPublicId, $type, $ownerHash, $document, $inputHashes, $result, $started): void {
            $this->operations->finish($operationId, OperationRepository::STATUS_NO_CHANGE, null, $result->meta + ['warnings' => $result->warnings], $result->engine, self::elapsed($started));
            $this->audit->record(
                $type,
                AuditService::STATUS_NO_CHANGE,
                ownerHash: $ownerHash,
                documentPublicId: $document?->publicId,
                operationPublicId: $operationPublicId,
                inputHash: $inputHashes[0] ?? null,
                metadata: ['engine' => $result->engine] + $result->meta
            );
        });

        return new OperationResult($operationPublicId, $type, OperationRepository::STATUS_NO_CHANGE, $document, [], $result->warnings, $result->meta);
    }

    /**
     * @param list<string> $inputHashes
     */
    private function recordFailure(int $operationId, string $operationPublicId, string $type, string $ownerHash, ?Document $document, array $inputHashes, \Throwable $e, float $started): void
    {
        $code = $e instanceof AppException ? $e->category()->value . ':' . $e->messageKey() : 'unexpected';

        try {
            $this->db->transaction(function () use ($operationId, $operationPublicId, $type, $ownerHash, $document, $inputHashes, $code, $started): void {
                $this->operations->finish($operationId, OperationRepository::STATUS_FAILED, null, [], null, self::elapsed($started), $code);
                $this->audit->record(
                    $type,
                    AuditService::STATUS_FAILED,
                    ownerHash: $ownerHash,
                    documentPublicId: $document?->publicId,
                    operationPublicId: $operationPublicId,
                    inputHash: $inputHashes[0] ?? null,
                    errorMessage: $code
                );
            });
        } catch (\Throwable $logError) {
            $this->logger->error('Could not record operation failure', ['exception' => $logError, 'operation' => $operationPublicId]);
        }
    }

    private static function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
