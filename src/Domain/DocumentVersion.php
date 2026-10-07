<?php

declare(strict_types=1);

namespace App\Domain;

final class DocumentVersion
{
    public function __construct(
        public readonly int $id,
        public readonly int $documentId,
        public readonly int $versionNumber,
        public readonly ?int $operationId,
        public readonly string $filename,
        public readonly string $storagePath,
        public readonly string $mimeType,
        public readonly int $fileSize,
        public readonly string $sha256,
        public readonly ?int $pageCount,
        public readonly ?string $label,
        public readonly string $createdAt,
        public readonly ?string $operationType = null,
    ) {
    }

    public function isOriginal(): bool
    {
        return $this->versionNumber === 0;
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['document_id'],
            (int) $row['version_number'],
            $row['operation_id'] !== null ? (int) $row['operation_id'] : null,
            (string) $row['filename'],
            (string) $row['storage_path'],
            (string) $row['mime_type'],
            (int) $row['file_size'],
            (string) $row['sha256'],
            $row['page_count'] !== null ? (int) $row['page_count'] : null,
            $row['label'] !== null ? (string) $row['label'] : null,
            (string) $row['created_at'],
            isset($row['operation_type']) ? (string) $row['operation_type'] : null,
        );
    }
}
