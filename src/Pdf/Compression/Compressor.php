<?php

declare(strict_types=1);

namespace App\Pdf\Compression;

use App\Core\Logger;
use App\Exceptions\ProcessingException;
use App\Exceptions\ValidationException;
use App\Pdf\PdfInspector;
use App\Tools\ProcessRunner;

/**
 * PDF sıkıştırma (spec §12):
 *  1. Ghostscript varsa: pdfwrite + PDFSETTINGS (gelişmiş; görüntü yeniden örnekleme, font alt kümeleri).
 *  2. Yoksa veya Ghostscript başarısız olursa: PHP tabanlı optimizasyon (StreamOptimizer).
 * Sonucun boyutu döndürülür; küçülme olup olmadığına çağıran karar verir (yapılmamış sıkıştırma gösterilmez).
 */
final class Compressor
{
    public const LEVELS = ['low', 'medium', 'high'];

    private const GS_SETTINGS = ['low' => '/printer', 'medium' => '/ebook', 'high' => '/screen'];

    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly ?string $ghostscript,
        private readonly int $timeout,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * @return array{engine: string, size_before: int, size_after: int, pages: int, details: array<string, int>}
     */
    public function compress(string $input, string $output, string $level): array
    {
        if (!in_array($level, self::LEVELS, true)) {
            throw new ValidationException('Invalid level', 'compress.invalid_level');
        }

        $before = (int) filesize($input);
        $pages = (new PdfInspector())->pageCount($input);

        if ($this->ghostscript !== null) {
            try {
                $this->withGhostscript($input, $output, $level, $pages);

                return ['engine' => 'ghostscript', 'size_before' => $before, 'size_after' => (int) filesize($output), 'pages' => $pages, 'details' => []];
            } catch (\Throwable $e) {
                // Ghostscript hatası: PHP yöntemiyle devam edilir, kullanıcı işlemi yine tamamlayabilir
                $this->logger?->warning('Ghostscript compression failed, falling back to PHP', ['exception' => $e]);
                @unlink($output);
            }
        }

        $details = $this->withPhp($input, $output, $level);

        return ['engine' => 'php', 'size_before' => $before, 'size_after' => (int) filesize($output), 'pages' => $pages, 'details' => $details];
    }

    /**
     * @return list<string>
     */
    public function ghostscriptCommand(string $input, string $output, string $level): array
    {
        return [
            (string) $this->ghostscript,
            '-dSAFER',               // Dosya sistemi erişimini kısıtlar
            '-dBATCH',
            '-dNOPAUSE',
            '-dQUIET',
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.5',
            '-dPDFSETTINGS=' . self::GS_SETTINGS[$level],
            '-dDetectDuplicateImages=true',
            '-dCompressFonts=true',
            '-sOutputFile=' . $output,
            $input,
        ];
    }

    private function withGhostscript(string $input, string $output, string $level, int $pages): void
    {
        $result = $this->runner->run($this->ghostscriptCommand($input, $output, $level), $this->timeout);
        if (!$result->successful() || !is_file($output) || filesize($output) === 0) {
            throw new ProcessingException('Ghostscript failed (exit ' . $result->exitCode . ($result->timedOut ? ', timeout' : '') . ')');
        }

        // Çıktı doğrulaması: geçerli PDF ve aynı sayfa sayısı
        if ((new PdfInspector())->pageCount($output) !== $pages) {
            throw new ProcessingException('Ghostscript output page count mismatch');
        }
    }

    /**
     * @return array<string, int>
     */
    private function withPhp(string $input, string $output, string $level): array
    {
        $optimizer = new StreamOptimizer($level);
        $pdf = new OptimizingFpdi();
        $pdf->setOptimizer($optimizer);

        try {
            $count = $pdf->setSourceFile($input);
            for ($i = 1; $i <= $count; $i++) {
                $template = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);
            }
            $pdf->Output('F', $output);
        } catch (\setasign\Fpdi\PdfParser\PdfParserException | \setasign\Fpdi\PdfReader\PdfReaderException $e) {
            @unlink($output);
            throw new ProcessingException('PHP compression failed: ' . $e->getMessage(), 'errors.processing_failed', previous: $e);
        } finally {
            $pdf->cleanUp(true);
        }

        return $optimizer->stats();
    }
}
