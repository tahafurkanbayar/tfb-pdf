<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Application;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Uygulamayı web sunucusu olmadan, gerçek bootstrap ve middleware zinciriyle çalıştırır.
 */
abstract class AppTestCase extends TestCase
{
    protected function createApp(): Application
    {
        return require APP_ROOT . '/bootstrap/app.php';
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @param array<string, mixed> $post
     */
    protected function request(
        string $method,
        string $path,
        array $headers = [],
        array $cookies = [],
        array $post = [],
        ?Application $app = null,
    ): Response {
        $query = [];
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        $request = new Request(
            $method,
            Request::extractPath($path, ''),
            $query,
            $post,
            [],
            $cookies,
            array_change_key_case($headers, CASE_LOWER),
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost', 'REQUEST_METHOD' => $method],
        );

        return ($app ?? $this->createApp())->handle($request);
    }
}
