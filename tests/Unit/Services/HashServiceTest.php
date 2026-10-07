<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\StorageException;
use App\Services\HashService;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

/**
 * SHA-256 (spec §18): bilinen test vektörleri, akış halinde hesaplama, doğrulama.
 */
final class HashServiceTest extends TestCase
{
    private string $dir;

    private HashService $hash;

    protected function setUp(): void
    {
        $this->dir = TempDirectory::create('hash');
        $this->hash = new HashService();
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->dir);
    }

    public function testKnownVectors(): void
    {
        file_put_contents($this->dir . '/empty.bin', '');
        file_put_contents($this->dir . '/abc.txt', 'abc');

        // FIPS 180-2 test vektörleri
        self::assertSame('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $this->hash->file($this->dir . '/empty.bin'));
        self::assertSame('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', $this->hash->file($this->dir . '/abc.txt'));
    }

    public function testLargeFileMatchesInMemoryHash(): void
    {
        // Birkaç MB'lık ikili içerik: akış halinde hesaplanan özet bellekteki özetle aynı olmalı
        $content = str_repeat(random_bytes(1024), 3 * 1024) . "\x00\xFF";
        file_put_contents($this->dir . '/big.bin', $content);

        self::assertSame(hash('sha256', $content), $this->hash->file($this->dir . '/big.bin'));
    }

    public function testSingleByteChangeChangesHash(): void
    {
        file_put_contents($this->dir . '/a.pdf', '%PDF-1.7 icerik');
        file_put_contents($this->dir . '/b.pdf', '%PDF-1.7 icerił');

        self::assertNotSame($this->hash->file($this->dir . '/a.pdf'), $this->hash->file($this->dir . '/b.pdf'));
    }

    public function testVerify(): void
    {
        $file = $this->dir . '/abc.txt';
        file_put_contents($file, 'abc');

        self::assertTrue($this->hash->verify($file, 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad'));
        self::assertTrue($this->hash->verify($file, 'BA7816BF8F01CFEA414140DE5DAE2223B00361A396177A9CB410FF61F20015AD'));
        self::assertFalse($this->hash->verify($file, str_repeat('0', 64)));
        self::assertFalse($this->hash->verify($this->dir . '/missing.txt', hash('sha256', 'abc')));
    }

    public function testMissingFileThrowsWithoutLeakingPath(): void
    {
        try {
            $this->hash->file($this->dir . '/missing.txt');
            self::fail('Expected StorageException');
        } catch (StorageException $e) {
            self::assertStringNotContainsString($this->dir, $e->getMessage());
        }
    }

    public function testIsValid(): void
    {
        self::assertTrue(HashService::isValid(hash('sha256', 'x')));
        self::assertFalse(HashService::isValid(strtoupper(hash('sha256', 'x'))));
        self::assertFalse(HashService::isValid(hash('sha1', 'x')));
        self::assertFalse(HashService::isValid('xyz'));
        self::assertFalse(HashService::isValid(''));
    }
}
