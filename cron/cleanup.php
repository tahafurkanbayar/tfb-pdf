<?php

declare(strict_types=1);

/*
 * Temizlik görevi (cPanel → Cron Jobs).
 *
 *   Her saat:  /usr/local/bin/php /home/KULLANICI/tfb-pdf/cron/cleanup.php
 *
 * Süresi dolan belgeleri ve tüm sürümlerini siler (audit: "expiry"), eski geçici dosyaları,
 * export ZIP'lerini, önizleme önbelleğini, oturum dosyalarını ve yetim dizinleri temizler.
 * Çıkış kodu: 0 = tamam, 1 = hata, 3 = başka bir temizlik zaten çalışıyor.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$app = require dirname(__DIR__) . '/bootstrap/app.php';
@set_time_limit(300);

try {
    $report = $app->container()->get(App\Services\CleanupService::class)->run('cron', 500, 240.0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($report === null) {
    echo "Başka bir temizlik zaten çalışıyor; atlandı.\n";
    exit(3);
}

foreach ($report as $key => $value) {
    printf("%-20s %s\n", $key, is_bool($value) ? ($value ? 'evet' : 'hayır (zaman sınırı, kalanlar sonraki çalıştırmada)') : (string) $value);
}
exit(0);
