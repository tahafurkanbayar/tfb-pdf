<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\StorageException;
use App\Services\HashService;
use App\Services\StorageService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

final class StorageServiceTest extends TestCase
{
    private string $root;

    private StorageService $storage;

    private const DOC = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        $this->root = TempDirectory::create('storage');
        $this->storage = new StorageService($this->root);
        self::assertSame([], $this->storage->ensureDirectories());
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testPathLayout(): void
    {
        self::assertSame('documents/01/' . self::DOC . '/original.pdf', StorageService::originalPath(self::DOC, 'PDF'));
        self::assertSame('versions/01/' . self::DOC . '/v001.pdf', StorageService::versionPath(self::DOC, 1));
        self::assertSame('versions/01/' . self::DOC . '/v042.pdf', StorageService::versionPath(self::DOC, 42));
        self::assertSame('v1000.pdf', StorageService::versionFilename(1000));
        self::assertFileExists($this->root . '/.htaccess');
    }

    public function testRejectsInvalidDocumentIdsAndExtensions(): void
    {
        foreach (['../../etc', 'ABCDEF', str_repeat('g', 32), ''] as $bad) {
            try {
                StorageService::originalPath($bad, 'pdf');
                self::fail('Expected rejection: ' . $bad);
            } catch (StorageException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(StorageException::class);
        StorageService::originalPath(self::DOC, 'p/h');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function maliciousPaths(): iterable
    {
        yield 'parent' => ['documents/../../.env'];
        yield 'parent middle' => ['versions/01/../../../x'];
        yield 'absolute' => ['/etc/passwd'];
        yield 'windows absolute' => ['C:\\Windows\\win.ini'];
        yield 'backslash' => ['documents\\..\\x'];
        yield 'null byte' => ["documents/x\0.pdf"];
        yield 'unknown root' => ['logs/app.log'];
        yield 'sessions' => ['sessions/sess_x'];
        yield 'hidden file' => ['documents/.htaccess'];
        yield 'bare root' => ['documents'];
        yield 'empty' => [''];
        yield 'uppercase' => ['documents/AB/x.pdf'];
    }

    #[DataProvider('maliciousPaths')]
    public function testResolveRejectsTraversal(string $path): void
    {
        $this->expectException(StorageException::class);
        $this->storage->resolve($path);
    }

    public function testMoveIntoPlaceNeverOverwrites(): void
    {
        $hash = new HashService();
        $source = $this->root . '/temporary/src.bin';
        file_put_contents($source, 'ilk içerik');
        $target = StorageService::originalPath(self::DOC, 'pdf');

        $this->storage->moveIntoPlace($source, $target);
        self::assertFileDoesNotExist($source);
        self::assertTrue($this->storage->exists($target));
        $originalHash = $hash->file($this->storage->resolve($target));

        $second = $this->root . '/temporary/src2.bin';
        file_put_contents($second, 'saldırgan içerik');
        try {
            $this->storage->moveIntoPlace($second, $target);
            self::fail('Overwrite must be refused');
        } catch (StorageException) {
            self::assertSame($originalHash, $hash->file($this->storage->resolve($target)));
            self::assertFileExists($second, 'Reddedilen kaynak dosya yerinde kalmalı');
        }

        // Yarım kalmış .part dosyası bırakılmamalı
        self::assertCount(1, glob(dirname($this->storage->resolve($target)) . '/*') ?: []);
    }

    public function testDeleteDocumentFilesRemovesOnlyThatDocument(): void
    {
        $other = 'ffffffffffffffffffffffffffffffff';
        $this->storage->putContents(StorageService::originalPath(self::DOC, 'pdf'), 'a');
        $this->storage->putContents(StorageService::versionPath(self::DOC, 1), 'b');
        $this->storage->putContents(StorageService::originalPath($other, 'pdf'), 'c');

        $this->storage->deleteDocumentFiles(self::DOC);

        self::assertFalse($this->storage->exists(StorageService::originalPath(self::DOC, 'pdf')));
        self::assertFalse($this->storage->exists(StorageService::versionPath(self::DOC, 1)));
        self::assertTrue($this->storage->exists(StorageService::originalPath($other, 'pdf')));
    }

    public function testTempDirectoriesAndPurge(): void
    {
        $dir = $this->storage->createTempDirectory();
        file_put_contents($dir . '/x', 'x');
        touch($dir, time() - 7200);

        $fresh = $this->storage->createTempDirectory();

        self::assertSame(1, $this->storage->purgeOlderThan('temporary', 3600));
        self::assertDirectoryDoesNotExist($dir);
        self::assertDirectoryExists($fresh);

        $this->storage->deleteTempDirectory($fresh);
        self::assertDirectoryDoesNotExist($fresh);

        // Temporary dışındaki bir dizin deleteTempDirectory ile silinemez
        $this->storage->deleteTempDirectory($this->root . '/documents');
        self::assertDirectoryExists($this->root . '/documents');

        $this->expectException(StorageException::class);
        $this->storage->purgeOlderThan('documents', 0);
    }

    public function testHashService(): void
    {
        $file = $this->root . '/temporary/h.txt';
        file_put_contents($file, 'abc');
        $hash = new HashService();

        self::assertSame('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', $hash->file($file));
        self::assertTrue($hash->verify($file, 'BA7816BF8F01CFEA414140DE5DAE2223B00361A396177A9CB410FF61F20015AD'));
        self::assertFalse($hash->verify($file, str_repeat('0', 64)));
        self::assertTrue(HashService::isValid(hash('sha256', 'x')));
        self::assertFalse(HashService::isValid('xyz'));
    }
}
