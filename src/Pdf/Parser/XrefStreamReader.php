<?php

declare(strict_types=1);

namespace App\Pdf\Parser;

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\CrossReference\ReaderInterface;
use setasign\Fpdi\PdfParser\Filter\Flate;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;

/**
 * Cross-reference stream okuyucu (PDF 1.5+, ISO 32000-1 §7.5.8).
 *
 * Girdi tipleri: 0 = boş, 1 = dosyada bayt ofseti, 2 = object stream içinde (stream no, sıra).
 */
final class XrefStreamReader implements ReaderInterface
{
    /** @var array<int, int> nesne no => bayt ofseti */
    private array $offsets = [];

    /** @var array<int, array{int, int}> nesne no => [object stream no, index] */
    private array $compressed = [];

    /** @var array<int, true> */
    private array $free = [];

    private PdfDictionary $trailer;

    public function __construct(PdfIndirectObject $object)
    {
        $stream = PdfStream::ensure($object->value);
        $dict = $stream->value;
        $this->trailer = $dict;

        $type = PdfDictionary::get($dict, 'Type');
        if (!$type instanceof PdfName || $type->value !== 'XRef') {
            throw new CrossReferenceException('Object is not a cross-reference stream.', CrossReferenceException::INVALID_DATA);
        }

        $widths = self::intArray(PdfDictionary::get($dict, 'W'));
        if (count($widths) !== 3 || min($widths) < 0 || max($widths) > 8) {
            throw new CrossReferenceException('Invalid /W in cross-reference stream.', CrossReferenceException::INVALID_DATA);
        }

        $size = PdfDictionary::get($dict, 'Size');
        $index = self::intArray(PdfDictionary::get($dict, 'Index'));
        if ($index === []) {
            $index = [0, $size instanceof PdfNumeric ? (int) $size->value : 0];
        }

        $this->parseEntries(self::decodeData($stream), $widths, $index);
    }

    public function getOffsetFor($objectNumber)
    {
        return $this->offsets[$objectNumber] ?? false;
    }

    /**
     * @return array{int, int}|null
     */
    public function getCompressedLocation(int $objectNumber): ?array
    {
        return $this->compressed[$objectNumber] ?? null;
    }

    /**
     * Bu bölümde nesne tanımlı mı (sıkıştırılmış, normal veya boş olarak)?
     */
    public function defines(int $objectNumber): bool
    {
        return isset($this->offsets[$objectNumber]) || isset($this->compressed[$objectNumber]) || isset($this->free[$objectNumber]);
    }

    public function getTrailer()
    {
        return $this->trailer;
    }

    private static function decodeData(PdfStream $stream): string
    {
        $data = (string) $stream->getStream();
        $filters = $stream->getFilters();
        $params = PdfDictionary::get($stream->value, 'DecodeParms');
        if ($params instanceof PdfArray) {
            $params = $params->value[0] ?? null;
        }

        foreach ($filters as $filter) {
            $name = $filter instanceof PdfName ? $filter->value : '';
            if ($name !== 'FlateDecode' && $name !== 'Fl') {
                throw new CrossReferenceException('Unsupported filter in cross-reference stream: ' . $name, CrossReferenceException::INVALID_DATA);
            }
            $data = (new Flate())->decode($data);
        }

        if ($params instanceof PdfDictionary) {
            $predictor = (int) PdfDictionary::get($params, 'Predictor', PdfNumeric::create(1))->value;
            if ($predictor >= 10) {
                $data = PngPredictor::decode(
                    $data,
                    (int) PdfDictionary::get($params, 'Columns', PdfNumeric::create(1))->value,
                    (int) PdfDictionary::get($params, 'Colors', PdfNumeric::create(1))->value,
                    (int) PdfDictionary::get($params, 'BitsPerComponent', PdfNumeric::create(8))->value,
                );
            } elseif ($predictor === 2) {
                throw new CrossReferenceException('TIFF predictor in xref stream is not supported.', CrossReferenceException::INVALID_DATA);
            }
        }

        return $data;
    }

    /**
     * @param list<int> $widths
     * @param list<int> $index
     */
    private function parseEntries(string $data, array $widths, array $index): void
    {
        $entryLength = array_sum($widths);
        if ($entryLength === 0) {
            return;
        }

        $position = 0;
        $dataLength = strlen($data);

        for ($s = 0; $s + 1 < count($index); $s += 2) {
            $start = $index[$s];
            $count = $index[$s + 1];

            for ($i = 0; $i < $count; $i++) {
                if ($position + $entryLength > $dataLength) {
                    return; // Eksik veri: okunabilen kısmı kullan
                }

                $fields = [];
                $fieldPos = $position;
                foreach ($widths as $w) {
                    $fields[] = $w === 0 ? null : self::readInt($data, $fieldPos, $w);
                    $fieldPos += $w;
                }
                $position += $entryLength;

                $type = $fields[0] ?? 1; // W[0] = 0 ise varsayılan tip 1
                $objectNumber = $start + $i;

                match ($type) {
                    0 => $this->free[$objectNumber] = true,
                    1 => $this->offsets[$objectNumber] = (int) $fields[1],
                    2 => $this->compressed[$objectNumber] = [(int) $fields[1], (int) ($fields[2] ?? 0)],
                    default => null, // Bilinmeyen tipler yok sayılır (spec gereği)
                };
            }
        }
    }

    private static function readInt(string $data, int $offset, int $width): int
    {
        $value = 0;
        for ($i = 0; $i < $width; $i++) {
            $value = ($value << 8) | ord($data[$offset + $i]);
        }

        return $value;
    }

    /**
     * @return list<int>
     */
    private static function intArray(mixed $value): array
    {
        if (!$value instanceof PdfArray) {
            return [];
        }

        $out = [];
        foreach ($value->value as $item) {
            if (!$item instanceof PdfNumeric) {
                return [];
            }
            $out[] = (int) $item->value;
        }

        return $out;
    }
}
