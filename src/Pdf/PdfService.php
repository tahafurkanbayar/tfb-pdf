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
