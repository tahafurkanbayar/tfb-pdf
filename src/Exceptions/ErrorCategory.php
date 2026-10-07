<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Hata kategorileri (spec §43). Her kategori kullanıcıya teknik olmayan bir mesajla
 * ve uygun HTTP durum koduyla eşlenir.
 */
enum ErrorCategory: string
{
    case Validation = 'validation';
    case Processing = 'processing';
    case Storage = 'storage';
    case Database = 'database';
    case Permission = 'permission';
    case ToolUnavailable = 'tool_unavailable';
    case NotFound = 'not_found';
    case RateLimited = 'rate_limited';
    case Unexpected = 'unexpected';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Validation => 422,
            self::Processing => 422,
            self::Permission => 403,
            self::NotFound => 404,
            self::RateLimited => 429,
            self::ToolUnavailable => 503,
            self::Storage, self::Database, self::Unexpected => 500,
        };
    }

    public function defaultMessageKey(): string
    {
        return match ($this) {
            self::Validation => 'errors.validation',
            self::Processing => 'errors.processing_failed',
            self::Storage => 'errors.storage',
            self::Database => 'errors.database',
            self::Permission => 'errors.permission_denied',
            self::ToolUnavailable => 'errors.tool_unavailable',
            self::NotFound => 'errors.not_found',
            self::RateLimited => 'errors.rate_limited',
            self::Unexpected => 'errors.unexpected',
        };
    }

    /**
     * Kullanıcı aynı isteği düzelterek / bekleyerek tekrar deneyebilir mi? (UI: recoverable vs fatal)
     */
    public function isRecoverable(): bool
    {
        return match ($this) {
            self::Validation, self::Processing, self::RateLimited => true,
            default => false,
        };
    }
}
