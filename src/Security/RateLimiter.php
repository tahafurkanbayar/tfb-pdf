<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Database;
use App\Exceptions\RateLimitException;

/**
 * Sabit pencereli istek sınırlama (spec §32). Redis yerine veritabanı kullanır (paylaşımlı hosting uyumlu).
 * Anahtar = HMAC(eylem + istemci): IP adresi düz metin saklanmaz. Eski kayıtları temizlik görevi siler.
 */
final class RateLimiter
{
    public function __construct(
        private readonly Database $db,
        private readonly Hmac $hmac,
    ) {
    }

    /**
     * Bir isteği sayar; sınır aşıldıysa RateLimitException.
     *
     * @param int $limit 0 = sınırsız
     */
    public function hit(string $action, string $client, int $limit, int $windowSeconds = 3600): void
    {
        if ($limit <= 0) {
            return;
        }

        $bucket = $this->hmac->hash('rate', $action . '|' . $client);
        $windowStart = gmdate('Y-m-d H:i:s', intdiv(time(), $windowSeconds) * $windowSeconds);

        $this->db->execute(
            'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1',
            [$bucket, $windowStart]
        );
        $hits = (int) $this->db->scalar('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?', [$bucket, $windowStart]);

        if ($hits > $limit) {
            throw new RateLimitException(sprintf('Rate limit exceeded for %s (%d > %d)', $action, $hits, $limit));
        }
    }
}
