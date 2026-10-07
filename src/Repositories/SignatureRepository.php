<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class SignatureRepository
{
    public const PENDING = 'pending';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const DECLINED = 'declined';
    public const EXPIRED = 'expired';

    public const SIGNER_PENDING = 'pending';
    public const SIGNER_VIEWED = 'viewed';
    public const SIGNER_SIGNED = 'signed';
    public const SIGNER_DECLINED = 'declined';

    public function __construct(private readonly Database $db)
    {
    }

    public function createRequest(string $publicId, int $documentId, int $versionId, string $ownerHash, ?string $message, string $sourceSha256, string $expiresAt): int
    {
        $now = Database::now();

        return $this->db->insert('signature_requests', [
            'public_id' => $publicId,
            'document_id' => $documentId,
            'version_id' => $versionId,
            'owner_hash' => $ownerHash,
            'status' => self::PENDING,
            'message' => $message,
            'source_sha256' => $sourceSha256,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function createSigner(int $requestId, string $name, ?string $email, string $tokenHash): int
    {
        return $this->db->insert('signature_signers', [
            'request_id' => $requestId,
            'name' => $name,
            'email' => $email,
            'token_hash' => $tokenHash,
            'status' => self::SIGNER_PENDING,
            'created_at' => Database::now(),
        ]);
    }

    /**
     * @param array{page: int, x: float, y: float, w: float, h: float} $field
     */
    public function createField(int $requestId, int $signerId, array $field): void
    {
        $this->db->insert('signature_fields', [
            'request_id' => $requestId,
            'signer_id' => $signerId,
            'page_number' => $field['page'],
            'pos_x' => sprintf('%.6F', $field['x']),
            'pos_y' => sprintf('%.6F', $field['y']),
            'width' => sprintf('%.6F', $field['w']),
            'height' => sprintf('%.6F', $field['h']),
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function addEvent(int $requestId, ?int $signerId, string $type, ?string $ip = null, ?string $userAgent = null, array $metadata = []): void
    {
        $this->db->insert('signature_events', [
            'request_id' => $requestId,
            'signer_id' => $signerId,
            'event_type' => $type,
            'ip_address' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => Database::now(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRequestForOwner(string $publicId, string $ownerHash): ?array
    {
        return $this->db->first('SELECT * FROM signature_requests WHERE public_id = ? AND owner_hash = ?', [$publicId, $ownerHash]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRequest(int $id): ?array
    {
        return $this->db->first('SELECT * FROM signature_requests WHERE id = ?', [$id]);
    }

    /**
     * İmzalayan + talep, token özetine göre.
     *
     * @return array<string, mixed>|null
     */
    public function findSignerByTokenHash(string $tokenHash): ?array
    {
        return $this->db->first(
            'SELECT s.*, r.public_id AS request_public_id, r.status AS request_status, r.expires_at, r.document_id, r.version_id,
                    r.message, r.source_sha256, r.final_version_id, r.final_sha256, r.owner_hash
             FROM signature_signers s JOIN signature_requests r ON r.id = s.request_id
             WHERE s.token_hash = ?',
            [$tokenHash]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function signers(int $requestId): array
    {
        return $this->db->select('SELECT * FROM signature_signers WHERE request_id = ? ORDER BY id', [$requestId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(int $requestId): array
    {
        return $this->db->select('SELECT * FROM signature_fields WHERE request_id = ? ORDER BY page_number, id', [$requestId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(int $requestId): array
    {
        return $this->db->select('SELECT * FROM signature_events WHERE request_id = ? ORDER BY id', [$requestId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requestsForDocument(int $documentId): array
    {
        return $this->db->select('SELECT * FROM signature_requests WHERE document_id = ? ORDER BY id DESC', [$documentId]);
    }

    public function lockRequest(int $id): void
    {
        $this->db->scalar('SELECT id FROM signature_requests WHERE id = ? FOR UPDATE', [$id]);
    }

    public function setRequestStatus(int $id, string $status): void
    {
        $this->db->execute(
            'UPDATE signature_requests SET status = ?, updated_at = ?, completed_at = IF(? = \'completed\', ?, completed_at) WHERE id = ?',
            [$status, Database::now(), $status, Database::now(), $id]
        );
    }

    public function setFinal(int $id, int $versionId, string $sha256): void
    {
        $this->db->execute(
            'UPDATE signature_requests SET final_version_id = ?, final_sha256 = ?, status = ?, completed_at = ?, updated_at = ? WHERE id = ?',
            [$versionId, $sha256, self::COMPLETED, Database::now(), Database::now(), $id]
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    public function updateSigner(int $id, array $values): void
    {
        $sets = [];
        $params = [];
        foreach ($values as $column => $value) {
            if (!in_array($column, ['status', 'signature_type', 'signature_path', 'typed_name', 'consented_at', 'signed_at', 'declined_at', 'token_hash'], true)) {
                throw new \InvalidArgumentException('Column not allowed: ' . $column);
            }
            $sets[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $this->db->execute('UPDATE signature_signers SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dueForExpiry(string $now, int $limit = 200): array
    {
        return $this->db->select(
            "SELECT * FROM signature_requests WHERE status = 'pending' AND expires_at <= ? LIMIT " . max(1, min(1000, $limit)),
            [$now]
        );
    }
}
