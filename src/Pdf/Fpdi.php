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

    // --- Saydamlık (ExtGState) ---

    /** @var array<int, array{parms: array<string, string>, n?: int}> */
    protected array $extGStates = [];

    /**
     * Sonraki çizimler için opaklık (0..1). Doldurma ve çizgi için birlikte uygulanır.
     */
    public function setAlpha(float $alpha): void
    {
        $alpha = max(0.0, min(1.0, $alpha));
        $key = sprintf('%.3F', $alpha);
        foreach ($this->extGStates as $i => $state) {
            if ($state['parms']['ca'] === $key) {
                $this->_out(sprintf('/GS%d gs', $i));

                return;
            }
        }

        $i = count($this->extGStates) + 1;
        $this->extGStates[$i] = ['parms' => ['ca' => $key, 'CA' => $key, 'BM' => '/Normal']];
        $this->_out(sprintf('/GS%d gs', $i));
    }

    protected function _putresources()
    {
        foreach ($this->extGStates as $i => $state) {
            $this->_newobj();
            $this->extGStates[$i]['n'] = $this->n;
            $this->_put('<</Type /ExtGState');
            foreach ($state['parms'] as $key => $value) {
                $this->_put('/' . $key . ' ' . $value);
            }
            $this->_put('>>');
            $this->_put('endobj');
        }

        parent::_putresources();
    }

    protected function _putresourcedict()
    {
        parent::_putresourcedict();

        if ($this->extGStates !== []) {
            $this->_put('/ExtGState <<');
            foreach ($this->extGStates as $i => $state) {
                $this->_put('/GS' . $i . ' ' . $state['n'] . ' 0 R');
            }
            $this->_put('>>');
        }
    }

    protected function _enddoc()
    {
        // Saydamlık PDF 1.4 gerektirir
        if ($this->extGStates !== [] && version_compare((string) $this->PDFVersion, '1.4', '<')) {
            $this->PDFVersion = '1.4';
        }

        parent::_enddoc();
    }

    // --- Grafik durumu ve döndürme ---

    /**
     * Grafik durumunu kaydeder (q). Döndürme / saydamlık sonrası restoreState() ile geri alınır.
     */
    public function saveState(): void
    {
        $this->_out('q');
    }

    public function restoreState(): void
    {
        $this->_out('Q');
    }

    /**
     * Sonraki çizimleri (x, y) noktası etrafında saat yönünün TERSİNE $degrees derece döndürür.
     * Koordinatlar FPDF birimindedir (sol üst köşe orijinli). saveState() içinde kullanılmalıdır.
     */
    public function rotateAround(float $degrees, float $x, float $y): void
    {
        $angle = deg2rad($degrees);
        $c = cos($angle);
        $s = sin($angle);
        $cx = $x * $this->k;
        $cy = ($this->h - $y) * $this->k;
        $this->_out(sprintf('%.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
    }
}
