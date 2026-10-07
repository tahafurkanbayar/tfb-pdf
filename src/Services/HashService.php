<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\StorageException;

/**
 * SHA-256 dosya özetleri (spec §18). Dosyayı belleğe almadan, akış halinde hesaplar.
 *
 * Not: Özet yalnızca bütünlük, audit ve sürüm kontrolü içindir; tek başına hukuki geçerlilik
 * veya belge doğrulaması anlamına gelmez.
 */
final class HashService
{
    public function file(string $path): string
    {
        $hash = is_file($path) ? hash_file('sha256', $path) : false;
        if ($hash === false) {
            throw new StorageException('Cannot hash file: ' . basename($path));
        }

        return $hash;
    }

    public function verify(string $path, string $expected): bool
    {
        return is_file($path) && hash_equals(strtolower($expected), $this->file($path));
    }

    public static function isValid(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $hash);
    }
}
