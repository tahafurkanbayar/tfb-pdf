<?php

declare(strict_types=1);

/*
 * Global yardımcı fonksiyonlar. Yalnızca view'larda ve çok sık kullanılan
 * kısa çağrılar için; iş mantığı burada yer almaz.
 */

if (!function_exists('__')) {
    /**
     * Çeviri. Kullanıcıya gösterilen her metin bu fonksiyondan geçer.
     *
     * @param array<string, string|int|float> $replace
     */
    function __(string $key, array $replace = []): string
    {
        return \App\I18n\Lang::translator()->get($key, $replace);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * @param array<string, string|int|float> $replace
     */
    function trans_choice(string $key, int $count, array $replace = []): string
    {
        return \App\I18n\Lang::translator()->choice($key, $count, $replace);
    }
}

if (!function_exists('e')) {
    /**
     * HTML çıktısı için güvenli kaçış (XSS koruması).
     */
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
