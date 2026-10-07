<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * PDF işleme sırasında oluşan, dosyadan kaynaklanan hata.
 */
final class ProcessingException extends AppException
{
    public function category(): ErrorCategory
    {
        return ErrorCategory::Processing;
    }
}
