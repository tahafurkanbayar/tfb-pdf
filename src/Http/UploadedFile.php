<?php

declare(strict_types=1);

namespace App\Http;

/**
 * $_FILES girdisinin değiştirilemez temsili. Kullanıcının verdiği ad ve MIME tipi
 * yalnızca bilgi amaçlıdır; doğrulama gerçek dosya içeriği üzerinden yapılır.
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $tmpPath,
        public readonly string $clientName,
        public readonly string $clientMime,
        public readonly int $size,
        public readonly int $error,
        private readonly bool $isHttpUpload = true,
    ) {
    }

    /**
     * Testler ve CLI için: gerçek HTTP yüklemesi olmayan bir dosyadan.
     */
    public static function fromPath(string $path, string $clientName, string $clientMime = 'application/octet-stream'): self
    {
        return new self($path, $clientName, $clientMime, (int) @filesize($path), UPLOAD_ERR_OK, false);
    }

    public function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    /**
     * Gerçek HTTP yüklemesinde move_uploaded_file güvenlik kontrolü uygulanır.
     */
    public function isValidUpload(): bool
    {
        return $this->isOk() && ($this->isHttpUpload ? is_uploaded_file($this->tmpPath) : is_file($this->tmpPath));
    }

    public function moveTo(string $target): bool
    {
        if ($this->isHttpUpload) {
            return move_uploaded_file($this->tmpPath, $target);
        }

        return copy($this->tmpPath, $target);
    }

    /**
     * $_FILES['alan'] tekil veya çoklu (name[]) olabilir.
     *
     * @param array<string, mixed> $spec
     * @return list<self>
     */
    public static function normalize(array $spec): array
    {
        if (!isset($spec['tmp_name'])) {
            return [];
        }

        if (!is_array($spec['tmp_name'])) {
            return [new self(
                (string) $spec['tmp_name'],
                (string) ($spec['name'] ?? ''),
                (string) ($spec['type'] ?? ''),
                (int) ($spec['size'] ?? 0),
                (int) ($spec['error'] ?? UPLOAD_ERR_NO_FILE),
            )];
        }

        $files = [];
        foreach (array_keys($spec['tmp_name']) as $i) {
            if (is_array($spec['tmp_name'][$i])) {
                continue; // Daha derin iç içe yapılar desteklenmez
            }
            $files[] = new self(
                (string) $spec['tmp_name'][$i],
                (string) ($spec['name'][$i] ?? ''),
                (string) ($spec['type'][$i] ?? ''),
                (int) ($spec['size'][$i] ?? 0),
                (int) ($spec['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            );
        }

        return $files;
    }
}
