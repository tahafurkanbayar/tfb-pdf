<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\StorageException;

/**
 * Özel (web'e kapalı) dosya deposu (spec §15–17).
 *
 * - Veritabanında yalnızca depo köküne göre GÖRELİ yollar saklanır ("versions/ab/<id>/v001.pdf").
 * - Tüm yollar bu sınıfın ürettiği sabit kalıplardan gelir; kullanıcı girdisi yol olarak kullanılmaz.
 * - resolve() her göreli yolu doğrular: "..", mutlak yol, ters bölü, null byte, izin dışı karakter reddedilir
 *   ve çözülen yolun depo kökü içinde kaldığı kontrol edilir.
 * - Dosyalar bir kez yazılır: hedef varsa yazma reddedilir (immutable versioning).
 */
final class StorageService
{
    public const DIRECTORIES = ['documents', 'versions', 'previews', 'temporary', 'exports', 'signatures', 'sessions', 'logs', 'cache'];

    private const RELATIVE_PATTERN = '#^(documents|versions|previews|temporary|exports|cache|signatures)(/[a-z0-9][a-z0-9._\-]{0,63}){1,4}$#';

    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Eksik dizinleri ve web erişim engelini oluşturur (kurulum ve kontrol için).
     *
     * @return list<string> Yazılamayan dizinler
     */
    public function ensureDirectories(): array
    {
        $problems = [];
        foreach (self::DIRECTORIES as $dir) {
            $path = $this->root . '/' . $dir;
            if (!is_dir($path) && !@mkdir($path, 0750, true) && !is_dir($path)) {
                $problems[] = $dir;
                continue;
            }
            if (!is_writable($path)) {
                $problems[] = $dir;
            }
        }

        $htaccess = $this->root . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }

