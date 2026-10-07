<?php

declare(strict_types=1);

use App\Core\Env;

return [
    // Varsayılan: proje kökündeki storage/. Mümkünse web root dışında bir mutlak yol verin.
    'path' => rtrim(Env::string('STORAGE_PATH', APP_ROOT . '/storage'), '/\\'),
    // 1d | 7d | 30d | never
    'default_expiry' => Env::string('DEFAULT_EXPIRY', '7d'),
    // Cron kurulamayan sunucularda istekler sırasında küçük, zaman sınırlı temizlik.
    'opportunistic_cleanup' => Env::bool('OPPORTUNISTIC_CLEANUP', true),
    // Geçici dosyalar ve export ZIP'leri bu süreden (saat) sonra silinir.
    'temporary_ttl_hours' => Env::int('TEMPORARY_TTL_HOURS', 6),
    // Thumbnail cache bu süreden (gün) sonra silinir.
    'preview_ttl_days' => Env::int('PREVIEW_TTL_DAYS', 14),
];
