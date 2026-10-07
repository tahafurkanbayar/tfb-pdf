<?php

declare(strict_types=1);

namespace App\Domain;

final class Document
{
    public const SOURCE_PDF = 'upload_pdf';
    public const SOURCE_OFFICE = 'upload_office';
    public const SOURCE_GENERATED = 'generated';

    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly string $ownerHash,
        public readonly string $originalName,
        public readonly string $sourceType,
        public readonly string $mimeType,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['public_id'],
            (string) $row['owner_hash'],
            (string) $row['original_name'],
            (string) $row['source_type'],
            (string) $row['mime_type'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
