<?php

declare(strict_types=1);

/*
 * Uygulama önyükleme: ortam, yapılandırma, hata yönetimi ve servisler.
 * HTTP (public/index.php), CLI (bin/*, cron/*) ve testler tarafından kullanılır.
 */

// Bu dosya eski PHP sürümlerinde de ayrıştırılabilir olmalı: cPanel'de varsayılan sürüm (veya cron'daki
// CLI sürümü) çoğu zaman eskidir; ham "syntax error" yerine yöneticiye ne yapacağı söylenir.
if (PHP_VERSION_ID < 80200) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "tfb-pdf: PHP 8.2 veya üzeri gerekli. cPanel → \"Select PHP Version\" / \"MultiPHP Manager\" ile sürümü değiştirin.\n"
        . "tfb-pdf: PHP 8.2 or newer is required. Change it in cPanel → \"Select PHP Version\" / \"MultiPHP Manager\".\n";
    exit(1);
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!is_file(APP_ROOT . '/vendor/autoload.php')) {
    // Çeviri sistemi henüz yüklenemez; yalnızca kurulum yapan yöneticiye yönelik teknik mesaj
    http_response_code(503);
    echo 'vendor/ missing: run "composer install" or upload the vendor directory (see README).';
    exit(1);
}

require APP_ROOT . '/vendor/autoload.php';

// Uygulama içinde tüm zamanlar UTC; gösterimde APP_TIMEZONE kullanılır
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

App\Core\Env::load(APP_ROOT . '/.env');
$config = App\Core\Config::fromDirectory(APP_ROOT . '/config');

$container = new App\Core\Container();
$container->instance(App\Core\Config::class, $config);
(require __DIR__ . '/services.php')($container, $config);

if (PHP_SAPI !== 'cli' || !defined('TFB_TESTING')) {
    $container->get(App\Core\ErrorHandler::class)->register();
}

return new App\Core\Application($container);
