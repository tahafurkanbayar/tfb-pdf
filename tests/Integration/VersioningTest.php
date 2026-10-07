<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\DatabaseException;
use App\Services\StorageService;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

/**
 * Değiştirilemez sürümleme (spec §17): original.pdf korunur, v001, v002 ... sırayla oluşur.
 */
final class VersioningTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('versioning');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'version-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testVersionsAreSequentialImmutableAndTraceable(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 4);
        $originalPath = $this->s->versionPath($doc, 0);
        $originalHash = hash_file('sha256', $originalPath);
        $originalMtime = filemtime($originalPath);

        $rotate = $this->s->tools->rotate($this->owner, $doc->publicId, 0, [1 => 90]);           // v001 ← orijinal
        $noChange = $this->s->tools->rotate($this->owner, $doc->publicId, 1, []);                 // sürüm yok
        $split = $this->s->tools->split($this->owner, $doc->publicId, 1, 'ranges', '1-2, 3-4');   // v002, v003 ← v001

        self::assertSame([1], array_map(fn ($v) => $v->versionNumber, $rotate->versions));
        self::assertSame('no_change', $noChange->status);
        self::assertSame([2, 3], array_map(fn ($v) => $v->versionNumber, $split->versions), 'Değişiklik yok işlemi numara tüketmemeli');

        $versions = $this->s->documents->versions($doc);
        self::assertSame(['original.pdf', 'v001.pdf', 'v002.pdf', 'v003.pdf'], array_map(fn ($v) => $v->filename, $versions));
        foreach ($versions as $v) {
            self::assertSame($v->sha256, hash_file('sha256', $this->s->storage->resolve($v->storagePath)), $v->filename);
        }

        // Köken: v001 orijinalden, v002/v003 v001'den
        $sources = $this->s->documents->versionSources($doc, $versions);
        self::assertSame([0], $sources[$versions[1]->id]['inputs']);
        self::assertSame([1], $sources[$versions[2]->id]['inputs']);
        self::assertSame([1], $sources[$versions[3]->id]['inputs']);
        self::assertArrayNotHasKey($versions[0]->id, $sources, 'Orijinalin kökeni yok');

        // Orijinal dosya hiç değişmedi
        clearstatcache();
        self::assertSame($originalHash, hash_file('sha256', $originalPath));
        self::assertSame($originalMtime, filemtime($originalPath));
    }

    public function testDatabaseRejectsDuplicateVersionNumbers(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 1);

        $this->expectException(DatabaseException::class);
        self::db()->insert('document_versions', [
            'document_id' => $doc->id,
            'version_number' => 0,
            'filename' => 'original.pdf',
            'storage_path' => 'versions/xx/dup.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1,
            'sha256' => str_repeat('0', 64),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function testStorageRefusesToOverwriteAnExistingVersionFile(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 2);
        $this->s->tools->rotate($this->owner, $doc->publicId, 0, [1 => 180]);
        $target = StorageService::versionPath($doc->publicId, 1);
        $before = hash_file('sha256', $this->s->storage->resolve($target));

        $fake = $this->root . '/fixtures/evil.pdf';
        @mkdir(dirname($fake));
        file_put_contents($fake, '%PDF-1.4 evil');
        try {
            $this->s->storage->moveIntoPlace($fake, $target);
            self::fail('Overwrite must be refused');
        } catch (\App\Exceptions\StorageException) {
            self::assertSame($before, hash_file('sha256', $this->s->storage->resolve($target)));
        }
    }
}
