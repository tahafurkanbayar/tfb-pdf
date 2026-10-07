<?php

declare(strict_types=1);

/*
 * Global yardımcı fonksiyonlar. Yalnızca view'larda ve çok sık kullanılan
 * kısa çağrılar için; iş mantığı burada yer almaz.
 */

if (!function_exists('e')) {
    /**
     * HTML çıktısı için güvenli kaçış (XSS koruması).
     */
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
