<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Gerekli harici araç (Ghostscript, LibreOffice, Tesseract) veya PHP eklentisi mevcut değil.
 */
final class ToolUnavailableException extends AppException
{
    public function category(): ErrorCategory
    {
        return ErrorCategory::ToolUnavailable;
    }
}
