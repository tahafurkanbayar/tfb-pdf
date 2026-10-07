<?php

declare(strict_types=1);

namespace App\Pdf;

/**
 * Bir PDF hakkında doğrulama ve uyarılar için gereken bilgiler.
 */
final class PdfInfo
{
    /**
     * @param list<array{width: float, height: float, rotation: int}> $pages Nokta (pt) cinsinden, döndürme öncesi boyut
     */
    public function __construct(
        public readonly int $pageCount,
        public readonly string $version,
        public readonly array $pages,
        public readonly bool $hasForms,
        public readonly bool $hasSignatures,
        public readonly bool $hasEmbeddedFiles,
        public readonly bool $hasOutlines,
        public readonly bool $hasJavaScript,
        public readonly bool $isTagged,
        public readonly bool $hasMetadata,
        public readonly bool $usesCompressedXref,
    ) {
    }

    /**
     * İşlem sonrasında kaybolabilecek / değişebilecek özellikler (spec §36 uyarıları için).
     *
     * @return list<string> forms | signatures | embedded_files | outlines | javascript | tagged | metadata
     */
    public function features(): array
    {
        return array_keys(array_filter([
            'forms' => $this->hasForms,
            'signatures' => $this->hasSignatures,
            'embedded_files' => $this->hasEmbeddedFiles,
            'outlines' => $this->hasOutlines,
            'javascript' => $this->hasJavaScript,
            'tagged' => $this->isTagged,
            'metadata' => $this->hasMetadata,
        ]));
    }
}
