<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * İstek sınırı aşıldı.
 */
final class RateLimitException extends AppException
{
    public function category(): ErrorCategory
    {
        return ErrorCategory::RateLimited;
    }
}
