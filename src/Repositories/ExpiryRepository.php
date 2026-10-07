<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class ExpiryRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function set(int $documentId, string $policy, ?string $expiresAt): void
    {
        $this->db->execute(
            'INSERT INTO file_expiry (document_id, policy, expires_at, updated_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE policy = VALUES(policy), expires_at = VALUES(expires_at), updated_at = VALUES(updated_at)',
            [$documentId, $policy, $expiresAt, Database::now()]
        );
    }

    /**
     * @return array{policy: string, expires_at: ?string}|null
     */
    public function get(int $documentId): ?array
    {
        $row = $this->db->first('SELECT policy, expires_at FROM file_expiry WHERE document_id = ?', [$documentId]);

        return $row === null ? null : ['policy' => (string) $row['policy'], 'expires_at' => $row['expires_at']];
    }

    /**
     * Belirtilen süre içinde silinecek belgeler (panel uyarısı).
     *
     * @return list<array<string, mixed>>
     */
    public function expiringSoon(string $ownerHash, string $until, int $limit = 5): array
    {
        return $this->db->select(
            'SELECT d.public_id, d.original_name, e.policy, e.expires_at
             FROM file_expiry e JOIN documents d ON d.id = e.document_id
             WHERE d.owner_hash = ? AND e.expires_at IS NOT NULL AND e.expires_at <= ?
             ORDER BY e.expires_at ASC LIMIT ' . max(1, min(50, $limit)),
            [$ownerHash, $until]
        );
    }

    /**
     * Süresi dolmuş belgeler (temizlik için).
     *
     * @return list<array{id: int, public_id: string, owner_hash: string}>
     */
    public function due(string $now, int $limit): array
    {
        $rows = $this->db->select(
            'SELECT d.id, d.public_id, d.owner_hash FROM file_expiry e JOIN documents d ON d.id = e.document_id
             WHERE e.expires_at IS NOT NULL AND e.expires_at <= ? ORDER BY e.expires_at ASC LIMIT ' . max(1, min(500, $limit)),
            [$now]
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'public_id' => (string) $r['public_id'],
            'owner_hash' => (string) $r['owner_hash'],
        ], $rows);
    }
}
