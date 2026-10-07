<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Dosya sistemi hatası (yazma, taşıma, izin, disk).
 */
final class StorageException extends AppException
{
    public function category(): ErrorCategory
    {
        return ErrorCategory::Storage;
    }
}
