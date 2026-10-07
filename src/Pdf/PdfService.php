<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Exceptions\ProcessingException;
use App\Exceptions\ValidationException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfReader\PdfReaderException;

/**
 * Saf PDF işlemleri (FPDI + tFPDF). Veritabanı, depolama ve HTTP'den bağımsızdır:
 * girdi dosya yolları alır, çıktı dosyası yazar. Orijinal dosyalara asla yazmaz.
 *
 * Not: FPDI sayfaları "şablon" (XObject) olarak içe aktarır. Görünüm korunur; ancak bağlantılar,
 * yer imleri, form alanları, açıklamalar (annotations) ve dijital imzalar yeni dosyaya aktarılmaz.
 * Bu durum kullanıcıya WarningCollector ile bildirilir.
 */
final class PdfService
{
    public function __construct(private readonly int $maxPages)
    {
    }

    /**
     * Birden fazla PDF'i verilen sırayla tek dosyada birleştirir.
     *
     * @param list<string> $inputs
     * @return int Çıktı sayfa sayısı
     */
    public function merge(array $inputs, string $output): int
    {
        if (count($inputs) < 2) {
            throw new ValidationException('Merge needs at least two files', 'operations.merge_min_files');
        }

        return $this->build($output, function (Fpdi $pdf) use ($inputs): int {
            $total = 0;
            foreach ($inputs as $input) {
                $count = $pdf->setSourceFile($input);
                $total += $count;
                $this->assertPageLimit($total);
                for ($i = 1; $i <= $count; $i++) {
                    $this->appendPage($pdf, $i);
                }
            }

            return $total;
        });
    }

    /**
     * Seçilen sayfaları verilen sırayla yeni bir PDF'e yazar. Bölme, sıralama, sayfa silme ve
     * döndürmenin ortak yapı taşıdır.
     *
     * @param list<int> $pages 1 tabanlı sayfa numaraları (tekrar edebilir)
     * @param array<int, int> $rotations Sayfa no => ek döndürme (0/90/180/270), saat yönünde
     */
    public function extract(string $input, string $output, array $pages, array $rotations = []): int
    {
        if ($pages === []) {
            throw new ValidationException('No pages selected', 'operations.no_pages');
        }

        return $this->build($output, function (Fpdi $pdf) use ($input, $pages, $rotations): int {
            $count = $pdf->setSourceFile($input);
            foreach ($pages as $page) {
                if ($page < 1 || $page > $count) {
                    throw new ValidationException('Page out of range: ' . $page, 'operations.page_out_of_range', ['page' => $page, 'total' => $count]);
                }
                $this->appendPage($pdf, $page, $rotations[$page] ?? 0);
            }

            return count($pages);
        });
    }

