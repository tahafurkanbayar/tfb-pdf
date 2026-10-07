<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorStatementsTest extends TestCase
{
    public function testSplitsStatementsAndDropsComments(): void
    {
        $sql = "-- yorum; noktalı virgül içeren\nCREATE TABLE a (\n  id INT\n);\r\n\r\n  -- girintili yorum\nINSERT INTO a VALUES (1);\nINSERT INTO a VALUES (2)";

        self::assertSame([
            "CREATE TABLE a (\n  id INT\n)",
            'INSERT INTO a VALUES (1)',
            'INSERT INTO a VALUES (2)',
        ], Migrator::statements($sql));
    }

    public function testMigrationFilesAreNamedAndOrdered(): void
    {
        $files = glob(APP_ROOT . '/database/migrations/*.sql') ?: [];
        self::assertNotEmpty($files);

        $numbers = [];
        foreach ($files as $file) {
            self::assertMatchesRegularExpression('/^\d{4}_[a-z0-9_]+\.sql$/', basename($file));
            $numbers[] = (int) substr(basename($file), 0, 4);
            // Dosyalarda yalnızca ASCII: farklı phpMyAdmin / istemci kodlamalarında sorun çıkmasın
            self::assertSame(1, preg_match('//u', (string) file_get_contents($file)));
            self::assertDoesNotMatchRegularExpression('/[^\x09\x0A\x0D\x20-\x7E]/', (string) file_get_contents($file), basename($file));
        }

        self::assertSame(range(1, count($numbers)), $numbers, 'Migration numaraları ardışık olmalı');
    }
}
