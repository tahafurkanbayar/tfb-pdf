<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * FORCE_HTTPS=true ise HTTP isteklerini HTTPS'e yönlendirir. HSTS_ENABLED=true ise HSTS başlığı ekler.
 * .htaccess yönlendirmesi çalışmayan sunucular için PHP tarafında yedek.
 */
final class ForceHttps implements Middleware
{
    /**
     * @param list<string> $trustedProxies
     */
    public function __construct(
        private readonly bool $force,
        private readonly bool $hsts,
        private readonly array $trustedProxies,
    ) {
    }

    public function process(Request $request, \Closure $next): Response
    {
        $secure = $request->isSecure($this->trustedProxies);

        if ($this->force && !$secure) {
            $host = (string) ($request->server['HTTP_HOST'] ?? '');
            $uri = (string) ($request->server['REQUEST_URI'] ?? '/');
            if (preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $host)) {
                return Response::redirect('https://' . $host . $uri, 301);
            }
        }

        $response = $next($request);

        if ($this->hsts && $secure) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
