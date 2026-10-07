<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Domain\Document;

final class DocumentRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(string $publicId, string $ownerHash, string $originalName, string $sourceType, string $mimeType): Document
    {
        $now = Database::now();
        $id = $this->db->insert('documents', [
            'public_id' => $publicId,
            'owner_hash' => $ownerHash,
            'original_name' => $originalName,
            'source_type' => $sourceType,
            'mime_type' => $mimeType,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new Document($id, $publicId, $ownerHash, $originalName, $sourceType, $mimeType, $now, $now);
    }

    /**
     * Sahip kontrolü sorguda yapılır: başkasının belgesi "bulunamadı" ile aynı sonucu verir.
     */
    public function findForOwner(string $publicId, string $ownerHash): ?Document
    {
        $row = $this->db->first('SELECT * FROM documents WHERE public_id = ? AND owner_hash = ?', [$publicId, $ownerHash]);

        return $row === null ? null : Document::fromRow($row);
    }

    public function findByPublicId(string $publicId): ?Document
    {
        $row = $this->db->first('SELECT * FROM documents WHERE public_id = ?', [$publicId]);

        return $row === null ? null : Document::fromRow($row);
    }

    public function findById(int $id): ?Document
    {
        $row = $this->db->first('SELECT * FROM documents WHERE id = ?', [$id]);

        return $row === null ? null : Document::fromRow($row);
    }

    /**
     * Sürüm numarası ayırmadan önce belge satırını kilitler (transaction içinde çağrılmalı).
     */
    public function lockForUpdate(int $id): void
    {
        $this->db->scalar('SELECT id FROM documents WHERE id = ? FOR UPDATE', [$id]);
    }

    public function touch(int $id): void
    {
        $this->db->execute('UPDATE documents SET updated_at = ? WHERE id = ?', [Database::now(), $id]);
    }

    /**
     * Liste görünümü için belgeler + son sürüm özeti + saklama süresi.
     *
     * @return list<array<string, mixed>>
     */
    public function listForOwner(string $ownerHash, int $limit = 50, int $offset = 0): array
    {
        return $this->db->select(
            'SELECT d.*, e.policy AS expiry_policy, e.expires_at,
                    (SELECT COUNT(*) FROM document_versions v WHERE v.document_id = d.id) AS version_count,
                    (SELECT COALESCE(SUM(v.file_size), 0) FROM document_versions v WHERE v.document_id = d.id) AS total_size,
                    (SELECT MAX(v.version_number) FROM document_versions v WHERE v.document_id = d.id) AS latest_version
             FROM documents d
             LEFT JOIN file_expiry e ON e.document_id = d.id
             WHERE d.owner_hash = ?
             ORDER BY d.updated_at DESC, d.id DESC
             LIMIT ' . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $offset),
            [$ownerHash]
        );
    }

    public function countForOwner(string $ownerHash): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM documents WHERE owner_hash = ?', [$ownerHash]);
    }

    /**
     * Sahibin tüm sürümlerinin toplam boyutu (kota ve panel için).
     */
    public function storageUsage(string $ownerHash): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(v.file_size), 0) FROM document_versions v JOIN documents d ON d.id = v.document_id WHERE d.owner_hash = ?',
            [$ownerHash]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM documents WHERE id = ?', [$id]);
    }
}
