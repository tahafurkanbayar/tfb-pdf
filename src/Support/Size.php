<?php

declare(strict_types=1);

namespace App\Support;

use App\I18n\Lang;

/**
 * "25M", "512K", "1G" veya bayt cinsinden değerleri çözer ve okunabilir biçime çevirir.
 */
final class Size
{
    public static function parse(string|int $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        $value = trim($value);
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([KMG]?)B?$/i', $value, $m)) {
            return 0;
        }

        $multiplier = match (strtoupper($m[2])) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 1,
        };

        return (int) round((float) $m[1] * $multiplier);
    }

    /**
     * php.ini değerleri ("40M", "-1", "0") için. Sınırsızsa PHP_INT_MAX döner.
     */
    public static function fromIni(string $key): int
    {
        $value = (string) ini_get($key);
        if ($value === '' || $value === '-1' || $value === '0') {
            return PHP_INT_MAX;
        }

        return self::parse($value);
    }

    /**
     * Ondalık ayırıcı verilmezse etkin dilinki kullanılır (TR "13,5 KB", EN "13.5 KB").
     */
    public static function format(int $bytes, int $precision = 1, ?string $decimalSeparator = null): string
    {
        $decimalSeparator ??= Lang::translator()->get('common.decimal_separator');
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) max(0, $bytes);
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $size : number_format($size, $precision, $decimalSeparator, '')) . ' ' . $units[$i];
    }
}
