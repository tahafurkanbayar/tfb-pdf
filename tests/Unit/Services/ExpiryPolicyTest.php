<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ValidationException;
use App\Services\ExpiryPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Saklama süresi hesabı (spec §20). Veritabanı gerekmez; silme akışı Integration\CleanupTest'te.
 */
final class ExpiryPolicyTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testExpiresAtIsCalculatedFromNow(): void
    {
        self::assertSame(gmdate('Y-m-d H:i:s', self::NOW + 86400), ExpiryPolicy::expiresAt('1d', self::NOW));
        self::assertSame(gmdate('Y-m-d H:i:s', self::NOW + 7 * 86400), ExpiryPolicy::expiresAt('7d', self::NOW));
        self::assertSame(gmdate('Y-m-d H:i:s', self::NOW + 30 * 86400), ExpiryPolicy::expiresAt('30d', self::NOW));
        self::assertNull(ExpiryPolicy::expiresAt('never', self::NOW));
    }

    public function testExpiresAtIsUtc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Istanbul');
        try {
            self::assertSame(gmdate('Y-m-d H:i:s', self::NOW + 86400), ExpiryPolicy::expiresAt('1d', self::NOW));
            self::assertTrue(ExpiryPolicy::isExpired(gmdate('Y-m-d H:i:s', self::NOW), self::NOW));
            self::assertFalse(ExpiryPolicy::isExpired(gmdate('Y-m-d H:i:s', self::NOW + 1), self::NOW));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testIsExpiredBoundaries(): void
    {
        self::assertTrue(ExpiryPolicy::isExpired(gmdate('Y-m-d H:i:s', self::NOW - 1), self::NOW));
        self::assertTrue(ExpiryPolicy::isExpired(gmdate('Y-m-d H:i:s', self::NOW), self::NOW), 'Tam süre dolduğu anda silinebilir');
        self::assertFalse(ExpiryPolicy::isExpired(gmdate('Y-m-d H:i:s', self::NOW + 1), self::NOW));
        self::assertFalse(ExpiryPolicy::isExpired(null, self::NOW), 'never: otomatik silinmez');
    }

    public function testInvalidPolicyIsRejected(): void
    {
        foreach (['1d', '7d', '30d', 'never'] as $policy) {
            self::assertTrue(ExpiryPolicy::isValid($policy), $policy);
        }
        foreach (['', '2d', '30D', 'forever', '1d '] as $policy) {
            self::assertFalse(ExpiryPolicy::isValid($policy), $policy);
        }

        try {
            ExpiryPolicy::expiresAt('365d', self::NOW);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('documents.expiry_invalid', $e->messageKey());
        }
    }
}
