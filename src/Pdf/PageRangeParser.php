<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Exceptions\ValidationException;

/**
 * Sayfa aralığı ifadeleri: "1-3, 5, 8-12", satır sonu veya noktalı virgülle de ayrılabilir.
 * "8-" → 8'den son sayfaya kadar. Tarayıcıdaki eşdeğeri: assets/js/page-range.js (aynı kurallar).
 */
final class PageRangeParser
{
    /**
     * @return list<array{int, int}> [başlangıç, bitiş] (1 tabanlı, dahil)
     * @throws ValidationException
     */
    public static function parse(string $input, int $totalPages): array
    {
        $input = trim($input);
        if ($input === '') {
            throw new ValidationException('Empty range', 'split.range_empty');
        }

        $ranges = [];
        foreach (preg_split('/[,;\n\r]+/', $input) ?: [] as $raw) {
            $token = preg_replace('/\s+/', '', $raw) ?? '';
            if ($token === '') {
                continue;
            }

            if (preg_match('/^(\d{1,6})$/', $token, $m)) {
                $start = $end = (int) $m[1];
            } elseif (preg_match('/^(\d{1,6})[-–](\d{1,6})?$/u', $token, $m)) {
                $start = (int) $m[1];
                $end = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $totalPages;
            } else {
                throw new ValidationException('Invalid token: ' . $token, 'split.range_invalid', ['token' => mb_substr($token, 0, 20)]);
            }

            if ($start < 1 || $end < 1) {
                throw new ValidationException('Page zero', 'split.range_zero', ['token' => $token]);
            }
            if ($start > $end) {
                throw new ValidationException('Reversed range', 'split.range_reversed', ['token' => $token]);
            }
            if ($end > $totalPages) {
                throw new ValidationException('Out of range', 'split.range_out_of_bounds', ['token' => $token, 'total' => $totalPages]);
            }

            $ranges[] = [$start, $end];
        }

        if ($ranges === []) {
            throw new ValidationException('Empty range', 'split.range_empty');
        }

        return $ranges;
    }

    /**
     * @param array{int, int} $range
     * @return list<int>
     */
    public static function pages(array $range): array
    {
        return range($range[0], $range[1]);
    }

    /**
     * @param array{int, int} $range
     */
    public static function label(array $range): string
    {
        return $range[0] === $range[1] ? (string) $range[0] : $range[0] . '-' . $range[1];
    }
}
