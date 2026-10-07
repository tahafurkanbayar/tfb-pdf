<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Core\Url;
use App\Domain\Document;
use App\Domain\DocumentVersion;
use App\Support\DateFormatter;

/**
 * Belge verilerinin JSON (API) gösterimi. Dosya sistemi yolu ASLA dışarı verilmez.
 */
final class DocumentPresenter
{
    public function __construct(private readonly Url $url)
    {
    }

    /**
     * @param list<DocumentVersion> $versions
     * @param array{policy: string, expires_at: ?string}|null $expiry
     * @return array<string, mixed>
     */
    public function document(Document $document, array $versions = [], ?array $expiry = null): array
    {
        return [
            'id' => $document->publicId,
            'name' => $document->originalName,
            'source' => $document->sourceType,
            'created_at' => DateFormatter::iso($document->createdAt),
            'updated_at' => DateFormatter::iso($document->updatedAt),
            'url' => $this->url->page('/documents/' . $document->publicId),
            'expiry' => $expiry === null ? null : ['policy' => $expiry['policy'], 'expires_at' => DateFormatter::iso($expiry['expires_at'])],
            'versions' => array_map(fn (DocumentVersion $v): array => $this->version($document, $v), $versions),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function version(Document $document, DocumentVersion $version): array
    {
        $base = '/api/documents/' . $document->publicId . '/versions/' . $version->versionNumber;

        return [
            'number' => $version->versionNumber,
            'original' => $version->isOriginal(),
            'filename' => $version->filename,
            'mime' => $version->mimeType,
            'size' => $version->fileSize,
            'sha256' => $version->sha256,
            'pages' => $version->pageCount,
            'label' => $version->label,
            'operation' => $version->operationType,
            'created_at' => DateFormatter::iso($version->createdAt),
            'download_url' => $this->url->to($base . '/download'),
            'preview_url' => $version->isPdf() ? $this->url->to($base . '/download', ['inline' => 1]) : null,
        ];
    }
}
