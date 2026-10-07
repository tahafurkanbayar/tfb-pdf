<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\DatabaseException;

/**
 * İnce PDO sarmalayıcı. Tüm sorgular prepared statement ile çalışır (SQL injection koruması).
 * Bağlantı ilk sorguda kurulur; veritabanı gerektirmeyen sayfalar bağlantı açmaz.
 */
final class Database
{
    private ?\PDO $pdo = null;

    private int $transactionDepth = 0;

    /**
     * @param array{host: string, port: int, socket: string, database: string, username: string, password: string, charset: string, collation: string} $config
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly array $config,
    ) {
    }

    public static function fromPdo(\PDO $pdo): self
    {
        $db = new self(['host' => '', 'port' => 0, 'socket' => '', 'database' => '', 'username' => '', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        $db->pdo = $pdo;

        return $db;
    }

    public function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect();
        }

        return $this->pdo;
    }

    public function isConfigured(): bool
    {
        return $this->config['database'] !== '' && $this->config['username'] !== '';
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * INSERT / UPDATE / DELETE. Etkilenen satır sayısını döndürür.
     *
     * @param array<string|int, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @param array<string, mixed> $values Sütun => değer. Sütun adları kodda sabittir, kullanıcı girdisi değildir.
     */
    public function insert(string $table, array $values): int
    {
        $columns = array_keys($values);
        foreach ([$table, ...$columns] as $identifier) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
                throw new DatabaseException('Invalid identifier: ' . $identifier);
            }
        }

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        $this->run($sql, array_values($values));

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Callback'i transaction içinde çalıştırır. Hata olursa rollback yapar ve hatayı yeniden fırlatır.
     * İç içe çağrılar dıştaki transaction'a katılır.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();

        if ($this->transactionDepth > 0) {
            $this->transactionDepth++;
            try {
                return $callback($this);
            } finally {
                $this->transactionDepth--;
            }
        }

        $pdo->beginTransaction();
        $this->transactionDepth = 1;

        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            $this->transactionDepth = 0;
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    /**
     * Şu anki UTC zaman damgası (DATETIME sütunları için).
     */
    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @param array<string|int, mixed> $params
     */
    private function run(string $sql, array $params): \PDOStatement
    {
        try {
            $statement = $this->pdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $statement->bindValue(
                    is_int($key) ? $key + 1 : ':' . ltrim($key, ':'),
                    $value,
                    match (true) {
                        is_int($value) => \PDO::PARAM_INT,
                        is_bool($value) => \PDO::PARAM_BOOL,
                        $value === null => \PDO::PARAM_NULL,
                        default => \PDO::PARAM_STR,
                    }
                );
            }
            $statement->execute();

            return $statement;
        } catch (\PDOException $e) {
            // SQL ve parametreler kullanıcıya asla gösterilmez; yalnızca log'a SQLSTATE ile gider
            throw new DatabaseException('Query failed [' . $e->getCode() . ']: ' . $e->getMessage(), previous: $e);
        }
    }

    private function connect(): \PDO
    {
        if (!$this->isConfigured()) {
            throw new DatabaseException('Database is not configured (DB_DATABASE / DB_USERNAME missing).');
        }

        $c = $this->config;
        $dsn = $c['socket'] !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $c['socket'], $c['database'], $c['charset'])
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['database'], $c['charset']);

        try {
            $pdo = new \PDO($dsn, $c['username'], $c['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
                \PDO::ATTR_TIMEOUT => 5,
            ]);

            // Sunucu ayarından bağımsız tutarlı davranış: strict mod ve UTC
            $pdo->exec(sprintf("SET NAMES %s COLLATE %s", $c['charset'], $c['collation']));
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $pdo->exec("SET time_zone = '+00:00'");

            return $pdo;
        } catch (\PDOException $e) {
            // PDO bağlantı mesajları şifre içermez; host/kullanıcı adı yalnızca log'a gider
            throw new DatabaseException('Connection failed [' . $e->getCode() . ']: ' . $e->getMessage(), previous: $e);
        }
    }
}
