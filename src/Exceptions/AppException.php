<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Uygulama içi tüm beklenen hataların temeli.
 *
 * getMessage() teknik ayrıntı içerebilir ve yalnızca log'a yazılır.
 * Kullanıcıya her zaman messageKey() çevirisi gösterilir.
 */
abstract class AppException extends \RuntimeException
{
    /**
     * @param array<string, string|int|float> $replace Çeviri yer tutucuları
     * @param array<string, string> $fieldErrors Alan => çeviri anahtarı (form doğrulama)
     */
    public function __construct(
        string $technicalMessage = '',
        private readonly ?string $messageKey = null,
        private readonly array $replace = [],
        private readonly array $fieldErrors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($technicalMessage, 0, $previous);
    }

    abstract public function category(): ErrorCategory;

    public function messageKey(): string
    {
        return $this->messageKey ?? $this->category()->defaultMessageKey();
    }

    /**
     * @return array<string, string|int|float>
     */
    public function replace(): array
    {
        return $this->replace;
    }

    /**
     * @return array<string, string>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }

    public function httpStatus(): int
    {
        return $this->category()->httpStatus();
    }
}
