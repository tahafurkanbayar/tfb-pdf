<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Yetkisiz erişim, CSRF doğrulama hatası.
 */
final class PermissionException extends AppException
{
    public function category(): ErrorCategory
    {
        return ErrorCategory::Permission;
    }
}
