<?php

declare(strict_types=1);

namespace App\Services\Operations;

/**
 * Bir PDF işleminin geçici dizinde ürettiği tek dosya.
 */
final class OperationOutput
{
    public function __construct(
        public readonly string $path,
        public readonly int $pageCount,
        public readonly ?string $label = null,
        public readonly string $extension = 'pdf',
        public readonly string $mimeType = 'application/pdf',
    ) {
    }
}
