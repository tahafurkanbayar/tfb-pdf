<?php

declare(strict_types=1);

namespace App\Services\Install;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Database\Migrator;
use App\Exceptions\DatabaseException;
use App\Services\StorageService;
use App\Support\Size;
use App\Tools\Capabilities;
use App\Tools\ProcessRunner;

/**
 * Kurulum sayfası için sunucu ortamı kontrolü (spec §3, §48). Her madde:
 * ['id' => ..., 'status' => ok|warning|error, 'message' => çeviri anahtarı, 'params' => [...]].
 * Mesajlar çeviri anahtarıdır; ham hata metni, parola veya kullanıcı adı döndürülmez.
 */
final class EnvironmentCheck
{
    public const OK = 'ok';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    public const MIN_PHP = '8.2.0';
    public const RECOMMENDED_PHP = '8.3.0';

    /** composer.json "require" ile aynı */
    public const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'zlib', 'hash', 'session'];
    /** Yoksa ilgili özellik kapanır, uygulama çalışır */
    public const OPTIONAL_EXTENSIONS = ['fileinfo', 'gd', 'zip', 'openssl'];

    public function __construct(
        private readonly Config $config,
        private readonly Database $db,
        private readonly StorageService $storage,
        private readonly Capabilities $capabilities,
        private readonly Logger $logger,
        private readonly string $appRoot,
    ) {
    }

    public function migrator(): Migrator
    {
        return new Migrator($this->db, $this->appRoot . '/database/migrations');
    }

    /**
     * @return array<string, list<array{id: string, status: string, message: string, params: array<string, string|int>}>>
     */
    public function run(bool $secureRequest): array
    {
        return [
            'php' => $this->php(),
            'config' => $this->configuration($secureRequest),
            'storage' => $this->storageChecks(),
            'database' => $this->database(),
            'tools' => $this->tools(),
        ];
    }

    /**
     * @param array<string, list<array{status: string}>> $groups
     * @return array{ok: int, warning: int, error: int}
     */
    public static function summary(array $groups): array
    {
        $counts = [self::OK => 0, self::WARNING => 0, self::ERROR => 0];
        foreach ($groups as $items) {
            foreach ($items as $item) {
                $counts[$item['status']]++;
            }
        }

        return $counts;
    }

    /**
     * @param array<string, string|int> $params
     * @return array{id: string, status: string, message: string, params: array<string, string|int>}
     */
    private static function item(string $id, string $status, string $message, array $params = []): array
    {
        return ['id' => $id, 'status' => $status, 'message' => 'install.msg.' . $message, 'params' => $params];
    }

    /**
     * @return list<array{id: string, status: string, message: string, params: array<string, string|int>}>
     */
    private function php(): array
    {
        $items = [];
        $items[] = match (true) {
            version_compare(PHP_VERSION, self::MIN_PHP, '<') => self::item('php_version', self::ERROR, 'php_too_old', ['version' => PHP_VERSION, 'min' => '8.2']),
            version_compare(PHP_VERSION, self::RECOMMENDED_PHP, '<') => self::item('php_version', self::OK, 'php_supported', ['version' => PHP_VERSION, 'recommended' => '8.3']),
            default => self::item('php_version', self::OK, 'php_ok', ['version' => PHP_VERSION]),
        };

        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, static fn (string $ext): bool => !extension_loaded($ext)));
        $items[] = $missing === []
            ? self::item('extensions', self::OK, 'extensions_ok')
            : self::item('extensions', self::ERROR, 'extensions_missing', ['list' => implode(', ', $missing)]);

        $optional = array_values(array_filter(self::OPTIONAL_EXTENSIONS, static fn (string $ext): bool => !extension_loaded($ext)));
        $items[] = $optional === []
            ? self::item('optional_extensions', self::OK, 'optional_extensions_ok')
            : self::item('optional_extensions', self::WARNING, 'optional_extensions_missing', ['list' => implode(', ', $optional)]);

        // Etkin yükleme sınırı: uygulama ayarı ile php.ini değerlerinin en küçüğü
        $app = (int) $this->config->get('limits.max_upload_size');
        $ini = min(Size::fromIni('upload_max_filesize'), Size::fromIni('post_max_size'));
        $items[] = $ini >= $app
            ? self::item('upload_limit', self::OK, 'upload_limit_ok', ['size' => Size::format($app)])
            : self::item('upload_limit', self::WARNING, 'upload_limit_low', ['ini' => Size::format($ini), 'app' => Size::format($app)]);

        $memory = (string) ini_get('memory_limit');
        $items[] = $memory === '-1' || Size::parse($memory) >= 128 * 1024 * 1024
            ? self::item('memory_limit', self::OK, 'memory_ok', ['value' => $memory])
            : self::item('memory_limit', self::WARNING, 'memory_low', ['value' => $memory]);

        $time = (int) ini_get('max_execution_time');
        $items[] = $time === 0 || $time >= 60
            ? self::item('execution_time', self::OK, 'execution_time_ok', ['seconds' => $time])
            : self::item('execution_time', self::WARNING, 'execution_time_low', ['seconds' => $time]);

        return $items;
    }

    /**
     * @return list<array{id: string, status: string, message: string, params: array<string, string|int>}>
     */
    private function configuration(bool $secureRequest): array
    {
        $items = [];
        $items[] = is_file($this->appRoot . '/.env')
            ? self::item('env_file', self::OK, 'env_ok')
            : self::item('env_file', self::WARNING, 'env_missing');

        $items[] = strlen((string) $this->config->get('app.key')) >= 32
            ? self::item('app_key', self::OK, 'app_key_ok')
            // Önerilen değer yalnızca kurulum anahtarıyla giriş yapmış yöneticiye gösterilir; hiçbir yere kaydedilmez
            : self::item('app_key', self::ERROR, 'app_key_missing', ['suggestion' => bin2hex(random_bytes(32))]);

        $items[] = (string) $this->config->get('app.url') !== ''
            ? self::item('app_url', self::OK, 'app_url_ok', ['url' => (string) $this->config->get('app.url')])
            : self::item('app_url', self::WARNING, 'app_url_missing');

        $items[] = $this->config->get('app.debug') === true && $this->config->get('app.env') === 'local'
            ? self::item('debug', self::WARNING, 'debug_on')
            : self::item('debug', self::OK, 'debug_off');

        $forceHttps = $this->config->get('app.force_https') === true;
        $items[] = match (true) {
            $secureRequest && $forceHttps => self::item('https', self::OK, 'https_ok'),
            $secureRequest => self::item('https', self::WARNING, 'https_not_forced'),
            default => self::item('https', self::WARNING, 'https_missing'),
        };

        return $items;
    }

    /**
     * @return list<array{id: string, status: string, message: string, params: array<string, string|int>}>
     */
    private function storageChecks(): array
    {
        $items = [];
        $problems = $this->storage->ensureDirectories();
        $items[] = $problems === []
            ? self::item('storage_writable', self::OK, 'storage_ok')
            : self::item('storage_writable', self::ERROR, 'storage_not_writable', ['list' => implode(', ', $problems)]);

        // Depolama dizini public/ içinde olmamalı (dosyalar doğrudan URL ile indirilebilir hale gelir)
        $storage = realpath($this->storage->root()) ?: $this->storage->root();
        $public = realpath($this->appRoot . '/public') ?: $this->appRoot . '/public';
        $items[] = self::isInside($storage, $public)
            ? self::item('storage_location', self::ERROR, 'storage_public')
            : self::item('storage_location', self::OK, 'storage_private');

        $free = function_exists('disk_free_space') ? @disk_free_space($this->storage->root()) : false;
        if ($free !== false) {
            $items[] = $free >= 500 * 1024 * 1024
                ? self::item('disk_space', self::OK, 'disk_ok', ['size' => Size::format((int) $free)])
                : self::item('disk_space', self::WARNING, 'disk_low', ['size' => Size::format((int) $free)]);
        }

        return $items;
    }

    /**
     * @return list<array{id: string, status: string, message: string, params: array<string, string|int>}>
     */
    private function database(): array
    {
        if (!$this->db->isConfigured()) {
            return [self::item('db_connection', self::ERROR, 'db_not_configured')];
        }

        try {
            $version = (string) $this->db->scalar('SELECT VERSION()');
        } catch (DatabaseException $e) {
            $this->logger->warning('Installer database check failed', ['exception' => $e]);

            return [self::item('db_connection', self::ERROR, 'db_' . self::connectionProblem($e))];
        }

        $items = [self::item('db_connection', self::OK, 'db_ok')];
        $items[] = self::serverSupported($version)
            ? self::item('db_version', self::OK, 'db_version_ok', ['version' => self::shortVersion($version)])
            : self::item('db_version', self::ERROR, 'db_version_old', ['version' => self::shortVersion($version)]);

        try {
            $migrator = $this->migrator();
            $pending = count($migrator->pending());
            $modified = $migrator->modified();
            $items[] = match (true) {
                $modified !== [] => self::item('migrations', self::ERROR, 'migrations_modified', ['list' => implode(', ', $modified)]),
                $pending > 0 => self::item('migrations', self::WARNING, 'migrations_pending', ['count' => $pending]),
                default => self::item('migrations', self::OK, 'migrations_ok', ['count' => count($migrator->files())]),
            };
        } catch (DatabaseException|\PDOException $e) {
            $this->logger->warning('Installer migration status failed', ['exception' => $e]);
            $items[] = self::item('migrations', self::ERROR, 'migrations_unknown');
        }

        return $items;
    }

    /**
     * @return list<array{id: string, status: string, message: string, params: array<string, string|int>}>
     */
    private function tools(): array
    {
        // Opsiyonel araçlar: yoksa uyarı değil bilgi (ok + "kapalı" mesajı); uygulama onlarsız çalışır
        $items = [];
        $items[] = ProcessRunner::available()
            ? self::item('proc_open', self::OK, 'proc_open_ok')
            : self::item('proc_open', self::WARNING, 'proc_open_disabled');
        foreach (['ghostscript', 'office', 'ocr'] as $tool) {
            $items[] = $this->capabilities->{$tool}()
                ? self::item($tool, self::OK, 'tool_available')
                : self::item($tool, self::OK, 'tool_unavailable');
        }
        $items[] = (string) $this->config->get('mail.host') !== ''
            ? self::item('mail', self::OK, 'mail_configured')
            : self::item('mail', self::OK, 'mail_disabled');

        return $items;
    }

    /**
     * PDO bağlantı hatasını kullanıcıya gösterilebilir bir kategoriye indirger.
     */
    public static function connectionProblem(DatabaseException $e): string
    {
        $previous = $e->getPrevious();
        $code = $previous instanceof \PDOException ? (int) ($previous->errorInfo[1] ?? $previous->getCode()) : 0;
        if ($code === 0 && preg_match('/\[(\d{4})\]/', $e->getMessage(), $m)) {
            $code = (int) $m[1];
        }

        return match ($code) {
            1044, 1045 => 'access_denied',
            1049 => 'unknown_database',
            2002, 2003, 2005, 2006 => 'unreachable',
            default => 'failed',
        };
    }

    /**
     * MySQL 8.0+ veya MariaDB 10.4+.
     */
    public static function serverSupported(string $version): bool
    {
        if (!preg_match('/^(\d+)\.(\d+)/', $version, $m)) {
            return false;
        }
        $numeric = $m[1] . '.' . $m[2];

        return stripos($version, 'mariadb') !== false
            ? version_compare($numeric, '10.4', '>=')
            : version_compare($numeric, '8.0', '>=');
    }

    private static function shortVersion(string $version): string
    {
        // "10.4.32-MariaDB-log" → "10.4.32 MariaDB"; derleme ayrıntıları gösterilmez
        preg_match('/^\d+\.\d+(\.\d+)?/', $version, $m);

        return ($m[0] ?? '?') . (stripos($version, 'mariadb') !== false ? ' MariaDB' : ' MySQL');
    }

    private static function isInside(string $path, string $parent): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/') . '/';
        $parent = rtrim(str_replace('\\', '/', $parent), '/') . '/';

        return str_starts_with(strtolower($path), strtolower($parent));
    }
}