        return $problems;
    }

    // --- Yol üretimi (tek doğruluk kaynağı) ---

    public static function originalPath(string $documentId, string $extension): string
    {
        return 'documents/' . self::shard($documentId) . '/original.' . self::safeExtension($extension);
    }

    public static function versionPath(string $documentId, int $versionNumber, string $extension = 'pdf'): string
    {
        if ($versionNumber < 1 || $versionNumber > 999999) {
            throw new StorageException('Invalid version number: ' . $versionNumber);
        }

        return 'versions/' . self::shard($documentId) . '/' . self::versionFilename($versionNumber, $extension);
    }

    /**
     * v001.pdf, v002.pdf ... v999.pdf, v1000.pdf
     */
    public static function versionFilename(int $versionNumber, string $extension = 'pdf'): string
    {
        return sprintf('v%03d.%s', $versionNumber, self::safeExtension($extension));
    }

    public static function previewDirectory(string $documentId, int $versionNumber): string
    {
        return 'previews/' . self::shard($documentId) . '/' . $versionNumber;
    }

    /**
     * Bir belgeye ait tüm dizinler (silme için).
     *
     * @return list<string>
     */
    public static function documentDirectories(string $documentId): array
    {
        $shard = self::shard($documentId);

        return ['documents/' . $shard, 'versions/' . $shard, 'previews/' . $shard, 'signatures/' . $shard];
    }

    /**
     * documents/ab/<id> — ilk iki karakterle alt dizin (tek dizinde çok sayıda girdiyi önler).
     */
    private static function shard(string $documentId): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $documentId)) {
            throw new StorageException('Invalid document id for storage path.');
        }

        return substr($documentId, 0, 2) . '/' . $documentId;
    }

    private static function safeExtension(string $extension): string
    {
        $extension = strtolower($extension);
        if (!preg_match('/^[a-z0-9]{1,5}$/', $extension)) {
            throw new StorageException('Invalid file extension for storage path.');
        }

        return $extension;
    }

    // --- Güvenli yol çözümleme ---

    /**
     * Göreli depo yolunu doğrular ve mutlak yola çevirir.
     */
    public function resolve(string $relative): string
    {
        if (!self::isValidRelativePath($relative)) {
            throw new StorageException('Rejected storage path.');
        }

        $absolute = $this->root . '/' . $relative;

        // Sembolik bağlantı vb. ile kök dışına çıkılmadığını doğrula (var olan en yakın üst dizin üzerinden)
        $check = $absolute;
        while (!file_exists($check) && $check !== $this->root && str_contains($check, '/')) {
            $check = dirname($check);
        }
        $realRoot = realpath($this->root);
        $realCheck = realpath($check);
        if ($realRoot === false || $realCheck === false) {
            throw new StorageException('Storage root is not available.');
        }
        $realRoot = rtrim(str_replace('\\', '/', $realRoot), '/');
        $realCheck = str_replace('\\', '/', $realCheck);
        if ($realCheck !== $realRoot && !str_starts_with($realCheck, $realRoot . '/')) {
            throw new StorageException('Storage path escapes the storage root.');
        }

        return $absolute;
    }

    public static function isValidRelativePath(string $relative): bool
    {
        return !str_contains($relative, '..')
            && !str_contains($relative, "\0")
            && !str_contains($relative, '\\')
            && preg_match(self::RELATIVE_PATTERN, $relative) === 1;
    }

    // --- Dosya işlemleri ---

    public function exists(string $relative): bool
    {
        return is_file($this->resolve($relative));
    }

    public function size(string $relative): int
    {
        $size = @filesize($this->resolve($relative));
        if ($size === false) {
            throw new StorageException('Cannot read file size.');
        }

        return $size;
    }

    /**
     * Mevcut bir dosyayı (yükleme sonrası temp dosya, işlem çıktısı) kalıcı konumuna taşır.
     * Hedef zaten varsa REDDEDER — hiçbir dosyanın üzerine yazılmaz.
     */
    public function moveIntoPlace(string $sourceAbsolute, string $relativeTarget): void
    {
        $target = $this->resolve($relativeTarget);
        $this->makeParent($target);

        if (file_exists($target)) {
            throw new StorageException('Refusing to overwrite existing file: ' . $relativeTarget);
        }

        // Önce aynı dizinde geçici ada taşı, sonra atomik olarak hedef ada çevir
        $partial = $target . '.part-' . bin2hex(random_bytes(4));
        $moved = @rename($sourceAbsolute, $partial) || (@copy($sourceAbsolute, $partial) && @unlink($sourceAbsolute));
        if (!$moved) {
            @unlink($partial);
            throw new StorageException('Cannot move file into storage: ' . $relativeTarget);
        }

        if (file_exists($target)) {
            @unlink($partial);
            throw new StorageException('Refusing to overwrite existing file: ' . $relativeTarget);
        }

        if (!@rename($partial, $target)) {
            @unlink($partial);
            throw new StorageException('Cannot finalize file in storage: ' . $relativeTarget);
        }

        @chmod($target, 0640);
    }

    /**
     * Küçük içerik yazımı (ör. imza görseli). Üzerine yazmaz.
     */
    public function putContents(string $relativeTarget, string $contents): void
    {
        $tmp = $this->createTempDirectory() . '/blob';
        if (@file_put_contents($tmp, $contents) === false) {
            throw new StorageException('Cannot write temporary file.');
        }
        $this->moveIntoPlace($tmp, $relativeTarget);
        $this->deleteTempDirectory(dirname($tmp));
    }

    public function delete(string $relative): void
    {
        $path = $this->resolve($relative);
        if (is_file($path) && !@unlink($path)) {
            throw new StorageException('Cannot delete file: ' . $relative);
        }
    }

    /**
     * Bir belgenin tüm dosyalarını kalıcı olarak siler.
     */
    public function deleteDocumentFiles(string $documentId): void
    {
        foreach (self::documentDirectories($documentId) as $dir) {
            $path = $this->resolve($dir);
            if (is_dir($path)) {
                $this->removeTree($path);
            }
        }
    }

    // --- Geçici dizinler ---

    /**
     * İşlem için benzersiz geçici dizin: storage/temporary/<rastgele>
     */
    public function createTempDirectory(): string
    {
        $relative = 'temporary/' . bin2hex(random_bytes(12));
        $path = $this->resolve($relative);
        if (!@mkdir($path, 0750, true)) {
            throw new StorageException('Cannot create temporary directory.');
        }

        return $path;
    }

    public function deleteTempDirectory(string $absolute): void
    {
        $tempRoot = $this->root . '/temporary/';
        $absolute = str_replace('\\', '/', $absolute);
        if (str_starts_with($absolute, $tempRoot) && preg_match('#^[a-f0-9]{24}$#', substr($absolute, strlen($tempRoot))) && is_dir($absolute)) {
            $this->removeTree($absolute);
        }
    }

    /**
     * Belirli yaştan eski girdileri siler (temporary, exports, previews temizliği).
     *
     * @return int Silinen girdi sayısı
     */
    public function purgeOlderThan(string $directory, int $maxAgeSeconds, int $limit = 500): int
    {
        if (!in_array($directory, ['temporary', 'exports', 'previews', 'cache'], true)) {
            throw new StorageException('Purge not allowed for: ' . $directory);
        }

        $base = $this->resolve($directory . '/x');
        $base = dirname($base);
        $threshold = time() - $maxAgeSeconds;
        $deleted = 0;

        foreach (new \DirectoryIterator($base) as $entry) {
            if ($entry->isDot() || str_starts_with($entry->getFilename(), '.') || $deleted >= $limit) {
                continue;
            }
            if ($entry->getMTime() < $threshold) {
                $entry->isDir() ? $this->removeTree($entry->getPathname()) : @unlink($entry->getPathname());
                $deleted++;
            }
        }

        return $deleted;
    }

    public function freeSpace(): ?int
    {
        $free = function_exists('disk_free_space') ? @disk_free_space($this->root) : false;

        return $free === false ? null : (int) $free;
    }

    private function makeParent(string $absolute): void
    {
        $dir = dirname($absolute);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new StorageException('Cannot create directory in storage.');
        }
    }

    /**
     * Dizin ağacını siler. Sembolik bağlantıları takip etmez.
     */
    private function removeTree(string $path): void
    {
        $realRoot = realpath($this->root);
        $realPath = realpath($path);
        if ($realRoot === false || $realPath === false || !str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new StorageException('Refusing to delete outside storage root.');
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }
}
