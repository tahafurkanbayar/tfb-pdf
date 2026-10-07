<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Domain\DocumentVersion;

final class VersionRepository
{
    private const SELECT = 'SELECT v.*, o.type AS operation_type FROM document_versions v LEFT JOIN operations o ON o.id = v.operation_id';

    public function __construct(private readonly Database $db)
    {
    }

    public function create(
        int $documentId,
        int $versionNumber,
        ?int $operationId,
        string $filename,
        string $storagePath,
        string $mimeType,
        int $fileSize,
        string $sha256,
        ?int $pageCount,
        ?string $label = null,
    ): DocumentVersion {
        $now = Database::now();
        $id = $this->db->insert('document_versions', [
            'document_id' => $documentId,
            'version_number' => $versionNumber,
            'operation_id' => $operationId,
            'filename' => $filename,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'sha256' => $sha256,
            'page_count' => $pageCount,
            'label' => $label,
            'created_at' => $now,
        ]);

        return new DocumentVersion($id, $documentId, $versionNumber, $operationId, $filename, $storagePath, $mimeType, $fileSize, $sha256, $pageCount, $label, $now);
    }

    /**
     * Sıradaki sürüm numarası. Çağıran, belge satırını FOR UPDATE ile kilitlemiş olmalı.
     */
    public function nextNumber(int $documentId): int
    {
        return (int) $this->db->scalar('SELECT COALESCE(MAX(version_number), 0) + 1 FROM document_versions WHERE document_id = ?', [$documentId]);
    }

    public function find(int $documentId, int $versionNumber): ?DocumentVersion
    {
        $row = $this->db->first(self::SELECT . ' WHERE v.document_id = ? AND v.version_number = ?', [$documentId, $versionNumber]);

        return $row === null ? null : DocumentVersion::fromRow($row);
    }

    public function findById(int $id): ?DocumentVersion
    {
        $row = $this->db->first(self::SELECT . ' WHERE v.id = ?', [$id]);

        return $row === null ? null : DocumentVersion::fromRow($row);
    }

    /**
     * @return list<DocumentVersion>
     */
    public function listForDocument(int $documentId): array
    {
        return array_map(
            DocumentVersion::fromRow(...),
            $this->db->select(self::SELECT . ' WHERE v.document_id = ? ORDER BY v.version_number', [$documentId])
        );
    }

    public function latest(int $documentId): ?DocumentVersion
    {
        $row = $this->db->first(self::SELECT . ' WHERE v.document_id = ? ORDER BY v.version_number DESC LIMIT 1', [$documentId]);

        return $row === null ? null : DocumentVersion::fromRow($row);
    }

    /**
     * @return list<DocumentVersion>
     */
    public function listForOperation(int $operationId): array
    {
        return array_map(
            DocumentVersion::fromRow(...),
            $this->db->select(self::SELECT . ' WHERE v.operation_id = ? ORDER BY v.version_number', [$operationId])
        );
    }
}
