<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Document;
use App\Domain\DocumentVersion;
use App\Exceptions\StorageException;
use App\Exceptions\ToolUnavailableException;
use App\Repositories\DocumentRepository;
use App\Repositories\OperationRepository;
use App\Support\DateFormatter;
use App\Support\FilenameSanitizer;

/**
 * Kullanıcının kendi verilerini dışa aktarması (spec §24): PDF'ler, tüm sürümler, metadata,
 * işlem geçmişi ve audit log tek ZIP içinde. ZIP içindeki her dosyanın SHA-256'sı manifest.json'da.
 */
final class ExportService
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly OperationRepository $operations,
        private readonly DocumentService $documentService,
        private readonly AuditService $audit,
        private readonly StorageService $storage,
        private readonly string $appName,
    ) {
    }

    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /**
     * @return array{path: string, name: string} Geçici ZIP (gönderildikten sonra silinmeli)
     */
    public function exportDocument(Document $document): array
    {
        return $this->build(
            [$document],
            $this->audit->forDocument($document->publicId, 10000),
            $document->ownerHash,
            FilenameSanitizer::basename(FilenameSanitizer::clean($document->originalName)) . '-export.zip',
            $document->publicId
        );
    }

    /**
     * Sahibin tüm belgeleri ve silinmiş belgeler dahil tüm audit kayıtları.
     *
     * @return array{path: string, name: string}
     */
    public function exportAll(string $ownerHash): array
    {
        $documents = [];
        $offset = 0;
        do {
            $rows = $this->documents->listForOwner($ownerHash, 200, $offset);
            foreach ($rows as $row) {
                $documents[] = Document::fromRow($row);
            }
            $offset += 200;
        } while (count($rows) === 200);

        return $this->build($documents, $this->audit->forOwner($ownerHash, 100000), $ownerHash, 'tfb-pdf-export-' . gmdate('Ymd-His') . '.zip', null);
    }

    /**
     * @param list<Document> $documents
     * @param list<array<string, mixed>> $auditEvents
     * @return array{path: string, name: string}
     */
    private function build(array $documents, array $auditEvents, string $ownerHash, string $name, ?string $documentPublicId): array
    {
        if (!self::available()) {
            throw new ToolUnavailableException('ext-zip missing', 'export.unavailable');
        }

        $dir = $this->storage->createTempDirectory();
        $zipPath = $dir . '/export.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new StorageException('Cannot create export zip');
        }

        $manifest = [];
        $add = function (string $entry, string $contents) use ($zip, &$manifest): void {
            $zip->addFromString($entry, $contents);
            $manifest[$entry] = hash('sha256', $contents);
        };

        $summary = [];
        foreach ($documents as $document) {
            $versions = $this->documentService->versions($document);
            $folder = 'documents/' . $document->publicId . '/';

            foreach ($versions as $version) {
                $path = $this->storage->resolve($version->storagePath);
                if (!is_file($path)) {
                    continue; // Bütünlük sorunları metadata'da görünür (file_present=false)
                }
                $zip->addFile($path, $folder . $version->filename);
                $manifest[$folder . $version->filename] = $version->sha256;
            }

            $metadata = [
                'id' => $document->publicId,
                'original_name' => $document->originalName,
                'source' => $document->sourceType,
                'created_at' => DateFormatter::iso($document->createdAt),
                'updated_at' => DateFormatter::iso($document->updatedAt),
                'expiry' => $this->documentService->expiry($document),
                'versions' => array_map(fn (DocumentVersion $v): array => [
                    'number' => $v->versionNumber,
                    'filename' => $v->filename,
                    'mime_type' => $v->mimeType,
                    'size' => $v->fileSize,
                    'sha256' => $v->sha256,
                    'pages' => $v->pageCount,
                    'label' => $v->label,
                    'operation' => $v->operationType,
                    'created_at' => DateFormatter::iso($v->createdAt),
                    'file_present' => is_file($this->storage->resolve($v->storagePath)),
                ], $versions),
            ];
            $add($folder . 'metadata.json', self::json($metadata));

            $operations = array_map(static fn (array $op): array => [
                'id' => $op['public_id'],
                'type' => $op['type'],
                'status' => $op['status'],
                'engine' => $op['engine'],
                'params' => json_decode((string) ($op['params'] ?? 'null'), true),
                'result' => json_decode((string) ($op['result'] ?? 'null'), true),
                'error' => $op['error_code'],
                'started_at' => DateFormatter::iso($op['started_at']),
                'finished_at' => DateFormatter::iso($op['finished_at']),
                'duration_ms' => $op['duration_ms'] !== null ? (int) $op['duration_ms'] : null,
            ], $this->operations->listForDocument($document->id));
            $add($folder . 'operations.json', self::json($operations));

            $summary[] = ['id' => $document->publicId, 'name' => $document->originalName, 'versions' => count($versions)];
        }

        $add('audit-log.json', self::json(array_map(static fn (array $e): array => [
            'event_id' => $e['event_id'],
            'document_id' => $e['document_public_id'] ?? null,
            'operation_id' => $e['operation_public_id'] ?? null,
            'event_type' => $e['event_type'],
            'status' => $e['status'],
            'actor' => $e['actor'],
            'input_hash' => $e['input_hash'],
            'output_hash' => $e['output_hash'],
            'metadata' => json_decode((string) ($e['metadata'] ?? 'null'), true),
            'error_message' => $e['error_message'],
            'timestamp' => DateFormatter::iso($e['created_at']),
        ], $auditEvents)));

        $add('documents.json', self::json($summary));
        $add('README.txt', $this->readme(count($documents)));
        $zip->addFromString('manifest.json', self::json([
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'application' => $this->appName,
            'algorithm' => 'SHA-256',
            'files' => $manifest,
        ]));

        if (!$zip->close()) {
            $this->storage->deleteTempDirectory($dir);
            throw new StorageException('Cannot finalize export zip');
        }

        $this->audit->record(
            AuditService::EXPORT,
            ownerHash: $ownerHash,
            documentPublicId: $documentPublicId,
            outputHash: hash_file('sha256', $zipPath) ?: null,
            metadata: ['documents' => count($documents), 'audit_events' => count($auditEvents), 'scope' => $documentPublicId === null ? 'all' : 'document']
        );

        return ['path' => $zipPath, 'name' => FilenameSanitizer::clean($name, 'export.zip')];
    }

    private function readme(int $documentCount): string
    {
        return implode("\n", [
            $this->appName . ' — dışa aktarma / export',
            str_repeat('=', 60),
            '',
            'TR',
            '- documents/<id>/        : belge dosyaları (original.* = yüklenen özgün dosya, vNNN.pdf = sürümler)',
            '- documents/<id>/metadata.json   : belge ve sürüm bilgileri (SHA-256 dahil)',
            '- documents/<id>/operations.json : işlem geçmişi',
            '- audit-log.json         : audit kayıtları (silinmiş belgelerinki dahil)',
            '- manifest.json          : bu ZIP içindeki her dosyanın SHA-256 özeti',
            'SHA-256 özeti yalnızca dosya bütünlüğünü kontrol etmek içindir; tek başına hukuki geçerlilik veya belge doğrulaması anlamına gelmez.',
            '',
            'EN',
            '- documents/<id>/        : document files (original.* = uploaded original, vNNN.pdf = versions)',
            '- documents/<id>/metadata.json   : document and version information (including SHA-256)',
            '- documents/<id>/operations.json : processing history',
            '- audit-log.json         : audit records (including those of deleted documents)',
            '- manifest.json          : SHA-256 digest of every file in this ZIP',
            'The SHA-256 digest is only for checking file integrity; on its own it does not imply legal validity or document verification.',
            '',
            'Belge sayısı / documents: ' . $documentCount,
            '',
        ]);
    }

    /**
     * @param array<mixed> $data
     */
    private static function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
