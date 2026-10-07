<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

/**
 * SHA-256 bütünlük doğrulaması (spec §18).
 */
final class IntegrityTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('integrity');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'integrity-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testDetectsModifiedAndMissingFiles(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 2);
        $this->s->tools->rotate($this->owner, $doc->publicId, 0, [1 => 90]);
        $this->s->tools->rotate($this->owner, $doc->publicId, 1, [2 => 90]);

        self::assertSame(['ok', 'ok', 'ok'], array_column($this->s->documents->verifyIntegrity($doc), 'status'));

        // Bir sürüm diskte değiştirildi, biri silindi
        file_put_contents($this->s->versionPath($doc, 1), 'x', FILE_APPEND);
        unlink($this->s->versionPath($doc, 2));

        $results = $this->s->documents->verifyIntegrity($doc);
        self::assertSame(['ok', 'mismatch', 'missing'], array_column($results, 'status'));
        self::assertNotSame($results[1]['expected'], $results[1]['actual']);
        self::assertNull($results[2]['actual']);
    }
}
