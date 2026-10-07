<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Belge, sürüm veya sayfa bulunamadı.
 */
final class NotFoundException extends AppException
{
    public function category(): ErrorCategory
    {
        return ErrorCategory::NotFound;
    }
}
