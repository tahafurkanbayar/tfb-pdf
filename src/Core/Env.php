<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Basit .env okuyucu. putenv() kullanmaz; değerler yalnızca bu sınıfta tutulur.
 *
 * Desteklenen sözdizimi: KEY=value, KEY="çift tırnak", KEY='tek tırnak',
 * satır başı # yorumları, tırnaksız değerlerde " #" ile başlayan satır sonu yorumları,
 * isteğe bağlı "export " öneki.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $contents = (string) file_get_contents($file);
        self::$values = array_merge(self::$values, self::parse($contents));
    }

    /**
     * @return array<string, string>
     */
    public static function parse(string $contents): array
    {
        $values = [];
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            $values[$key] = self::parseValue(trim(substr($line, $pos + 1)));
        }

        return $values;
    }

    private static function parseValue(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];
        if (($quote === '"' || $quote === "'") && ($end = strpos($raw, $quote, 1)) !== false) {
            $value = substr($raw, 1, $end - 1);

            return $quote === '"' ? str_replace(['\\n', '\\"'], ["\n", '"'], $value) : $value;
        }

        $commentPos = strpos($raw, ' #');
        if ($commentPos !== false) {
            $raw = substr($raw, 0, $commentPos);
        }

        return trim($raw);
    }

    /**
     * Önce gerçek ortam değişkenine (sunucu yapılandırması), sonra .env değerine bakar.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? self::$values[$key] ?? null;

        if ($value === null || !is_string($value)) {
            return $value ?? $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            '' => $default,
            default => $value,
        };
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default): bool
    {
        $value = self::get($key, $default);

        return is_bool($value) ? $value : in_array(strtolower((string) $value), ['1', 'on', 'yes'], true);
    }

    /**
     * Testler için: mevcut değerleri saklayıp geri yüklemek.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::$values;
    }

    /**
     * @param array<string, string> $values
     */
    public static function replace(array $values): void
    {
        self::$values = $values;
    }
}
