<?php

declare(strict_types=1);

namespace App\Services\Operations;

/**
 * İşlemcinin (processor) dönüşü.
 *
 * changed=false: işlem anlamlı bir çıktı üretmedi (ör. sıkıştırma boyutu küçültemedi);
 * bu durumda yeni sürüm OLUŞTURULMAZ ve kullanıcıya yapılmış gibi gösterilmez.
 */
final class ProcessResult
{
    /**
     * @param list<OperationOutput> $outputs
     * @param array<string, mixed> $meta Sonuç bilgileri (boyutlar, motor ayrıntısı ...)
     * @param list<string> $warnings Çeviri anahtarları
     */
    public function __construct(
        public readonly array $outputs,
        public readonly string $engine,
        public readonly array $meta = [],
        public readonly array $warnings = [],
        public readonly bool $changed = true,
    ) {
    }
}
