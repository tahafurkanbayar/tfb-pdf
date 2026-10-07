<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Core\Url;
use App\Domain\DocumentVersion;
use App\Services\Operations\OperationResult;

final class OperationPresenter
{
    public function __construct(private readonly Url $url)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function result(OperationResult $result): array
    {
        $document = $result->document;
        $changed = $result->status === 'completed';

        return [
            'operation' => [
                'id' => $result->operationId,
                'type' => $result->type,
                'status' => $result->status,
            ],
            'message' => $changed ? __('operations.success.' . $result->type) : __('operations.no_change.' . $result->type),
            'changed' => $changed,
            'document' => $document === null ? null : [
                'id' => $document->publicId,
                'name' => $document->originalName,
                'url' => $this->url->page('/documents/' . $document->publicId),
            ],
            'outputs' => $document === null ? [] : array_map(fn (DocumentVersion $v): array => [
                'version' => $v->versionNumber,
                'label' => $v->label,
                'pages' => $v->pageCount,
                'size' => $v->fileSize,
                'sha256' => $v->sha256,
                'download_url' => $this->url->to('/api/documents/' . $document->publicId . '/versions/' . $v->versionNumber . '/download'),
            ], $result->versions),
            'warnings' => array_map(static fn (string $key): string => __($key), $result->warnings),
            'meta' => $result->meta,
        ];
    }
}
