<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * Çeviri anahtarları "grup.anahtar[.alt]" biçimindedir. Grup, resources/lang/{locale}/{grup}.php
 * dosyasıdır ve iç içe dizi döndürür.
 *
 *   __('upload.success')
 *   __('common.page_count', ['count' => 3])          → ":count" yer tutucusu
 *   trans_choice('common.page_count', 3)             → "1 page|:count pages"
 */
final class Translator
{
    /** @var array<string, array<string, array<mixed>>> locale => group => dizi */
    private array $loaded = [];

    /** @var array<string, true> */
    private array $missing = [];

    public function __construct(
        private readonly string $langPath,
        private string $locale,
        private readonly string $fallbackLocale = 'tr',
    ) {
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * @param array<string, string|int|float> $replace
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $line = $this->find($key, $locale ?? $this->locale)
            ?? $this->find($key, $this->fallbackLocale);

        if ($line === null) {
            $this->missing[$key] = true;

            return $key;
        }

        return $this->replace($line, $replace);
    }

    /**
     * Çoğul seçimi: "tekil|çoğul". Türkçede tek biçim yeterlidir (| kullanılmaz).
     *
     * @param array<string, string|int|float> $replace
     */
    public function choice(string $key, int $count, array $replace = []): string
    {
        $line = $this->get($key, $replace + ['count' => $count]);
        if (!str_contains($line, '|')) {
            return $line;
        }

        [$one, $other] = array_pad(explode('|', $line, 2), 2, '');

        return $count === 1 ? $one : $other;
    }

    public function has(string $key, ?string $locale = null): bool
    {
        return $this->find($key, $locale ?? $this->locale) !== null;
    }

    /**
     * Bir grubun tamamını düz (dot) anahtarlar halinde döndürür. JS'e aktarım ve kontrol için.
     *
     * @return array<string, string>
     */
    public function group(string $group, ?string $locale = null): array
    {
        $flat = [];
        self::flatten($this->load($locale ?? $this->locale, $group), $group, $flat);

        return $flat;
    }

    /**
     * Bu istek sırasında bulunamayan anahtarlar (debug logları için).
     *
     * @return list<string>
     */
    public function missingKeys(): array
    {
        return array_keys($this->missing);
    }

    /**
     * @param array<mixed> $array
     * @param array<string, string> $out
     */
    public static function flatten(array $array, string $prefix, array &$out): void
    {
        foreach ($array as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                self::flatten($value, $full, $out);
            } else {
                $out[$full] = (string) $value;
            }
        }
    }

    private function find(string $key, string $locale): ?string
    {
        $segments = explode('.', $key);
        if (count($segments) < 2) {
            return null;
        }

        $value = $this->load($locale, array_shift($segments));
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<mixed>
     */
    private function load(string $locale, string $group): array
    {
        if (isset($this->loaded[$locale][$group])) {
            return $this->loaded[$locale][$group];
        }

        $data = [];
        // Grup ve locale adları yalnızca güvenli karakterlerden oluşabilir (dosya yolu olarak kullanılır)
        if (preg_match('/^[a-z]{2}$/', $locale) && preg_match('/^[a-z_]+$/', $group)) {
            $file = $this->langPath . '/' . $locale . '/' . $group . '.php';
            if (is_file($file)) {
                $loaded = require $file;
                $data = is_array($loaded) ? $loaded : [];
            }
        }

        return $this->loaded[$locale][$group] = $data;
    }

    /**
     * @param array<string, string|int|float> $replace
     */
    private function replace(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        // Uzun anahtarlar önce: ":count" ile ":counter" çakışmasın
        uksort($replace, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($replace as $name => $value) {
            $line = str_replace(':' . $name, (string) $value, $line);
        }

        return $line;
    }
}
