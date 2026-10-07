<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\DatabaseException;
use Tests\Support\DatabaseTestCase;

final class DatabaseTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        self::db()->pdo()->exec('CREATE TEMPORARY TABLE tfb_tmp (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL) ENGINE=InnoDB');
    }

    protected function tearDown(): void
    {
        self::db()->pdo()->exec('DROP TEMPORARY TABLE IF EXISTS tfb_tmp');
    }

    public function testSessionIsStrictAndUtc(): void
    {
        $db = self::db();

        self::assertStringContainsString('STRICT_TRANS_TABLES', (string) $db->scalar('SELECT @@SESSION.sql_mode'));
        self::assertSame('+00:00', $db->scalar('SELECT @@SESSION.time_zone'));
        self::assertSame('utf8mb4', $db->scalar('SELECT @@character_set_connection'));
    }

    public function testPreparedStatementsKeepInjectionPayloadAsData(): void
    {
        $db = self::db();
        $payload = "x'); DROP TABLE tfb_tmp; -- ğüşiöç";

        $id = $db->insert('tfb_tmp', ['name' => $payload]);

        self::assertSame($payload, $db->scalar('SELECT name FROM tfb_tmp WHERE id = ?', [$id]));
        self::assertSame(1, (int) $db->scalar('SELECT COUNT(*) FROM tfb_tmp WHERE name = :name', ['name' => $payload]));
    }

    public function testTransactionCommitsAndRollsBack(): void
    {
        $db = self::db();

        $db->transaction(fn () => $db->insert('tfb_tmp', ['name' => 'committed']));

        try {
            $db->transaction(function () use ($db): void {
                $db->insert('tfb_tmp', ['name' => 'rolled back']);
                // İç içe transaction dıştakine katılır
                $db->transaction(fn () => $db->insert('tfb_tmp', ['name' => 'inner']));
                throw new \RuntimeException('boom');
            });
            self::fail('Exception expected');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertSame(['committed'], array_column($db->select('SELECT name FROM tfb_tmp ORDER BY id'), 'name'));
        self::assertFalse($db->inTransaction());
    }

    public function testQueryErrorsBecomeDatabaseExceptions(): void
    {
        $this->expectException(DatabaseException::class);
        self::db()->select('SELECT * FROM table_that_does_not_exist_tfb');
    }

    public function testInsertRejectsUnsafeIdentifiers(): void
    {
        $this->expectException(DatabaseException::class);
        self::db()->insert('tfb_tmp', ['name`; DROP TABLE x; --' => 'a']);
    }
}
