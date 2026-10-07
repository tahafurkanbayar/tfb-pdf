<?php

declare(strict_types=1);

/*
 * tfb-pdf front controller.
 *
 * Uygulama kökü varsayılan olarak bu dizinin bir üstüdür. public/ içeriği ayrı bir
 * dizine (ör. cPanel public_html) kopyalandıysa, bu dizine aşağıdaki gibi bir
 * app-root.php dosyası koyun:
 *
 *     <?php return '/home/kullanici/tfb-pdf';
 */

$appRoot = dirname(__DIR__);

if (is_file(__DIR__ . '/app-root.php')) {
    $appRoot = (string) require __DIR__ . '/app-root.php';
}

/** @var \App\Core\Application $app */
$app = require $appRoot . '/bootstrap/app.php';
$app->runHttp();
