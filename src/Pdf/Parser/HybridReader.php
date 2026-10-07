<?php

declare(strict_types=1);

namespace App\Pdf\Parser;

use setasign\Fpdi\PdfParser\CrossReference\ReaderInterface;

/**
 * "Hybrid" dosyalar (ör. Microsoft Word çıktıları): klasik xref tablosu + trailer'daki /XRefStm
 * ile işaret edilen cross-reference stream. Önce tablo, sonra stream aranır (ISO 32000-1 §7.5.8.4).
 */
final class HybridReader implements ReaderInterface
{
    public function __construct(
        private readonly ReaderInterface $table,
        private readonly XrefStreamReader $stream,
    ) {
    }

    public function getOffsetFor($objectNumber)
    {
        $offset = $this->table->getOffsetFor($objectNumber);

        return $offset !== false ? $offset : $this->stream->getOffsetFor($objectNumber);
    }

    /**
     * @return array{int, int}|null
     */
    public function getCompressedLocation(int $objectNumber): ?array
    {
        return $this->table->getOffsetFor($objectNumber) !== false ? null : $this->stream->getCompressedLocation($objectNumber);
    }

    public function getTrailer()
    {
        return $this->table->getTrailer();
    }
}
