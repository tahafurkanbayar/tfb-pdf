<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;

/**
 * Belge saklama süreleri (spec §20): 1 gün, 7 gün, 30 gün, manuel silme (never).
 */
final class ExpiryPolicy
{
    public const POLICIES = [
        '1d' => 86400,
        '7d' => 7 * 86400,
        '30d' => 30 * 86400,
        'never' => null,
    ];

    public static function isValid(string $policy): bool
    {
        return array_key_exists($policy, self::POLICIES);
    }

    public static function assertValid(string $policy): void
    {
        if (!self::isValid($policy)) {
            throw new ValidationException('Invalid expiry policy: ' . $policy, 'documents.expiry_invalid');
        }
    }

    /**
     * Süre seçildiği andan itibaren hesaplanır. never → null (otomatik silinmez).
     */
    public static function expiresAt(string $policy, int $now): ?string
    {
        self::assertValid($policy);
        $seconds = self::POLICIES[$policy];

        return $seconds === null ? null : gmdate('Y-m-d H:i:s', $now + $seconds);
    }

    public static function isExpired(?string $expiresAt, int $now): bool
    {
        return $expiresAt !== null && strtotime($expiresAt . ' UTC') <= $now;
    }
}
