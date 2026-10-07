<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Pdf\Parser\ExtendedPdfParser;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Projenin PDF yazıcısı: tFPDF (UTF-8, Türkçe karakter) + FPDI (sayfa içe aktarma)
 * + xref stream / object stream desteği (ExtendedPdfParser).
 *
 * Fontlar resources/fonts/unifont altındadır (DejaVu Sans). tFPDF font ölçü önbelleğini
 * aynı dizine yazar; vendor/ dizinine dokunulmaz.
 */
class Fpdi extends \setasign\Fpdi\Tfpdf\Fpdi
{
    public const FONT = 'DejaVu';

    public function __construct(string $orientation = 'P', string $unit = 'pt', string|array $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);

        $this->fontpath = APP_ROOT . '/resources/fonts/';
        $this->SetAutoPageBreak(false);
        $this->SetCreator('tfb-pdf', true);
        $this->SetCompression(true);
    }

    public function useUnicodeFont(float $size = 12, bool $bold = false): void
    {
        $style = $bold ? 'B' : '';
        if (!isset($this->fonts[strtolower(self::FONT) . $style])) {
            $this->AddFont(self::FONT, $style, $bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf', true);
        }
        $this->SetFont(self::FONT, $style, $size);
    }

    protected function getPdfParserInstance(StreamReader $streamReader, array $parserParams = [])
    {
        return new ExtendedPdfParser($streamReader);
    }
}
