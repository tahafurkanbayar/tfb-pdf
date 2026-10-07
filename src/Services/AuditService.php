<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Security\Hmac;

/**
 * Append-only audit log (spec §19) ve hash zinciri.
 *
 * Her olay, bir önceki olayın özetini (prev_hash) içerir; event_hash = SHA-256(kanonik kayıt).
 * Bir kayıt sonradan değiştirilir veya silinirse verify() zincirin koptuğu yeri bulur.
 * Bu, değişikliği ENGELLEMEZ (veritabanı yöneticisi zinciri yeniden hesaplayabilir);
 * yalnızca tespit edilebilir kılar (tamper-evident).
 *
 * Uygulama audit_events tablosunda UPDATE veya DELETE yapmaz.
 */
final class AuditService
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public const UPLOAD = 'upload';
    public const MERGE = 'merge';
    public const SPLIT = 'split';
    public const REORDER = 'reorder';
    public const ROTATE = 'rotate';
    public const COMPRESS = 'compress';
    public const WATERMARK = 'watermark';
    public const REDACT = 'redact';
    public const OCR = 'ocr';
    public const OFFICE_CONVERT = 'office_convert';
    public const DOWNLOAD = 'download';
    public const EXPORT = 'export';
    public const DELETE = 'delete';
    public const EXPIRY = 'expiry';
    public const EXPIRY_CHANGED = 'expiry_changed';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_NO_CHANGE = 'no_change';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string, mixed> $metadata Dosya içeriği, şifre, token gibi hassas veriler EKLENMEZ.
     */
    public function record(
        string $eventType,
        string $status = self::STATUS_SUCCESS,
        string $actor = 'owner',
        ?string $ownerHash = null,
        ?string $documentPublicId = null,
        ?string $operationPublicId = null,
        ?string $inputHash = null,
        ?string $outputHash = null,
        array $metadata = [],
        ?string $errorMessage = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): string {
        return $this->db->transaction(function () use (
            $eventType, $status, $actor, $ownerHash, $documentPublicId, $operationPublicId,
            $inputHash, $outputHash, $metadata, $errorMessage, $ipAddress, $userAgent
        ): string {
            // Zincir başını kilitle: eşzamanlı olaylar sıraya girer
            $prev = (string) ($this->db->scalar("SELECT `value` FROM settings WHERE `key` = 'audit_chain_head' FOR UPDATE") ?? self::GENESIS);

            $event = [
                'event_id' => Hmac::publicId(),
                'owner_hash' => $ownerHash,
                'document_public_id' => $documentPublicId,
                'operation_public_id' => $operationPublicId,
                'event_type' => $eventType,
                'status' => $status,
                'actor' => $actor,
                'input_hash' => $inputHash,
                'output_hash' => $outputHash,
                'metadata' => $metadata === [] ? null : self::canonicalJson($metadata),
                'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 255) : null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
                'created_at' => Database::now(),
                'prev_hash' => $prev,
            ];
            $event['event_hash'] = self::hashEvent($event);

            $this->db->insert('audit_events', $event);
            $this->db->execute(
                "INSERT INTO settings (`key`, `value`, updated_at) VALUES ('audit_chain_head', ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)",
                [$event['event_hash'], $event['created_at']]
            );

            return $event['event_id'];
        });
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function hashEvent(array $event): string
    {
        $fields = [];
        foreach (['event_id', 'owner_hash', 'document_public_id', 'operation_public_id', 'event_type', 'status', 'actor',
                     'input_hash', 'output_hash', 'metadata', 'error_message', 'ip_address', 'user_agent', 'created_at', 'prev_hash'] as $key) {
            $value = $event[$key] ?? null;
            if ($key === 'metadata' && $value !== null) {
                // MySQL 8 JSON tipi metni yeniden biçimlendirebilir; karşılaştırma kanonik biçim üzerinden
                $decoded = json_decode((string) $value, true);
                $value = is_array($decoded) ? self::canonicalJson($decoded) : (string) $value;
            }
            $fields[$key] = $value === null ? null : (string) $value;
        }

        return hash('sha256', (string) json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<mixed> $data
     */
    public static function canonicalJson(array $data): string
    {
        $sort = static function (array $value) use (&$sort): array {
            if (!array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value[$k] = $sort($v);
                }
            }

            return $value;
        };

        return (string) json_encode($sort($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Zinciri baştan doğrular.
     *
     * @return array{ok: bool, checked: int, broken_at: ?int, reason: ?string}
     */
    public function verify(int $batch = 1000): array
    {
        $prev = self::GENESIS;
        $lastId = 0;
        $checked = 0;

        do {
            $rows = $this->db->select('SELECT * FROM audit_events WHERE id > ? ORDER BY id LIMIT ' . $batch, [$lastId]);
            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                $checked++;
                if (!hash_equals($prev, (string) $row['prev_hash'])) {
                    return ['ok' => false, 'checked' => $checked, 'broken_at' => $lastId, 'reason' => 'prev_hash'];
                }
                if (!hash_equals(self::hashEvent($row), (string) $row['event_hash'])) {
                    return ['ok' => false, 'checked' => $checked, 'broken_at' => $lastId, 'reason' => 'event_hash'];
                }
                $prev = (string) $row['event_hash'];
            }
        } while (count($rows) === $batch);

        $head = (string) ($this->db->scalar("SELECT `value` FROM settings WHERE `key` = 'audit_chain_head'") ?? self::GENESIS);
        if (!hash_equals($prev, $head)) {
            return ['ok' => false, 'checked' => $checked, 'broken_at' => null, 'reason' => 'head'];
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null, 'reason' => null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forDocument(string $documentPublicId, int $limit = 100): array
    {
        return $this->db->select(
            'SELECT event_id, event_type, status, actor, input_hash, output_hash, metadata, error_message, created_at, operation_public_id
             FROM audit_events WHERE document_public_id = ? ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)),
            [$documentPublicId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forOwner(string $ownerHash, int $limit = 1000): array
    {
        return $this->db->select(
            'SELECT event_id, document_public_id, operation_public_id, event_type, status, actor, input_hash, output_hash, metadata, error_message, created_at
             FROM audit_events WHERE owner_hash = ? ORDER BY id DESC LIMIT ' . max(1, min(10000, $limit)),
            [$ownerHash]
        );
    }
}