    /**
     * Metin filigranı. $pages boşsa tüm sayfalar.
     *
     * @param list<int> $pages
     */
    public function watermark(string $input, string $output, WatermarkOptions $options, array $pages = []): int
    {
        return $this->build($output, function (Fpdi $pdf) use ($input, $options, $pages): int {
            $count = $pdf->setSourceFile($input);
            $selected = $pages === [] ? null : array_flip($pages);

            for ($page = 1; $page <= $count; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);

                $apply = $selected === null || isset($selected[$page]);
                if ($apply && $options->under) {
                    $this->drawWatermark($pdf, $options, $size['width'], $size['height']);
                }
                $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);
                if ($apply && !$options->under) {
                    $this->drawWatermark($pdf, $options, $size['width'], $size['height']);
                }
            }

            return $count;
        });
    }

    private function drawWatermark(Fpdi $pdf, WatermarkOptions $o, float $width, float $height): void
    {
        $pdf->useUnicodeFont($o->fontSize, $o->bold);
        $pdf->SetTextColor(...$o->color);
        $textWidth = $pdf->GetStringWidth($o->text);
        $margin = max(18.0, $o->fontSize * 0.6);

        $centers = match ($o->position) {
            'center' => [[$width / 2, $height / 2]],
            'top' => [[$width / 2, $margin + $o->fontSize / 2]],
            'bottom' => [[$width / 2, $height - $margin - $o->fontSize / 2]],
            'top-left' => [[$margin + $textWidth / 2, $margin + $o->fontSize / 2]],
            'top-right' => [[$width - $margin - $textWidth / 2, $margin + $o->fontSize / 2]],
            'bottom-left' => [[$margin + $textWidth / 2, $height - $margin - $o->fontSize / 2]],
            'bottom-right' => [[$width - $margin - $textWidth / 2, $height - $margin - $o->fontSize / 2]],
            'tile' => self::tileCenters($width, $height, $textWidth, $o->fontSize),
        };

        foreach ($centers as [$cx, $cy]) {
            $pdf->saveState();
            $pdf->setAlpha($o->opacity);
            if ($o->rotation !== 0) {
                $pdf->rotateAround($o->rotation, $cx, $cy);
            }
            // Metnin optik merkezi (cx, cy) olacak şekilde taban çizgisi
            $pdf->Text($cx - $textWidth / 2, $cy + $o->fontSize * 0.35, $o->text);
            $pdf->restoreState();
        }
    }

    /**
     * @return list<array{float, float}>
     */
    private static function tileCenters(float $width, float $height, float $textWidth, int $fontSize): array
    {
        $stepX = $textWidth + $fontSize * 2;
        $stepY = $fontSize * 4;
        $centers = [];
        $row = 0;
        for ($y = $stepY / 2; $y < $height + $stepY; $y += $stepY) {
            $offset = ($row++ % 2) * $stepX / 2;
            for ($x = $stepX / 2 - $offset; $x < $width + $stepX; $x += $stepX) {
                $centers[] = [$x, $y];
            }
        }

        return $centers;
    }

    public function pageCount(string $input): int
    {
        return (new PdfInspector())->pageCount($input);
    }

    /**
     * Kaynak sayfayı şablon olarak ekler. Kaynağın kendi /Rotate değeri FPDI tarafından
     * şablona uygulanır; ek döndürme yeni sayfanın /Rotate girdisi olarak yazılır
     * (içerik yeniden çizilmez, kalite kaybı olmaz).
     */
    private function appendPage(Fpdi $pdf, int $page, int $extraRotation = 0): void
    {
        $template = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($template);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']], self::normalizeRotation($extraRotation));
        $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);
    }

    public static function normalizeRotation(int $degrees): int
    {
        $degrees %= 360;
        if ($degrees < 0) {
            $degrees += 360;
        }
        if ($degrees % 90 !== 0) {
            throw new ValidationException('Invalid rotation: ' . $degrees, 'operations.invalid_rotation');
        }

        return $degrees;
    }

    private function assertPageLimit(int $pages): void
    {
        if ($pages > $this->maxPages) {
            throw new ValidationException('Too many pages: ' . $pages, 'upload.too_many_pages', ['max' => $this->maxPages]);
        }
    }

    /**
     * @param \Closure(Fpdi): int $writer
     */
    private function build(string $output, \Closure $writer): int
    {
        if (file_exists($output)) {
            throw new ProcessingException('Output already exists');
        }

        $pdf = new Fpdi();
        try {
            $pages = $writer($pdf);
            $pdf->Output('F', $output);

            return $pages;
        } catch (PdfParserException | PdfReaderException $e) {
            @unlink($output);
            throw new ProcessingException('PDF processing failed: ' . $e->getMessage(), 'errors.processing_failed', previous: $e);
        } catch (\Throwable $e) {
            @unlink($output);
            throw $e;
        } finally {
            // Hata olsa da kaynak dosya tanıtıcıları kapatılır (Windows'ta dosya kilidi, uzun süreçte sızıntı)
            $pdf->cleanUp(true);
        }
    }
}
