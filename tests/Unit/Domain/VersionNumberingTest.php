<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\DocumentVersion;
use App\Exceptions\StorageException;
use App\Services\StorageService;
use PHPUnit\Framework\TestCase;

/**
 * Sürüm numaralama kuralları (spec §17): 0 = orijinal, işlem sonuçları 1'den başlar,
 * dosya adları v001, v002 ... biçimindedir. Sıralı numara ataması DB'de yapılır ve
 * Integration\VersioningTest ile test edilir.
 */
final class VersionNumberingTest extends TestCase
{
    private const DOC = '0123456789abcdef0123456789abcdef';

    /**
     * @return array<string, mixed>
     */
    private static function row(int $number): array
    {
        return [
            'id' => 10 + $number,
            'document_id' => 7,
            'version_number' => (string) $number, // PDO string döndürebilir
            'operation_id' => $number === 0 ? null : '3',
            'filename' => 'belge.pdf',
            'storage_path' => 'versions/01/x/v001.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => '1234',
            'sha256' => str_repeat('a', 64),
            'page_count' => '2',
            'label' => null,
            'created_at' => '2026-10-08 10:00:00',
        ];
    }

    public function testVersionZeroIsTheOriginal(): void
    {
        $original = DocumentVersion::fromRow(self::row(0));
        $first = DocumentVersion::fromRow(self::row(1));

        self::assertTrue($original->isOriginal());
        self::assertNull($original->operationId);
        self::assertFalse($first->isOriginal());
        self::assertSame(1, $first->versionNumber);
        self::assertSame(3, $first->operationId);
        self::assertSame(1234, $first->fileSize);
    }

    public function testVersionFilenamesArePaddedAndSortable(): void
    {
        self::assertSame('v001.pdf', StorageService::versionFilename(1));
        self::assertSame('v010.pdf', StorageService::versionFilename(10));
        self::assertSame('v999.pdf', StorageService::versionFilename(999));
        self::assertSame('v1000.pdf', StorageService::versionFilename(1000));

        $names = array_map(static fn (int $n): string => StorageService::versionFilename($n), [12, 3, 100, 1]);
        sort($names);
        self::assertSame(['v001.pdf', 'v003.pdf', 'v012.pdf', 'v100.pdf'], $names);
    }

    public function testOriginalIsNotStoredAsAVersionFile(): void
    {
        // Orijinal dosya documents/ altında ayrı tutulur; versions/ altında 0 numarası olamaz
        foreach ([0, -1, 1_000_000] as $bad) {
            try {
                StorageService::versionPath(self::DOC, $bad);
                self::fail('Expected rejection: ' . $bad);
            } catch (StorageException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertStringEndsWith('/v001.pdf', StorageService::versionPath(self::DOC, 1));
        self::assertStringStartsWith('documents/', StorageService::originalPath(self::DOC, 'pdf'));
    }
}
