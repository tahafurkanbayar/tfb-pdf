<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\DocumentRepository;
use App\Repositories\ExpiryRepository;
use App\Services\CleanupService;
use App\Services\ExpiryPolicy;
use App\Services\StorageService;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

/**
 * Süre dolumu ve temizlik (spec §20–21).
 */
final class CleanupTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('cleanup');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'cleanup-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    private function cleanup(): CleanupService
    {
        return new CleanupService(
            self::db(),
            new DocumentRepository(self::db()),
            new ExpiryRepository(self::db()),
            $this->s->documents,
            $this->s->storage,
            new Logger($this->root . '/logs', 'error'),
            6,
            14
        );
    }

    public function testExpiryPolicyCalculation(): void
    {
        $now = 1_800_000_000;
        self::assertSame(gmdate('Y-m-d H:i:s', $now + 86400), ExpiryPolicy::expiresAt('1d', $now));
        self::assertSame(gmdate('Y-m-d H:i:s', $now + 30 * 86400), ExpiryPolicy::expiresAt('30d', $now));
        self::assertNull(ExpiryPolicy::expiresAt('never', $now));
        self::assertTrue(ExpiryPolicy::isExpired(gmdate('Y-m-d H:i:s', $now - 1), $now));
        self::assertFalse(ExpiryPolicy::isExpired(gmdate('Y-m-d H:i:s', $now + 1), $now));
        self::assertFalse(ExpiryPolicy::isExpired(null, $now));
    }

    public function testExpiredDocumentsAreDeletedWithAuditAndOthersKept(): void
    {
        $expired = $this->s->uploadPdf($this->owner, 2, 'eski.pdf');
        $this->s->tools->rotate($this->owner, $expired->publicId, 0, [1 => 90]);
        $fresh = $this->s->uploadPdf($this->owner, 1, 'yeni.pdf');
        $forever = $this->s->uploadPdf($this->owner, 1, 'kalici.pdf');
        $this->s->documents->setExpiry($forever, 'never');

        // Süresi geçmiş olarak işaretle
        self::db()->execute('UPDATE file_expiry SET expires_at = ? WHERE document_id = ?', [gmdate('Y-m-d H:i:s', time() - 60), $expired->id]);
        self::db()->execute("UPDATE file_expiry SET expires_at = ? WHERE document_id = ?", [gmdate('Y-m-d H:i:s', time() + 3600), $fresh->id]);
        $expiredFiles = [$this->s->versionPath($expired, 0), $this->s->versionPath($expired, 1)];

        $report = $this->cleanup()->run('cron');

        self::assertSame(1, $report['expired_documents']);
        self::assertTrue($report['completed']);
        foreach ($expiredFiles as $file) {
            self::assertFileDoesNotExist($file);
        }
        $ids = array_column(self::db()->select('SELECT public_id FROM documents'), 'public_id');
        self::assertNotContains($expired->publicId, $ids);
        self::assertContains($fresh->publicId, $ids);
        self::assertContains($forever->publicId, $ids);

        $event = self::db()->first('SELECT * FROM audit_events WHERE document_public_id = ? ORDER BY id DESC LIMIT 1', [$expired->publicId]);
        self::assertSame('expiry', $event['event_type']);
        self::assertSame('cron', $event['actor']);
        self::assertTrue($this->s->audit->verify()['ok']);
    }

    public function testTemporaryFilesOrphansAndOldRateLimitsAreRemoved(): void
    {
        $old = $this->s->storage->createTempDirectory();
        file_put_contents($old . '/x', 'x');
        touch($old, time() - 7 * 3600);
        $recent = $this->s->storage->createTempDirectory();

        // Yetim: diskte belge dizini var, veritabanında kaydı yok (yarım kalmış silme)
        $orphanId = str_repeat('c', 32);
        $this->s->storage->putContents(StorageService::originalPath($orphanId, 'pdf'), '%PDF');
        foreach (StorageService::documentDirectories($orphanId) as $dir) {
            if (is_dir($this->root . '/' . $dir)) {
                touch($this->root . '/' . $dir, time() - 7 * 3600);
            }
        }
        // Yeni yetim (devam eden yükleme olabilir): bekleme süresi dolmadan silinmez
        $youngId = str_repeat('d', 32);
        $this->s->storage->putContents(StorageService::originalPath($youngId, 'pdf'), '%PDF');
        // Gerçek belge dokunulmaz
        $real = $this->s->uploadPdf($this->owner, 1);

        self::db()->insert('rate_limits', ['bucket' => str_repeat('a', 64), 'window_start' => gmdate('Y-m-d H:i:s', time() - 3 * 86400), 'hits' => 3]);
        self::db()->insert('rate_limits', ['bucket' => str_repeat('b', 64), 'window_start' => gmdate('Y-m-d H:i:00'), 'hits' => 1]);

        $report = $this->cleanup()->run();

        self::assertSame(1, $report['temporary']);
        self::assertDirectoryDoesNotExist($old);
        self::assertDirectoryExists($recent);
        self::assertSame(1, $report['orphan_directories']);
        self::assertFalse($this->s->storage->exists(StorageService::originalPath($orphanId, 'pdf')));
        self::assertTrue($this->s->storage->exists(StorageService::originalPath($youngId, 'pdf')));
        self::assertFileExists($this->s->versionPath($real, 0));
        self::assertSame(1, $report['rate_limits']);
    }

    public function testConcurrentRunIsSkipped(): void
    {
        $lock = fopen($this->root . '/cache/cleanup.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            self::assertNull($this->cleanup()->run());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        self::assertIsArray($this->cleanup()->run());
    }
}
