<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Document;
use App\Domain\DocumentVersion;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Services\StorageService;
use App\Services\ThumbnailService;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

final class ThumbnailServiceTest extends TestCase
{
    private string $root;

    private ThumbnailService $service;

    private Document $document;

    private DocumentVersion $version;

    protected function setUp(): void
    {
        if (!ThumbnailService::available()) {
            self::markTestSkipped('GD eklentisi yok');
        }
        $this->root = TempDirectory::create('thumbs');
        $storage = new StorageService($this->root);
        $storage->ensureDirectories();
        $this->service = new ThumbnailService($storage);

        $id = str_repeat('ab', 16);
        $this->document = new Document(1, $id, str_repeat('0', 64), 'a.pdf', Document::SOURCE_PDF, 'application/pdf', '2026-01-01 00:00:00', '2026-01-01 00:00:00');
        $this->version = new DocumentVersion(1, 1, 0, null, 'original.pdf', 'documents/ab/' . $id . '/original.pdf', 'application/pdf', 100, str_repeat('a', 64), 3, null, '2026-01-01 00:00:00');
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            TempDirectory::remove($this->root);
        }
    }

    private static function image(int $w, int $h, string $type = 'png'): string
    {
        $gd = imagecreatetruecolor($w, $h);
        imagefill($gd, 0, 0, (int) imagecolorallocate($gd, 200, 30, 30));
        ob_start();
        $type === 'png' ? imagepng($gd) : imagejpeg($gd);

        return (string) ob_get_clean();
    }

    public function testStoresReencodedJpegAndListsIt(): void
    {
        // Polyglot denemesi: geçerli PNG'nin sonuna eklenmiş betik yeniden kodlamada atılmalı
        $this->service->store($this->document, $this->version, 2, self::image(150, 200) . '<?php echo 1; ?>');

        $path = $this->service->path($this->document, $this->version, 2);
        $stored = (string) file_get_contents($path);
        self::assertSame(IMAGETYPE_JPEG, getimagesizefromstring($stored)[2]);
        self::assertStringNotContainsString('<?php', $stored);
        self::assertSame([2], $this->service->cachedPages($this->document, $this->version));

        // İkinci gönderim mevcut dosyaya dokunmaz
        $before = hash_file('sha256', $path);
        $this->service->store($this->document, $this->version, 2, self::image(100, 100, 'jpeg'));
        self::assertSame($before, hash_file('sha256', $path));
    }

    public function testRejectsInvalidImages(): void
    {
        foreach (['', 'not an image', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', self::image(800, 800)] as $bad) {
            try {
                $this->service->store($this->document, $this->version, 1, $bad);
                self::fail('Expected rejection');
            } catch (ValidationException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame([], $this->service->cachedPages($this->document, $this->version));
    }

    public function testPageBoundsAreEnforced(): void
    {
        foreach ([0, 4, -1] as $page) {
            try {
                $this->service->store($this->document, $this->version, $page, self::image(10, 10));
                self::fail('Expected not found');
            } catch (NotFoundException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(NotFoundException::class);
        $this->service->path($this->document, $this->version, 1);
    }
}
