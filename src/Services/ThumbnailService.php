<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Document;
use App\Domain\DocumentVersion;
use App\Exceptions\NotFoundException;
use App\Exceptions\ToolUnavailableException;
use App\Exceptions\ValidationException;

/**
 * Sayfa küçük resmi önbelleği (spec §13).
 *
 * Paylaşımlı hostingde sunucu tarafında PDF görüntüleme aracı (Ghostscript/Imagick) bulunmayabilir.
 * Bu yüzden küçük resimler tarayıcıda PDF.js ile bir kez üretilir ve sunucuya gönderilir; sonraki
 * açılışlarda PDF'in tamamını indirip yeniden çizmek yerine bu küçük JPEG'ler kullanılır.
 * Gönderilen görsel GD ile yeniden kodlanır (meta veri ve gömülü içerik atılır) ve boyutu sınırlanır.
 */
final class ThumbnailService
{
    public const MAX_DIMENSION = 400;

    public const MAX_BYTES = 400 * 1024;

    public function __construct(private readonly StorageService $storage)
    {
    }

    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    private static function relativePath(Document $document, DocumentVersion $version, int $page): string
    {
        return StorageService::previewDirectory($document->publicId, $version->versionNumber) . '/p' . $page . '.jpg';
    }

    private function assertPage(DocumentVersion $version, int $page): void
    {
        if (!$version->isPdf() || $page < 1 || $page > (int) $version->pageCount) {
            throw new NotFoundException('Page out of range');
        }
    }

    /**
     * @return list<int> Önbellekte bulunan sayfa numaraları
     */
    public function cachedPages(Document $document, DocumentVersion $version): array
    {
        if (!$version->isPdf()) {
            return [];
        }

        $dir = $this->storage->resolve(StorageService::previewDirectory($document->publicId, $version->versionNumber));
        $pages = [];
        foreach (glob($dir . '/p*.jpg') ?: [] as $file) {
            if (preg_match('/p(\d+)\.jpg$/', $file, $m)) {
                $pages[] = (int) $m[1];
            }
        }
        sort($pages);

        return $pages;
    }

    public function path(Document $document, DocumentVersion $version, int $page): string
    {
        $this->assertPage($version, $page);
        $path = $this->storage->resolve(self::relativePath($document, $version, $page));
        if (!is_file($path)) {
            throw new NotFoundException('Thumbnail not cached');
        }

        return $path;
    }

    /**
     * Tarayıcının ürettiği küçük resmi doğrulayıp önbelleğe yazar. Zaten varsa dokunmaz.
     */
    public function store(Document $document, DocumentVersion $version, int $page, string $image): void
    {
        if (!self::available()) {
            throw new ToolUnavailableException('GD not available');
        }
        $this->assertPage($version, $page);

        $relative = self::relativePath($document, $version, $page);
        if ($this->storage->exists($relative)) {
            return;
        }

        if ($image === '' || strlen($image) > self::MAX_BYTES) {
            throw new ValidationException('Invalid thumbnail size', 'errors.validation');
        }

        $info = @getimagesizefromstring($image);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            throw new ValidationException('Invalid thumbnail image', 'errors.validation');
        }

        $gd = @imagecreatefromstring($image);
        if ($gd === false) {
            throw new ValidationException('Undecodable thumbnail', 'errors.validation');
        }

        // Saydam PNG/WebP beyaz zemine
        $canvas = imagecreatetruecolor(imagesx($gd), imagesy($gd));
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $gd, 0, 0, 0, 0, imagesx($gd), imagesy($gd));

        ob_start();
        imagejpeg($canvas, null, 80);
        $jpeg = (string) ob_get_clean();
        imagedestroy($gd);
        imagedestroy($canvas);

        try {
            $this->storage->putContents($relative, $jpeg);
        } catch (\App\Exceptions\StorageException $e) {
            // Aynı anda iki istek: diğeri yazdıysa sorun değil
            if (!$this->storage->exists($relative)) {
                throw $e;
            }
        }
    }
}
