<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Database\Migrator;

final class TestSchema
{
    /**
     * Yalnızca adı _test ile biten veritabanında çalışır.
     */
    public static function dropAllTables(Database $db): void
    {
        $name = (string) $db->scalar('SELECT DATABASE()');
        if (!str_ends_with($name, '_test')) {
            throw new \LogicException('Refusing to drop tables outside a *_test database: ' . $name);
        }

        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->select('SHOW TABLES') as $row) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) array_values($row)[0]) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public static function migrateFresh(Database $db): void
    {
        self::dropAllTables($db);
        (new Migrator($db, APP_ROOT . '/database/migrations'))->migrate();
    }
}
