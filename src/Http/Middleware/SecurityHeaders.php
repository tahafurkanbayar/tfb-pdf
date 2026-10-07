<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;

/**
 * Güvenlik başlıkları. Hata yanıtları dahil her yanıta uygulanır (Application::finalize).
 * CSP yalnızca kendi origin'imizden script/style yüklenmesine izin verir:
 * inline script yok, üçüncü taraf CDN yok, analytics yok.
 */
final class SecurityHeaders
{
    public const CSP = "default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self'; "
        . "img-src 'self' data: blob:; "
        . "font-src 'self' data:; "
        . "worker-src 'self' blob:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'";

    public static function apply(Response $response): void
    {
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => self::CSP,
            'Cache-Control' => 'no-store, private',
        ];

        foreach ($defaults as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response->setHeader($name, $value);
            }
        }
    }
}
