<?php

declare(strict_types=1);

namespace App\Pdf\Redaction;

use App\Exceptions\ProcessingException;
use App\Exceptions\ToolUnavailableException;
use App\Exceptions\ValidationException;
use App\Pdf\Fpdi;
use App\Pdf\PdfInfo;
use App\Tools\ProcessRunner;

/**
 * Kalıcı karartma (spec §12).
 *
 * Karartma yapılan her sayfa TAMAMEN görüntüye dönüştürülür ve kutular piksellere yakılır;
 * o sayfadaki metin, vektör çizimler, gizli katmanlar ve açıklamalar çıktıya hiç aktarılmaz.
 * Diğer sayfalar FPDI ile şablon olarak aktarılır (açıklamalar, form alanları, ekler, yer imleri
 * ve özgün belge bilgileri de bu sırada düşer).
 *
 * Sayfa görüntüsü kaynağı:
 *  - Ghostscript varsa sunucuda üretilir (png16m, -dSAFER).
 *  - Yoksa tarayıcının PDF.js ile ürettiği görüntü kullanılır; sunucu görüntüyü doğrular,
 *    GD ile yeniden kodlar ve kutuları KENDİSİ tekrar yakar.
 */
final class Redactor
{
    public const DPI = 150;

    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly ?string $ghostscript,
        private readonly int $timeout,
    ) {
    }

    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg') && function_exists('imagefilledrectangle');
    }

    public function serverRendering(): bool
    {
        return $this->ghostscript !== null && ProcessRunner::available();
    }

    /**
     * @param array<int, string> $browserImages Sayfa no => tarayıcıdan gelen görüntü dosyası (sunucu render yoksa)
     */
    public function redact(string $input, string $output, PdfInfo $info, RedactionBoxes $boxes, array $browserImages, string $workDir): int
    {
        if (!self::available()) {
            throw new ToolUnavailableException('GD not available', 'errors.tool_unavailable');
        }

        $rasters = [];
        foreach ($boxes->pageNumbers() as $page) {
            [$width, $height] = self::visualSize($info, $page);
            $image = $this->serverRendering()
                ? $this->renderWithGhostscript($input, $page, $workDir)
                : $this->loadBrowserImage($browserImages[$page] ?? null, $width / $height);

            $boxes->burn($image, $page);
            $file = $workDir . '/page-' . $page . '.jpg';
            imagejpeg($image, $file, 88);
            imagedestroy($image);
            $rasters[$page] = $file;
        }

        $pdf = new Fpdi();
        try {
            $count = $pdf->setSourceFile($input);
            for ($page = 1; $page <= $count; $page++) {
                if (isset($rasters[$page])) {
                    [$width, $height] = self::visualSize($info, $page);
                    $pdf->AddPage($width > $height ? 'L' : 'P', [$width, $height]);
                    $pdf->Image($rasters[$page], 0, 0, $width, $height, 'JPG');
                    continue;
                }
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);
            }
            $pdf->Output('F', $output);

            return $count;
        } finally {
            $pdf->cleanUp(true);
            foreach ($rasters as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Sayfanın görünen boyutu (pt): /Rotate 90/270 ise genişlik ve yükseklik yer değiştirir.
     *
     * @return array{float, float}
     */
    public static function visualSize(PdfInfo $info, int $page): array
    {
        $p = $info->pages[$page - 1];
        $rotated = in_array(((int) $p['rotation'] % 360 + 360) % 360, [90, 270], true);

        return $rotated ? [$p['height'], $p['width']] : [$p['width'], $p['height']];
    }

    private function renderWithGhostscript(string $input, int $page, string $workDir): \GdImage
    {
        $png = $workDir . '/gs-page-' . $page . '.png';
        $result = $this->runner->run([
            (string) $this->ghostscript,
            '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
            '-sDEVICE=png16m',
            '-r' . self::DPI,
            '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4',
            '-dFirstPage=' . $page, '-dLastPage=' . $page,
            '-sOutputFile=' . $png,
            $input,
        ], $this->timeout);

        $image = $result->successful() && is_file($png) ? @imagecreatefrompng($png) : false;
        @unlink($png);
        if ($image === false) {
            throw new ProcessingException('Ghostscript rasterization failed for page ' . $page);
        }

        return $image;
    }

    /**
     * Tarayıcı görüntüsü: tür, boyut ve sayfa oranı doğrulanır; GD ile çözülür (yeniden kodlanacak).
     */
    private function loadBrowserImage(?string $file, float $aspect): \GdImage
    {
        if ($file === null || !is_file($file) || filesize($file) > 8 * 1024 * 1024) {
            throw new ValidationException('Missing page image', 'redact.image_missing');
        }

        $info = @getimagesize($file);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
            || $info[0] < 200 || $info[1] < 200 || $info[0] > 4000 || $info[1] > 4000) {
            throw new ValidationException('Invalid page image', 'redact.image_invalid');
        }
        if (abs($info[0] / $info[1] - $aspect) / $aspect > 0.03) {
            throw new ValidationException('Page image aspect mismatch', 'redact.image_invalid');
        }

        $image = @imagecreatefromstring((string) file_get_contents($file));
        if ($image === false) {
            throw new ValidationException('Undecodable page image', 'redact.image_invalid');
        }

        // Saydamlık varsa beyaz zemin
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);

        return $canvas;
    }
}
