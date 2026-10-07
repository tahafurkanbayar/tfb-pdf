<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class OperationRepository
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_NO_CHANGE = 'no_change';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @param list<int> $inputVersionIds
     */
    public function create(string $publicId, ?int $documentId, string $ownerHash, string $type, array $params, array $inputVersionIds): int
    {
        return $this->db->insert('operations', [
            'public_id' => $publicId,
            'document_id' => $documentId,
            'owner_hash' => $ownerHash,
            'type' => $type,
            'status' => self::STATUS_PROCESSING,
            'params' => self::json($params),
            'input_versions' => self::json($inputVersionIds),
            'started_at' => Database::now(),
        ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function finish(int $id, string $status, ?int $documentId, array $result, ?string $engine, int $durationMs, ?string $errorCode = null): void
    {
        $this->db->execute(
            'UPDATE operations SET status = ?, document_id = COALESCE(?, document_id), result = ?, engine = ?, error_code = ?, finished_at = ?, duration_ms = ? WHERE id = ?',
            [$status, $documentId, self::json($result), $engine, $errorCode, Database::now(), max(0, $durationMs), $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForOwner(string $publicId, string $ownerHash): ?array
    {
        return $this->db->first('SELECT * FROM operations WHERE public_id = ? AND owner_hash = ?', [$publicId, $ownerHash]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForOwner(string $ownerHash, int $limit = 10): array
    {
        return $this->db->select(
            'SELECT o.*, d.public_id AS document_public_id, d.original_name
             FROM operations o LEFT JOIN documents d ON d.id = o.document_id
             WHERE o.owner_hash = ? ORDER BY o.id DESC LIMIT ' . max(1, min(100, $limit)),
            [$ownerHash]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForDocument(int $documentId): array
    {
        return $this->db->select('SELECT * FROM operations WHERE document_id = ? ORDER BY id DESC', [$documentId]);
    }

    /**
     * Saatlik işlem sayısı (kaba kötüye kullanım koruması için yardımcı).
     */
    public function countSince(string $ownerHash, string $since): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM operations WHERE owner_hash = ? AND started_at >= ?', [$ownerHash, $since]);
    }

    /**
     * @param array<mixed> $value
     */
    private static function json(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
