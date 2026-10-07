<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Veritabanı bağlantı veya sorgu hatası.
 */
final class DatabaseException extends AppException
{
    public function category(): ErrorCategory
    {
        return ErrorCategory::Database;
    }
}
