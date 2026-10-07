<?php

declare(strict_types=1);

namespace App\Core;

/**
 * URL üretimi. Uygulama alt dizinde (http://localhost/tfb-pdf) veya alan adı kökünde çalışabilir.
 */
final class Url
{
    public function __construct(
        private readonly string $baseUrl,
        private string $locale = 'tr',
    ) {
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * Taban URL'nin yol kısmı ("/tfb-pdf" veya "").
     */
    public function basePath(): string
    {
        return rtrim((string) parse_url($this->baseUrl, PHP_URL_PATH), '/');
    }

    public function base(): string
    {
        return $this->baseUrl;
    }

    /**
     * Uygulama içi mutlak yol: to('/api/documents') → "/tfb-pdf/api/documents"
     *
     * @param array<string, string|int> $query
     */
    public function to(string $path, array $query = []): string
    {
        $url = $this->basePath() . '/' . ltrim($path, '/');

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    /**
     * Dil önekli sayfa yolu: page('/tools/merge') → "/tfb-pdf/tr/tools/merge"
     *
     * @param array<string, string|int> $query
     */
    public function page(string $path = '/', array $query = [], ?string $locale = null): string
    {
        $path = '/' . trim($path, '/');
        $full = '/' . ($locale ?? $this->locale) . ($path === '/' ? '/' : $path);

        return $this->to($full, $query);
    }

    /**
     * E-posta gibi dış bağlantılar için tam URL.
     *
     * @param array<string, string|int> $query
     */
    public function absolute(string $path, array $query = []): string
    {
        $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', $this->baseUrl) ?? '';

        return $origin . $this->to($path, $query);
    }

    public function asset(string $path): string
    {
        $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
        $version = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';

        return $this->to('/assets/' . ltrim($path, '/'), ['v' => $version]);
    }

    /**
     * Aynı sayfanın başka dildeki karşılığı (dil değiştirici için).
     * "/tr/tools/merge" → "/en/tools/merge"
     *
     * @param list<string> $locales
     */
    public static function swapLocale(string $path, string $newLocale, array $locales): string
    {
        $segments = explode('/', ltrim($path, '/'), 2);
        if (in_array($segments[0], $locales, true)) {
            return '/' . $newLocale . '/' . ($segments[1] ?? '');
        }

        return '/' . $newLocale . '/';
    }

    /**
     * Açık yönlendirme (open redirect) koruması: yalnızca uygulama içi yollar.
     */
    public static function isSafeInternalPath(string $path): bool
    {
        return $path !== ''
            && $path[0] === '/'
            && !str_starts_with($path, '//')
            && !str_contains($path, '\\')
            && !preg_match('/[\x00-\x1F\x7F]/', $path);
    }
}
