<?php

declare(strict_types=1);

namespace App\Pdf\Parser;

use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfBoolean;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfToken;
use setasign\Fpdi\PdfParser\Type\PdfType;

/**
 * FPDI PdfType değerlerini PDF sözdizimine çevirir (FpdiTrait::writePdfType ile aynı kurallar).
 * Nesne numaraları bir eşleme fonksiyonuyla yeniden yazılabilir.
 */
final class PdfSerializer
{
    /**
     * @param (\Closure(int): int)|null $mapReference Kaynak nesne no → hedef nesne no
     */
    public static function value(PdfType $value, ?\Closure $mapReference = null): string
    {
        return match (true) {
            $value instanceof PdfNumeric => is_int($value->value)
                ? (string) $value->value
                : rtrim(rtrim(sprintf('%.5F', $value->value), '0'), '.'),
            $value instanceof PdfName => '/' . $value->value,
            $value instanceof PdfString => '(' . $value->value . ')',
            $value instanceof PdfHexString => '<' . $value->value . '>',
            $value instanceof PdfBoolean => $value->value ? 'true' : 'false',
            $value instanceof PdfNull => 'null',
            $value instanceof PdfToken => (string) $value->value,
            $value instanceof PdfIndirectObjectReference => ($mapReference !== null ? $mapReference((int) $value->value) : $value->value) . ' 0 R',
            $value instanceof PdfArray => '[' . implode(' ', array_map(
                static fn (PdfType $v): string => self::value($v, $mapReference),
                $value->value
            )) . ']',
            $value instanceof PdfDictionary => self::dictionary($value, $mapReference),
            $value instanceof PdfStream => throw new \InvalidArgumentException('Use stream() for stream objects.'),
            default => throw new \InvalidArgumentException('Unsupported PDF type: ' . $value::class),
        };
    }

    /**
     * @param (\Closure(int): int)|null $mapReference
     */
    public static function dictionary(PdfDictionary $dict, ?\Closure $mapReference = null): string
    {
        $out = '<<';
        foreach ($dict->value as $key => $entry) {
            $out .= '/' . $key . ' ' . self::value($entry, $mapReference) . ' ';
        }

        return rtrim($out) . '>>';
    }

    /**
     * Stream nesnesi: sözlük (/Length yeni veriye göre) + veri.
     *
     * @param array<string, PdfType> $overrides Sözlükte değiştirilecek/eklenecek girdiler
     * @param list<string> $remove Sözlükten kaldırılacak anahtarlar
     * @param (\Closure(int): int)|null $mapReference
     */
    public static function stream(PdfDictionary $dict, string $data, array $overrides = [], array $remove = [], ?\Closure $mapReference = null): string
    {
        $entries = $dict->value;
        foreach ($remove as $key) {
            unset($entries[$key]);
        }
        $entries = array_merge($entries, $overrides);
        $entries['Length'] = PdfNumeric::create(strlen($data));

        return self::dictionary(PdfDictionary::create($entries), $mapReference) . "\nstream\n" . $data . "\nendstream";
    }
}
