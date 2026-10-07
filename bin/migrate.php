<?php

declare(strict_types=1);

/*
 * Veritabanı migration aracı.
 *
 *   php bin/migrate.php           Bekleyen migration'ları çalıştırır
 *   php bin/migrate.php status    Durum listesi
 *   php bin/migrate.php schema    database/schema.sql dosyasını yeniden üretir (phpMyAdmin için)
 *
 * SSH erişimi yoksa: web kurulum sayfası (/install) veya phpMyAdmin ile database/schema.sql içe aktarımı.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';

App\Core\Env::load(APP_ROOT . '/.env');
$config = App\Core\Config::fromDirectory(APP_ROOT . '/config');
$migrator = new App\Database\Migrator(
    new App\Core\Database($config->get('database')),
    APP_ROOT . '/database/migrations'
);

$command = $argv[1] ?? 'migrate';

try {
    switch ($command) {
        case 'migrate':
            $ran = $migrator->migrate();
            if ($ran === []) {
                echo "Bekleyen migration yok. Veritabanı güncel.\n";
            }
            foreach ($ran as $name) {
                echo "Uygulandı: $name\n";
            }
            foreach ($migrator->modified() as $name) {
                echo "UYARI: uygulanmış migration dosyası değiştirilmiş: $name\n";
            }
            exit(0);

        case 'status':
            $applied = $migrator->applied();
            foreach (array_keys($migrator->files()) as $name) {
                printf("[%s] %s\n", isset($applied[$name]) ? 'x' : ' ', $name);
            }
            foreach ($migrator->modified() as $name) {
                echo "UYARI: uygulanmış migration dosyası değiştirilmiş: $name\n";
            }
            exit(0);

        case 'schema':
            $target = APP_ROOT . '/database/schema.sql';
            file_put_contents($target, $migrator->buildSchemaSql());
            echo "Yazıldı: database/schema.sql\n";
            exit(0);

        default:
            fwrite(STDERR, "Bilinmeyen komut: $command (migrate | status | schema)\n");
            exit(2);
    }
} catch (App\Exceptions\DatabaseException $e) {
    // CLI yöneticiye yönelik: teknik ayrıntı gösterilebilir, ama şifre içermez
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(1);
}
