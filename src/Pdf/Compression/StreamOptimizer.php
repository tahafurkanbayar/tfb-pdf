<?php

declare(strict_types=1);

namespace App\Pdf\Compression;

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;

/**
 * PHP ile gerçek boyut azaltma (Ghostscript yoksa):
 *  - JPEG (DCTDecode) görüntüler: seviyeye göre en büyük kenar sınırlanır ve GD ile yeniden kodlanır.
 *  - Filtresiz akışlar: FlateDecode ile sıkıştırılır.
 * Yalnızca gerçekten küçülen akışlar değiştirilir; emin olunamayan durumlar (CMYK, /Decode, renk anahtarı
 * maskesi, bilinmeyen renk uzayı) olduğu gibi bırakılır.
 */
final class StreamOptimizer
{
    /** Seviye => [en büyük kenar (px), JPEG kalitesi] */
    public const LEVELS = [
        'low' => [2400, 85],
        'medium' => [1600, 70],
        'high' => [1100, 55],
    ];

    private int $imagesOptimized = 0;

    private int $streamsCompressed = 0;

    public function __construct(private readonly string $level)
    {
        if (!isset(self::LEVELS[$level])) {
            throw new \InvalidArgumentException('Unknown compression level: ' . $level);
        }
    }

    public function stats(): array
    {
        return ['images_optimized' => $this->imagesOptimized, 'streams_compressed' => $this->streamsCompressed];
    }

    /**
     * Değiştirilmiş akış veya değişiklik yoksa null.
     */
    public function optimize(PdfStream $stream, PdfParser $parser): ?PdfStream
    {
        $dict = $stream->value;
        $subtype = PdfDictionary::get($dict, 'Subtype');
        $type = PdfDictionary::get($dict, 'Type');
        $filters = array_map(static fn (PdfType $f): string => $f instanceof PdfName ? $f->value : '?', $stream->getFilters());

        try {
            if ($subtype instanceof PdfName && $subtype->value === 'Image' && $filters === ['DCTDecode']) {
                return $this->optimizeJpeg($stream, $parser);
            }

            // XMP metadata okunabilir kalmalı (PDF/A önerisi); sıkıştırılmaz
            $isMetadata = $type instanceof PdfName && $type->value === 'Metadata';
            if ($filters === [] && !$isMetadata) {
                return $this->deflate($stream);
            }
        } catch (\Throwable) {
            // Herhangi bir sorunda akış özgün haliyle kopyalanır
        }

        return null;
    }

    private function deflate(PdfStream $stream): ?PdfStream
    {
        $data = (string) $stream->getStream();
        if (strlen($data) < 256) {
            return null;
        }

        $compressed = gzcompress($data, 9);
        if ($compressed === false || strlen($compressed) >= strlen($data) * 0.9) {
            return null;
        }

        $entries = $stream->value->value;
        $entries['Filter'] = PdfName::create('FlateDecode');
        unset($entries['DecodeParms']);
        $entries['Length'] = PdfNumeric::create(strlen($compressed));
        $this->streamsCompressed++;

        return PdfStream::create(PdfDictionary::create($entries), $compressed);
    }

    private function optimizeJpeg(PdfStream $stream, PdfParser $parser): ?PdfStream
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $dict = $stream->value;
        foreach (['Decode', 'Mask', 'DecodeParms'] as $unsupported) {
            if (!PdfDictionary::get($dict, $unsupported) instanceof PdfNull) {
                return null;
            }
        }
        $bpc = PdfType::resolve(PdfDictionary::get($dict, 'BitsPerComponent'), $parser);
        if ($bpc instanceof PdfNumeric && (int) $bpc->value !== 8) {
            return null;
        }
        if (!$this->isRgbOrGray(PdfType::resolve(PdfDictionary::get($dict, 'ColorSpace'), $parser), $parser)) {
            return null; // CMYK, Indexed, Separation vb.
        }

        $data = (string) $stream->getStream();
        $image = @imagecreatefromstring($data);
        if ($image === false) {
            return null;
        }

        [$maxDimension, $quality] = self::LEVELS[$this->level];
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1.0, $maxDimension / max($width, $height));
        if ($scale < 1.0) {
            $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC);
            imagedestroy($image);
            if ($scaled === false) {
                return null;
            }
            $image = $scaled;
        }

        ob_start();
        imagejpeg($image, null, $quality);
        $jpeg = (string) ob_get_clean();
        $newWidth = imagesx($image);
        $newHeight = imagesy($image);
        imagedestroy($image);

        // Anlamlı kazanç yoksa özgün görüntü korunur
        if ($jpeg === '' || strlen($jpeg) >= strlen($data) * 0.9) {
            return null;
        }

        $entries = $dict->value;
        $entries['Width'] = PdfNumeric::create($newWidth);
        $entries['Height'] = PdfNumeric::create($newHeight);
        // GD her zaman 3 bileşenli (RGB) JPEG yazar
        $entries['ColorSpace'] = PdfName::create('DeviceRGB');
        $entries['BitsPerComponent'] = PdfNumeric::create(8);
        $entries['Filter'] = PdfName::create('DCTDecode');
        $entries['Length'] = PdfNumeric::create(strlen($jpeg));
        $this->imagesOptimized++;

        return PdfStream::create(PdfDictionary::create($entries), $jpeg);
    }

    private function isRgbOrGray(PdfType $colorSpace, PdfParser $parser): bool
    {
        if ($colorSpace instanceof PdfName) {
            return in_array($colorSpace->value, ['DeviceRGB', 'DeviceGray', 'CalRGB', 'CalGray'], true);
        }
        if ($colorSpace instanceof PdfArray && ($colorSpace->value[0] ?? null) instanceof PdfName) {
            $family = $colorSpace->value[0]->value;
            if ($family === 'ICCBased' && ($colorSpace->value[1] ?? null) instanceof PdfIndirectObjectReference) {
                $profile = PdfType::resolve($colorSpace->value[1], $parser);
                if ($profile instanceof PdfStream) {
                    $n = PdfType::resolve(PdfDictionary::get($profile->value, 'N'), $parser);

                    return $n instanceof PdfNumeric && in_array((int) $n->value, [1, 3], true);
                }
            }
            return in_array($family, ['CalRGB', 'CalGray'], true);
        }

        return false;
    }
}
