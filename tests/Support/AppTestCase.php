<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Application;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Http\UploadedFile;
use App\Services\StorageService;

/**
 * Uygulamayı web sunucusu olmadan, gerçek bootstrap ve middleware zinciriyle çalıştırır.
 * withDatabase() çağrılırsa test veritabanı (*_test) ve geçici bir storage dizini kullanılır;
 * geliştirme veritabanına ve gerçek storage/ dizinine dokunulmaz.
 */
abstract class AppTestCase extends DatabaseTestCase
{
    private ?string $storageRoot = null;

    protected bool $useTestDatabase = false;

    protected function tearDown(): void
    {
        if ($this->storageRoot !== null) {
            TempDirectory::remove($this->storageRoot);
            $this->storageRoot = null;
        }
    }

    protected function storageRoot(): string
    {
        if ($this->storageRoot === null) {
            $this->storageRoot = TempDirectory::create('app-storage');
            (new StorageService($this->storageRoot))->ensureDirectories();
        }

        return $this->storageRoot;
    }

    protected function createApp(): Application
    {
        /** @var Application $app */
        $app = require APP_ROOT . '/bootstrap/app.php';

        if ($this->useTestDatabase) {
            $db = self::db();
            $root = $this->storageRoot();
            $c = $app->container();
            $c->set(Database::class, fn (): Database => $db);
            $c->set(StorageService::class, fn (): StorageService => new StorageService($root));
        }

        return $app;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @param array<string, mixed> $post
     * @param array<string, list<UploadedFile>> $files
     */
    protected function request(
        string $method,
        string $path,
        array $headers = [],
        array $cookies = [],
        array $post = [],
        ?Application $app = null,
        array $files = [],
        string $body = '',
    ): Response {
        $query = [];
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        $request = new Request(
            $method,
            Request::extractPath($path, ''),
            $query,
            $post,
            $files,
            $cookies,
            array_change_key_case($headers, CASE_LOWER),
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost', 'REQUEST_METHOD' => $method],
            $body,
        );

        return ($app ?? $this->createApp())->handle($request);
    }

    /**
     * Yanıttaki Set-Cookie değerleri: ad => değer
     *
     * @return array<string, string>
     */
    protected static function cookiesFrom(Response $response): array
    {
        $out = [];
        foreach ($response->cookies() as $cookie) {
            $out[$cookie['name']] = $cookie['value'];
        }

        return $out;
    }

    /**
     * FileResponse gövdesini yakalar.
     */
    protected static function body(Response $response): string
    {
        ob_start();
        $response->send();

        return (string) ob_get_clean();
    }
}
