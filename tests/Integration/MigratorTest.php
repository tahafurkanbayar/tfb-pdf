<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\Migrator;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestSchema;

final class MigratorTest extends DatabaseTestCase
{
    public function testMigratesFreshDatabaseAndIsIdempotent(): void
    {
        $db = self::db();
        TestSchema::dropAllTables($db);

        $migrator = new Migrator($db, APP_ROOT . '/database/migrations');
        $all = array_keys($migrator->files());

        self::assertSame($all, $migrator->pending());
        self::assertSame($all, $migrator->migrate());
        self::assertSame([], $migrator->pending());
        self::assertSame([], $migrator->migrate(), 'İkinci çalıştırma hiçbir şey yapmamalı');
        self::assertSame([], $migrator->modified());

        $tables = array_column($db->select('SHOW TABLES'), array_key_first($db->select('SHOW TABLES')[0]));
        foreach (['documents', 'document_versions', 'operations', 'audit_events', 'file_expiry', 'signature_requests', 'signature_events', 'settings', 'migrations'] as $table) {
            self::assertContains($table, $tables);
        }

        self::assertSame(str_repeat('0', 64), $db->scalar("SELECT `value` FROM settings WHERE `key` = 'audit_chain_head'"));
    }

    public function testSchemaSqlFileIsUpToDateAndImportable(): void
    {
        $db = self::db();
        $migrator = new Migrator($db, APP_ROOT . '/database/migrations');

        $schemaFile = APP_ROOT . '/database/schema.sql';
        self::assertFileExists($schemaFile);
        self::assertSame(
            $migrator->buildSchemaSql(),
            str_replace("\r\n", "\n", (string) file_get_contents($schemaFile)),
            'database/schema.sql güncel değil: php bin/migrate.php schema'
        );

        // phpMyAdmin içe aktarımını taklit et: boş veritabanına schema.sql
        TestSchema::dropAllTables($db);
        foreach (Migrator::statements((string) file_get_contents($schemaFile)) as $statement) {
            $db->pdo()->exec($statement);
        }

        self::assertSame([], $migrator->pending(), 'schema.sql sonrası bekleyen migration kalmamalı');
        self::assertSame([], $migrator->modified());
    }
}
