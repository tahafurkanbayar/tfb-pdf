<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCategory;
use App\Http\Request;
use App\Http\Response;

/**
 * Kurulum tamamlanmadan (APP_KEY veya veritabanı yapılandırması eksik) ham hata yerine
 * anlaşılır bir "yapılandırılmamış" sayfası gösterir. Kurulum sayfası (/install) bundan muaftır.
 */
final class EnsureConfigured implements Middleware
{
    public function __construct(
        private readonly Config $config,
        private readonly Database $db,
    ) {
    }

    public function process(Request $request, \Closure $next): Response
    {
        $exempt = $request->path === '/install' || str_starts_with($request->path, '/install/');
        if (!$exempt && (strlen((string) $this->config->get('app.key')) < 32 || !$this->db->isConfigured())) {
            throw new class ('Application is not configured (APP_KEY or database settings missing)', 'errors.misconfigured') extends AppException {
                public function category(): ErrorCategory
                {
                    return ErrorCategory::Unexpected;
                }

                public function httpStatus(): int
                {
                    return 503;
                }
            };
        }

        return $next($request);
    }
}
