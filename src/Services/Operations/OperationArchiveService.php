<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Domain\Document;
use App\Domain\DocumentVersion;
use App\Exceptions\NotFoundException;
use App\Exceptions\StorageException;
use App\Exceptions\ToolUnavailableException;
use App\Repositories\DocumentRepository;
use App\Repositories\OperationRepository;
use App\Repositories\VersionRepository;
use App\Services\AuditService;
use App\Services\StorageService;
use App\Support\FilenameSanitizer;

/**
 * Bir işlemin tüm çıktılarını (ör. bölmede oluşan dosyalar) tek ZIP olarak sunar.
 * ext-zip yoksa özellik kapalıdır; dosyalar tek tek indirilebilir.
 */
final class OperationArchiveService
{
    public function __construct(
        private readonly OperationRepository $operations,
        private readonly DocumentRepository $documents,
        private readonly VersionRepository $versions,
        private readonly StorageService $storage,
        private readonly AuditService $audit,
    ) {
    }

    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /**
     * @return array{path: string, name: string} Geçici ZIP (gönderildikten sonra silinmeli)
     */
    public function build(string $operationPublicId, string $ownerHash): array
    {
        if (!self::available()) {
            throw new ToolUnavailableException('ext-zip missing', 'split.zip_unavailable');
        }

        $operation = $this->operations->findForOwner($operationPublicId, $ownerHash);
        $document = $operation !== null && $operation['document_id'] !== null ? $this->documents->findById((int) $operation['document_id']) : null;
        if ($operation === null || $document === null) {
            throw new NotFoundException('Operation not found');
        }

        $versions = $this->versions->listForOperation((int) $operation['id']);
        if ($versions === []) {
            throw new NotFoundException('Operation has no outputs');
        }

        $base = FilenameSanitizer::basename($document->originalName);
        $zipPath = $this->storage->createTempDirectory() . '/outputs.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new StorageException('Cannot create zip');
        }

        foreach ($versions as $version) {
            $zip->addFile($this->storage->resolve($version->storagePath), $this->entryName($base, $version));
        }
        $zip->close();

        $this->audit->record(
            AuditService::DOWNLOAD,
            ownerHash: $ownerHash,
            documentPublicId: $document->publicId,
            operationPublicId: $operationPublicId,
            metadata: ['zip' => true, 'versions' => array_map(static fn (DocumentVersion $v): int => $v->versionNumber, $versions)]
        );

        return ['path' => $zipPath, 'name' => FilenameSanitizer::clean($base . '-' . $operation['type'] . '.zip')];
    }

    private function entryName(string $base, DocumentVersion $version): string
    {
        $label = $version->label !== null ? '-p' . preg_replace('/[^0-9\-]+/', '_', $version->label) : '';

        return FilenameSanitizer::clean(sprintf('%s-v%03d%s.pdf', $base, $version->versionNumber, $label));
    }
}
