<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Girdi doğrulama hatası (dosya, form alanı, sayfa aralığı ...).
 */
final class ValidationException extends AppException
{
    public function category(): ErrorCategory
    {
        return ErrorCategory::Validation;
    }
}
