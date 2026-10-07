<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\DocumentRepository;
use App\Repositories\ExpiryRepository;

/**
 * Temizlik (spec §20–21). Sürekli çalışan bir worker gerektirmez:
 *  - cPanel Cron Job → cron/cleanup.php (geniş sınırlar)
 *  - Manuel → aynı betik
 *  - Fırsatçı → istek sonrası, düşük olasılıkla, kilitli ve zaman/adet sınırlı
 *
 * Aynı anda yalnızca bir temizlik çalışır (flock).
 */
final class CleanupService
{
    /** Yetim dizin sayılmadan önce beklenen süre: yükleme sırasında dosya DB kaydından önce yazılır */
    private const ORPHAN_GRACE_SECONDS = 6 * 3600;

    public function __construct(
        private readonly Database $db,
        private readonly DocumentRepository $documents,
        private readonly ExpiryRepository $expiry,
        private readonly DocumentService $documentService,
        private readonly StorageService $storage,
        private readonly Logger $logger,
        private readonly int $temporaryTtlHours,
        private readonly int $previewTtlDays,
    ) {
    }

    /**
     * @return array<string, int|bool>|null Rapor; başka bir temizlik çalışıyorsa null
     */
    public function run(string $actor = 'cron', int $maxDocuments = 500, float $timeBudgetSeconds = 50.0): ?array
    {
        $lockFile = $this->storage->root() . '/cache/cleanup.lock';
        $handle = @fopen($lockFile, 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle !== false) {
                fclose($handle);
            }

            return null;
        }

        $started = microtime(true);
        $report = [
            'expired_documents' => 0,
            'orphan_directories' => 0,
            'temporary' => 0,
            'exports' => 0,
            'previews' => 0,
            'sessions' => 0,
            'rate_limits' => 0,
            'completed' => true,
        ];

        try {
            // 1. Süresi dolan belgeler (audit: "expiry")
            foreach ($this->expiry->due(gmdate('Y-m-d H:i:s'), $maxDocuments) as $row) {
                if (microtime(true) - $started > $timeBudgetSeconds) {
                    $report['completed'] = false;
                    break;
                }
                $document = $this->documents->findById($row['id']);
                if ($document !== null) {
                    $this->documentService->delete($document, AuditService::EXPIRY, $actor);
                    $report['expired_documents']++;
                }
            }

            // 2. Geçici dosyalar, export ZIP'leri, eski önizlemeler, oturum dosyaları
            $report['temporary'] = $this->storage->purgeOlderThan('temporary', $this->temporaryTtlHours * 3600);
            $report['exports'] = $this->storage->purgeOlderThan('exports', $this->temporaryTtlHours * 3600);
            $report['previews'] = $this->storage->purgeOlderThan('previews', $this->previewTtlDays * 86400);
            $report['sessions'] = $this->storage->purgeOlderThan('sessions', 24 * 3600);

            // 3. Yetim dizinler (kaydı olmayan belge dosyaları: yarım kalmış silme / yükleme)
            if (microtime(true) - $started < $timeBudgetSeconds) {
                $report['orphan_directories'] = $this->removeOrphans();
            }

            // 4. Eski istek sınırı kayıtları
            $report['rate_limits'] = $this->db->execute('DELETE FROM rate_limits WHERE window_start < ?', [gmdate('Y-m-d H:i:s', time() - 2 * 86400)]);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->logger->info('Cleanup finished', ['actor' => $actor, 'report' => $report]);

        return $report;
    }

    /**
     * Fırsatçı temizlik: her istekte değil, ~1/$probability olasılıkla ve küçük sınırlarla.
     */
    public function maybeRunOpportunistic(int $probability = 50): void
    {
        if (random_int(1, max(1, $probability)) !== 1) {
            return;
        }
        $this->run('system', 5, 3.0);
    }

    private function removeOrphans(): int
    {
        $onDisk = $this->storage->documentIdsOnDisk();
        if ($onDisk === []) {
            return 0;
        }

        $removed = 0;
        foreach (array_chunk(array_keys($onDisk), 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $known = array_column($this->db->select("SELECT public_id FROM documents WHERE public_id IN ($placeholders)", $chunk), 'public_id');
            foreach (array_diff($chunk, $known) as $id) {
                if ($onDisk[$id] < time() - self::ORPHAN_GRACE_SECONDS) {
                    $this->storage->deleteDocumentFiles($id);
                    $removed++;
                }
            }
        }

        return $removed;
    }
}
