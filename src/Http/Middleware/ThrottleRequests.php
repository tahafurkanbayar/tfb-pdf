<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Security\RateLimiter;

/**
 * Durum değiştiren API isteklerini eylem türüne göre sınırlar:
 *   upload     POST /api/documents
 *   operation  POST /api/operations/*
 *   signature  POST /api/signatures*, POST /api/sign/*
 *   preview    PUT  .../previews/* (küçük resim önbelleği; daha geniş sınır)
 */
final class ThrottleRequests implements Middleware
{
    /**
     * @param array{upload: int, operation: int, signature: int} $limits Saatlik
     * @param list<string> $trustedProxies
     */
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly array $limits,
        private readonly array $trustedProxies,
    ) {
    }

    public function process(Request $request, \Closure $next): Response
    {
        $action = self::action($request);
        if ($action !== null) {
            $limit = $action === 'preview' ? max(0, $this->limits['operation']) * 20 : $this->limits[$action];
            $this->limiter->hit($action, $request->ip($this->trustedProxies), $limit);
        }

        return $next($request);
    }

    public static function action(Request $request): ?string
    {
        $path = $request->path;

        return match (true) {
            $request->isMethod('POST') && $path === '/api/documents' => 'upload',
            $request->isMethod('POST') && str_starts_with($path, '/api/operations/') => 'operation',
            $request->isMethod('POST') && (str_starts_with($path, '/api/signatures') || str_starts_with($path, '/api/sign/')) => 'signature',
            $request->isMethod('PUT') && str_contains($path, '/previews/') => 'preview',
            default => null,
        };
    }
}
