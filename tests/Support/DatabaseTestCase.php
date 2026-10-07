<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Core\Env;
use App\Exceptions\DatabaseException;
use PHPUnit\Framework\TestCase;

/**
 * Gerçek MySQL/MariaDB test veritabanı kullanan testler için temel sınıf.
 *
 * Bağlantı bilgileri .env'den alınır, veritabanı adı DB_TEST_DATABASE (varsayılan: tfb_pdf_test).
 * Güvenlik: adı "_test" ile bitmeyen bir veritabanında asla çalışmaz.
 * Veritabanına ulaşılamazsa testler "skipped" olarak işaretlenir (başarılı sayılmaz).
 */
abstract class DatabaseTestCase extends TestCase
{
    private static ?Database $db = null;

    private static ?string $skipReason = null;

    protected static function db(): Database
    {
        if (self::$db !== null) {
            return self::$db;
        }
        if (self::$skipReason !== null) {
            self::markTestSkipped(self::$skipReason);
        }

        $name = Env::string('DB_TEST_DATABASE', 'tfb_pdf_test');
        if (!str_ends_with($name, '_test')) {
            self::$skipReason = 'DB_TEST_DATABASE "_test" ile bitmeli (güvenlik).';
            self::markTestSkipped(self::$skipReason);
        }

        $db = new Database([
            'host' => Env::string('DB_HOST', '127.0.0.1'),
            'port' => Env::int('DB_PORT', 3306),
            'socket' => Env::string('DB_SOCKET', ''),
            'database' => $name,
            'username' => Env::string('DB_USERNAME', 'root'),
            'password' => Env::string('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        try {
            $db->pdo();
        } catch (DatabaseException $e) {
            self::$skipReason = 'Test veritabanına bağlanılamadı: ' . $e->getMessage();
            self::markTestSkipped(self::$skipReason);
        }

        return self::$db = $db;
    }
}
