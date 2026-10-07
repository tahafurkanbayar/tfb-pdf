<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Veritabanındaki UTC zaman damgalarını kullanıcı saat dilimi ve diline göre gösterir.
 */
final class DateFormatter
{
    public function __construct(private readonly string $timezone)
    {
    }

    public function format(?string $utc, string $locale, bool $withTime = true): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
            $date = $date->setTimezone(new \DateTimeZone($this->timezone));
        } catch (\Exception) {
            return '';
        }

        $pattern = $locale === 'tr' ? 'd.m.Y' : 'Y-m-d';

        return $date->format($withTime ? $pattern . ' H:i' : $pattern);
    }

    /**
     * Makine tarafından okunabilir (time datetime="" ve JSON için) ISO 8601.
     */
    public static function iso(?string $utc): ?string
    {
        return $utc === null || $utc === '' ? null : str_replace(' ', 'T', $utc) . 'Z';
    }
}
