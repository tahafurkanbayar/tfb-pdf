<?php

declare(strict_types=1);

namespace App\Pdf\Ocr;

use App\Exceptions\ProcessingException;
use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Pdf\PdfInspector;
use App\Pdf\Redaction\Redactor;
use App\Services\Evren\EvrenOcrClient;

final class EvrenOcrEngine
{
    public function __construct(
        private readonly EvrenOcrClient $client,
        private readonly SearchablePdfBuilder $builder,
        public readonly int $maxPages = 5,
    ) {
    }

    public function available(): bool
    {
        return $this->client->available() && function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /** Validate every page before sending anything; no partial PDF on API failure. */
    public function run(string $input, string $output, array $images, string $workDir): array
    {
        $info = (new PdfInspector())->inspect($input, $this->maxPages);
        if (count($images) !== $info->pageCount) {
            throw new ValidationException('Missing OCR page images', 'ocr.images_invalid');
        }
        for ($page = 1; $page <= $info->pageCount; $page++) {
            $file = $images[$page] ?? null;
            if (!$file instanceof UploadedFile || !$file->isValidUpload() || $file->error !== UPLOAD_ERR_OK
                || !is_file($file->tmpPath) || filesize($file->tmpPath) > 8 * 1024 * 1024) {
                throw new ValidationException('Invalid OCR page upload', 'ocr.images_invalid');
            }
            $size = @getimagesize($file->tmpPath);
            [$w, $h] = Redactor::visualSize($info, $page);
            if ($w < 20 || $h < 20 || $size === false || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
                || min($size[0], $size[1]) < 200 || max($size[0], $size[1]) > 4000
                || $size[0] * $size[1] > 12000000 || abs($size[0] / $size[1] - $w / $h) / ($w / $h) > 0.03) {
                throw new ValidationException('Invalid OCR image dimensions', 'ocr.images_invalid');
            }
        }
        $prepared = [];
        try {
            for ($page = 1; $page <= $info->pageCount; $page++) {
                $image = @imagecreatefromstring((string) file_get_contents($images[$page]->tmpPath));
                if ($image === false) {
                    throw new ValidationException('Cannot decode OCR image', 'ocr.images_invalid');
                }
                $path = $workDir . '/evren-page-' . $page . '.jpg';
                $prepared[] = $path;
                $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
                try {
                    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                    imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                    if (!imagejpeg($canvas, $path, 90)) {
                        throw new ProcessingException('Cannot encode OCR image', 'ocr.evren_failed');
                    }
                } finally {
                    imagedestroy($image);
                    imagedestroy($canvas);
                }
            }
            $texts = [];
            $started = microtime(true);
            foreach ($prepared as $path) {
                if (microtime(true) - $started > 180) {
                    throw new ProcessingException('Evren OCR operation deadline exceeded', 'ocr.evren_failed');
                }
                $texts[] = $this->client->recognize((string) file_get_contents($path));
            }
            $pages = $this->builder->build($input, $output, $texts);
            return ['pages' => $pages, 'provider' => 'evren'];
        } finally {
            foreach ($prepared as $path) @unlink($path);
        }
    }
}
