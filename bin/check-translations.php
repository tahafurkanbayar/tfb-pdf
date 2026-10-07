<?php

declare(strict_types=1);

/*
 * Eksik / tutarsız çeviri anahtarlarını bulur.
 * Kullanım: php bin/check-translations.php
 * Çıkış kodu: 0 = sorun yok, 1 = sorun var
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';

$config = require APP_ROOT . '/config/i18n.php';
$checker = new App\I18n\TranslationChecker(APP_ROOT . '/resources/lang', array_keys($config['locales']));

$problems = $checker->check([
    APP_ROOT . '/src',
    APP_ROOT . '/resources/views',
    APP_ROOT . '/routes',
    APP_ROOT . '/public/assets/js',
]);

$catalog = $checker->catalog();
foreach ($catalog as $locale => $keys) {
    fwrite(STDOUT, sprintf("%s: %d anahtar\n", $locale, count($keys)));
}

if ($problems === []) {
    fwrite(STDOUT, "OK: çeviriler eksiksiz.\n");
    exit(0);
}

foreach ($problems as $problem) {
    fwrite(STDOUT, '  - ' . $problem . "\n");
}
fwrite(STDOUT, sprintf("HATA: %d sorun bulundu.\n", count($problems)));
exit(1);
