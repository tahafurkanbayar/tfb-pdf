<?php

declare(strict_types=1);

namespace App\Pdf\Parser;

/**
 * PDF FlateDecode DecodeParms /Predictor >= 10 (PNG satır filtreleri: None, Sub, Up, Average, Paeth).
 * Ücretsiz FPDI'de bulunmadığı için xref stream'leri çözmek amacıyla eklendi.
 */
final class PngPredictor
{
    public static function decode(string $data, int $columns, int $colors = 1, int $bitsPerComponent = 8): string
    {
        $bytesPerPixel = max(1, intdiv($colors * $bitsPerComponent + 7, 8));
        $rowLength = intdiv($colors * $bitsPerComponent * $columns + 7, 8);
        $stride = $rowLength + 1;

        $length = strlen($data);
        if ($rowLength <= 0 || $length % $stride !== 0) {
            // Bazı üreticiler son satırı eksik yazar; tam satırlar işlenir
            $length -= $length % $stride;
        }

        $output = '';
        $previous = str_repeat("\0", $rowLength);

        for ($offset = 0; $offset < $length; $offset += $stride) {
            $type = ord($data[$offset]);
            $row = substr($data, $offset + 1, $rowLength);
            $decoded = '';

            for ($i = 0; $i < $rowLength; $i++) {
                $raw = ord($row[$i]);
                $left = $i >= $bytesPerPixel ? ord($decoded[$i - $bytesPerPixel]) : 0;
                $up = ord($previous[$i]);
                $upLeft = $i >= $bytesPerPixel ? ord($previous[$i - $bytesPerPixel]) : 0;

                $value = match ($type) {
                    0 => $raw,
                    1 => $raw + $left,
                    2 => $raw + $up,
                    3 => $raw + intdiv($left + $up, 2),
                    4 => $raw + self::paeth($left, $up, $upLeft),
                    default => throw new \UnexpectedValueException('Unknown PNG predictor row type: ' . $type),
                };
                $decoded .= chr($value & 0xFF);
            }

            $output .= $decoded;
            $previous = $decoded;
        }

        return $output;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }

        return $pb <= $pc ? $b : $c;
    }
}
