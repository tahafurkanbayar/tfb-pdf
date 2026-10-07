<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\Database;
use App\Exceptions\DatabaseException;

/**
 * Sıralı, yalnızca ileri yönlü SQL migration'ları.
 *
 * - database/migrations/NNNN_ad.sql dosyaları ad sırasına göre çalışır.
 * - Çalışanlar `migrations` tablosunda tutulur (dosya adı + SHA-256 checksum).
 * - Aynı anda iki çalıştırma GET_LOCK ile engellenir (ör. kurulum sayfasında çift tıklama).
 * - DDL ifadeleri MySQL/MariaDB'de örtük commit yaptığından bir dosya yarıda kalırsa
 *   otomatik geri alınamaz; hata hangi dosyada olduğunu belirterek durur.
 */
final class Migrator
{
    private const LOCK_NAME = 'tfb_pdf_migrate';

    public function __construct(
        private readonly Database $db,
        private readonly string $directory,
    ) {
    }

    /**
     * @return array<string, string> dosya adı => tam yol (sıralı)
     */
    public function files(): array
    {
        $files = [];
        foreach (glob($this->directory . '/*.sql') ?: [] as $path) {
            $name = basename($path, '.sql');
            if (preg_match('/^\d{4}_[a-z0-9_]+$/', $name)) {
                $files[$name] = $path;
            }
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    public function ensureMigrationsTable(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(255) NOT NULL,
                checksum CHAR(64) NOT NULL,
                batch INT UNSIGNED NOT NULL,
                applied_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_migrations_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return array<string, string> migration => checksum
     */
    public function applied(): array
    {
        $this->ensureMigrationsTable();
        $rows = $this->db->select('SELECT migration, checksum FROM migrations ORDER BY migration');

        return array_column($rows, 'checksum', 'migration');
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        return array_values(array_diff(array_keys($this->files()), array_keys($this->applied())));
    }

    /**
     * Uygulanmış ama dosyası sonradan değiştirilmiş migration'lar (uyarı amaçlı).
     *
     * @return list<string>
     */
    public function modified(): array
    {
        $modified = [];
        $files = $this->files();
        foreach ($this->applied() as $name => $checksum) {
            if (isset($files[$name]) && !hash_equals($checksum, self::checksum($files[$name]))) {
                $modified[] = $name;
            }
        }

        return $modified;
    }

    /**
     * Bekleyen migration'ları çalıştırır.
     *
     * @return list<string> Çalıştırılan migration adları
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        if ((int) $this->db->scalar('SELECT GET_LOCK(?, 10)', [self::LOCK_NAME]) !== 1) {
            throw new DatabaseException('Another migration process is running.');
        }

        try {
            $pending = $this->pending();
            if ($pending === []) {
                return [];
            }

            $batch = (int) $this->db->scalar('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations');
            $files = $this->files();
            $ran = [];

            foreach ($pending as $name) {
                try {
                    foreach (self::statements((string) file_get_contents($files[$name])) as $statement) {
                        $this->db->pdo()->exec($statement);
                    }
                } catch (\PDOException $e) {
                    throw new DatabaseException(sprintf('Migration %s failed: %s', $name, $e->getMessage()), previous: $e);
                }

                $this->db->insert('migrations', [
                    'migration' => $name,
                    'checksum' => self::checksum($files[$name]),
                    'batch' => $batch,
                    'applied_at' => Database::now(),
                ]);
                $ran[] = $name;
            }

            return $ran;
        } finally {
            $this->db->scalar('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }
    }

    /**
     * SQL dosyasını ifadelere böler. Kurallar (migration dosyaları bunlara uyar):
     * "--" ile başlayan satırlar yorumdur; her ifade satır sonundaki ";" ile biter.
     *
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\r\n|\r|\n/', $sql) ?: [],
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );

        $statements = [];
        foreach (preg_split('/;\s*$/m', implode("\n", $lines)) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    public static function checksum(string $path): string
    {
        // Satır sonu farkları (Windows/Unix) checksum'ı değiştirmesin
        return hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($path)));
    }

    /**
     * phpMyAdmin ile içe aktarılabilecek tek dosyalık şema: tüm migration'lar + migrations kayıtları.
     */
    public function buildSchemaSql(): string
    {
        $out = [
            '-- tfb-pdf veritabani semasi (otomatik uretilir: php bin/migrate.php schema)',
            '-- Bu dosyayi elle duzenlemeyin; database/migrations altindaki dosyalari degistirin.',
            '-- phpMyAdmin > Import ile bos bir veritabanina ice aktarilabilir.',
            '',
            'SET NAMES utf8mb4;',
            "SET time_zone = '+00:00';",
            '',
            'CREATE TABLE IF NOT EXISTS migrations (',
            '    id INT UNSIGNED NOT NULL AUTO_INCREMENT,',
            '    migration VARCHAR(255) NOT NULL,',
            '    checksum CHAR(64) NOT NULL,',
            '    batch INT UNSIGNED NOT NULL,',
            '    applied_at DATETIME NOT NULL,',
            '    PRIMARY KEY (id),',
            '    UNIQUE KEY uq_migrations_migration (migration)',
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
            '',
        ];

        foreach ($this->files() as $name => $path) {
            $out[] = '-- ' . str_repeat('-', 70);
            $out[] = '-- ' . $name;
            $out[] = '-- ' . str_repeat('-', 70);
            foreach (self::statements((string) file_get_contents($path)) as $statement) {
                $out[] = $statement . ';';
                $out[] = '';
            }
            $out[] = sprintf(
                "INSERT INTO migrations (migration, checksum, batch, applied_at) VALUES ('%s', '%s', 1, UTC_TIMESTAMP());",
                $name,
                self::checksum($path)
            );
            $out[] = '';
        }

        return implode("\n", $out);
    }
}
