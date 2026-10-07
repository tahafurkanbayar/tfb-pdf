<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Csrf;
use App\Exceptions\PermissionException;
use App\Http\Request;
use App\Http\Response;

/**
 * Durum değiştiren tüm isteklerde (POST/PUT/PATCH/DELETE) CSRF token zorunlu.
 * Ek savunma: Origin başlığı varsa kendi host'umuzla eşleşmeli.
 */
final class VerifyCsrfToken implements Middleware
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function process(Request $request, \Closure $next): Response
    {
        if ($request->isStateChanging()) {
            $origin = $request->header('origin');
            if ($origin !== null && $origin !== 'null' && !$this->sameHost($origin, (string) ($request->server['HTTP_HOST'] ?? ''))) {
                throw new PermissionException('Cross-origin request blocked: ' . $origin, 'errors.csrf');
            }

            $token = $request->header('x-csrf-token') ?? $request->input('_token');
            if (!$this->csrf->validate(is_string($token) ? $token : null)) {
                throw new PermissionException('CSRF token mismatch', 'errors.csrf');
            }
        }

        return $next($request);
    }

    private function sameHost(string $origin, string $host): bool
    {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);
        $full = $originHost . ($originPort !== null ? ':' . $originPort : '');

        return $host !== '' && (strcasecmp($full, $host) === 0 || strcasecmp((string) $originHost, $host) === 0);
    }
}
