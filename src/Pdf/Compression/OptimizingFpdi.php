<?php

declare(strict_types=1);

namespace App\Pdf\Compression;

use App\Pdf\Fpdi;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;

/**
 * Kaynaktan kopyalanan her akış nesnesini StreamOptimizer'dan geçiren FPDI.
 * FpdiTrait::writePdfType kaynak nesneleri yazarken çağrılır; burada yalnızca akış nesneleri değiştirilir.
 */
final class OptimizingFpdi extends Fpdi
{
    private ?StreamOptimizer $optimizer = null;

    public function setOptimizer(StreamOptimizer $optimizer): void
    {
        $this->optimizer = $optimizer;
    }

    protected function writePdfType(PdfType $value)
    {
        if ($this->optimizer !== null && $value instanceof PdfIndirectObject && $value->value instanceof PdfStream) {
            $parser = $this->getPdfReader($this->currentReaderId)->getParser();
            $optimized = $this->optimizer->optimize($value->value, $parser);
            if ($optimized !== null) {
                $value = PdfIndirectObject::create($value->objectNumber, $value->generationNumber, $optimized);
            }
        }

        parent::writePdfType($value);
    }
}
