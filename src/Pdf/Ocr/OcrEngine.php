<?php

declare(strict_types=1);

namespace App\Pdf\Ocr;

use App\Exceptions\ProcessingException;
use App\Exceptions\ToolUnavailableException;
use App\Exceptions\ValidationException;
use App\Pdf\PdfService;
use App\Tools\ProcessRunner;

/**
 * Taranmış PDF'ler için OCR (spec §12). Tesseract ve bir sayfa görüntüleyici (Ghostscript veya pdftoppm)
 * gerektirir; yoksa özellik kapalıdır.
 *
 * Akış: sayfa → 300 DPI PNG → tesseract (pdf çıktısı: görüntü + görünmez metin katmanı)
 * → sayfa PDF'leri birleştirilir. Sonuç %100 doğru olmayabilir.
 */
final class OcrEngine
{
    public const DPI = 300;

    public const MAX_PAGES = 50;

    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly PdfService $pdf,
        private readonly ?string $tesseract,
        private readonly ?string $ghostscript,
        private readonly ?string $pdftoppm,
        private readonly string $languages,
        private readonly int $timeout,
    ) {
    }

    public function available(): bool
    {
        return $this->tesseract !== null && ($this->ghostscript !== null || $this->pdftoppm !== null) && ProcessRunner::available();
    }

    /**
     * Tesseract'ta kurulu diller ile yapılandırılan dillerin kesişimi ("tur+eng").
     */
    public function languages(): string
    {
        $wanted = array_filter(array_map('trim', explode('+', $this->languages)));
        $result = $this->runner->run([(string) $this->tesseract, '--list-langs'], 20);
        $installed = array_filter(array_map('trim', preg_split('/\r?\n/', $result->stdout . "\n" . $result->stderr) ?: []), static fn (string $l): bool => (bool) preg_match('/^[a-z_]{3,10}$/i', $l));

        $usable = array_values(array_intersect($wanted, $installed));
        if ($usable === []) {
            $usable = in_array('eng', $installed, true) ? ['eng'] : array_slice(array_values($installed), 0, 1);
        }
        if ($usable === []) {
            throw new ToolUnavailableException('No tesseract language data installed', 'errors.tool_unavailable');
        }

        return implode('+', $usable);
    }

    /**
     * @return array{pages: int, languages: string}
     */
    public function run(string $input, string $output, int $pageCount, string $workDir): array
    {
        if (!$this->available()) {
            throw new ToolUnavailableException('OCR tools missing', 'errors.tool_unavailable');
        }
        if ($pageCount > self::MAX_PAGES) {
            throw new ValidationException('Too many pages for OCR', 'ocr.too_many_pages', ['max' => self::MAX_PAGES]);
        }

        $languages = $this->languages();
        $pagePdfs = [];
        for ($page = 1; $page <= $pageCount; $page++) {
            $png = $this->rasterize($input, $page, $workDir);
            $base = $workDir . '/ocr-' . $page;
            $result = $this->runner->run([(string) $this->tesseract, $png, $base, '-l', $languages, '--dpi', (string) self::DPI, 'pdf'], $this->timeout);
            @unlink($png);
            if (!$result->successful() || !is_file($base . '.pdf')) {
                throw new ProcessingException('Tesseract failed on page ' . $page . ($result->timedOut ? ' (timeout)' : ''));
            }
            $pagePdfs[] = $base . '.pdf';
        }

        try {
            if (count($pagePdfs) === 1) {
                copy($pagePdfs[0], $output);
            } else {
                $this->pdf->merge($pagePdfs, $output);
            }
        } finally {
            array_map('unlink', array_filter($pagePdfs, 'is_file'));
        }

        return ['pages' => $pageCount, 'languages' => $languages];
    }

    /**
     * @return list<string>
     */
    public function rasterizeCommand(string $input, int $page, string $png): array
    {
        if ($this->ghostscript !== null) {
            return [
                $this->ghostscript, '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
                '-sDEVICE=pnggray', '-r' . self::DPI,
                '-dFirstPage=' . $page, '-dLastPage=' . $page,
                '-sOutputFile=' . $png, $input,
            ];
        }

        // pdftoppm çıktı adına "-<sayfa>" ekleyebildiği için -singlefile kullanılır
        return [(string) $this->pdftoppm, '-r', (string) self::DPI, '-gray', '-png', '-singlefile', '-f', (string) $page, '-l', (string) $page, $input, substr($png, 0, -4)];
    }

    private function rasterize(string $input, int $page, string $workDir): string
    {
        $png = $workDir . '/ocr-page-' . $page . '.png';
        $result = $this->runner->run($this->rasterizeCommand($input, $page, $png), $this->timeout);
        if (!$result->successful() || !is_file($png)) {
            throw new ProcessingException('Rasterization failed on page ' . $page);
        }

        return $png;
    }
}
