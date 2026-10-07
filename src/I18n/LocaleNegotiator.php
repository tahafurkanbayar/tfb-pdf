<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * Dil seçimi önceliği (spec §6):
 *   1. URL öneki (/tr/..., /en/...) — sayfa istekleri için
 *   2. Kullanıcının açık tercihi (cookie)
 *   3. Tarayıcı dili (Accept-Language)
 *   4. Varsayılan: Türkçe
 */
final class LocaleNegotiator
{
    /**
     * @param list<string> $supported
     */
    public function __construct(
        private readonly array $supported,
        private readonly string $default,
    ) {
    }

    public function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, $this->supported, true);
    }

    public function negotiate(?string $urlLocale, ?string $cookieLocale, ?string $acceptLanguage): string
    {
        if ($this->isSupported($urlLocale)) {
            return (string) $urlLocale;
        }
        if ($this->isSupported($cookieLocale)) {
            return (string) $cookieLocale;
        }

        return $this->fromAcceptLanguage($acceptLanguage) ?? $this->default;
    }

    /**
     * "en-US,en;q=0.9,tr;q=0.8" → desteklenen en yüksek öncelikli dil.
     */
    public function fromAcceptLanguage(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $candidates = [];
        foreach (explode(',', $header) as $index => $part) {
            $pieces = explode(';', trim($part));
            $tag = strtolower(trim($pieces[0]));
            if ($tag === '' || $tag === '*') {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $param) {
                if (preg_match('/^\s*q\s*=\s*([01](?:\.\d{0,3})?)\s*$/', $param, $m)) {
                    $quality = (float) $m[1];
                }
            }
            if ($quality <= 0) {
                continue;
            }

            $primary = explode('-', $tag)[0];
            // Aynı kalitede olanlarda başlıktaki sıra korunur
            $candidates[] = ['locale' => $primary, 'q' => $quality, 'i' => $index];
        }

        usort($candidates, static fn (array $a, array $b): int => [$b['q'], $a['i']] <=> [$a['q'], $b['i']]);

        foreach ($candidates as $candidate) {
            if ($this->isSupported($candidate['locale'])) {
                return $candidate['locale'];
            }
        }

        return null;
    }
}
