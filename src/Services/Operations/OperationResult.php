<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Domain\Document;
use App\Domain\DocumentVersion;

final class OperationResult
{
    /**
     * @param list<DocumentVersion> $versions
     * @param list<string> $warnings
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $operationId,
        public readonly string $type,
        public readonly string $status,
        public readonly ?Document $document,
        public readonly array $versions,
        public readonly array $warnings,
        public readonly array $meta,
    ) {
    }
}
