<?php

declare(strict_types=1);

/*
 * Audit log hash zincirini doğrular.
 *
 *   php bin/verify-audit.php
 *
 * Çıkış kodu: 0 = zincir sağlam, 1 = bozulma tespit edildi, 2 = çalıştırılamadı.
 * Not: Zincir değişikliği tespit eder (tamper-evident); veritabanına tam erişimi olan biri zinciri
 * baştan yeniden hesaplayabileceği için değişikliği engellemez.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$app = require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $result = $app->container()->get(App\Services\AuditService::class)->verify();
} catch (Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(2);
}

if ($result['ok']) {
    printf("OK: %d audit kaydı doğrulandı, zincir sağlam.\n", $result['checked']);
    exit(0);
}

printf(
    "BOZULMA: %d. kayıtta zincir kopuyor (kayıt id: %s, neden: %s).\n",
    $result['checked'],
    $result['broken_at'] ?? '-',
    $result['reason']
);
exit(1);
