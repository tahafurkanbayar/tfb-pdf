<?php

declare(strict_types=1);

namespace App\Security;

/**
 * APP_KEY ile amaç bazlı HMAC-SHA256. Token'lar ve IP'ler veritabanında düz metin saklanmaz.
 */
final class Hmac
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $key,
    ) {
    }

    public function hash(string $purpose, string $value): string
    {
        // Doğrulama ilk kullanımda: anahtar eksikse uygulama yine de "yapılandırılmamış" sayfasını gösterebilir
        if (strlen($this->key) < 32) {
            throw new \InvalidArgumentException('APP_KEY must be at least 32 characters.');
        }

        return hash_hmac('sha256', $purpose . '|' . $value, $this->key);
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * URL ve veritabanında kullanılan 32 karakterlik rastgele public id.
     */
    public static function publicId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
