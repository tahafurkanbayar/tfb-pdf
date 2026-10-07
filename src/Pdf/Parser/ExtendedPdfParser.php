<?php

declare(strict_types=1);

namespace App\Pdf\Parser;

use setasign\Fpdi\PdfParser\PdfParser;

/**
 * FPDI PdfParser'ı; cross-reference okuyucusu olarak ExtendedCrossReference kullanır.
 */
final class ExtendedPdfParser extends PdfParser
{
    public function getCrossReference()
    {
        if ($this->xref === null) {
            $this->xref = new ExtendedCrossReference($this, $this->resolveFileHeader());
        }

        return $this->xref;
    }
}
