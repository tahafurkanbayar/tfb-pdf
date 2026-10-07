<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Exceptions\NotFoundException;
use App\Http\MethodNotAllowedException;
use App\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchesParametersAndConstraints(): void
    {
        $router = new Router();
        $router->get('/{locale:tr|en}/tools/{tool}', fn () => 'tool');
        $router->get('/api/documents/{id:[a-f0-9]{32}}', fn () => 'doc');

        $match = $router->match('GET', '/en/tools/merge');
        self::assertSame(['locale' => 'en', 'tool' => 'merge'], $match['params']);

        $id = str_repeat('ab', 16);
        self::assertSame(['id' => $id], $router->match('GET', '/api/documents/' . $id)['params']);
    }

    public function testConstraintViolationIsNotFound(): void
    {
        $router = new Router();
        $router->get('/api/documents/{id:[a-f0-9]{32}}', fn () => 'doc');

        $this->expectException(NotFoundException::class);
        $router->match('GET', '/api/documents/../../etc/passwd');
    }

    public function testUnknownLocaleIsNotFound(): void
    {
        $router = new Router();
        $router->get('/{locale:tr|en}', fn () => 'home');

        $this->expectException(NotFoundException::class);
        $router->match('GET', '/de');
    }

    public function testWrongMethodIs405WithAllowedList(): void
    {
        $router = new Router();
        $router->post('/api/upload', fn () => 'x');

        try {
            $router->match('GET', '/api/upload');
            self::fail('Expected 405');
        } catch (MethodNotAllowedException $e) {
            self::assertSame(['POST'], $e->allowed);
            self::assertSame(405, $e->httpStatus());
        }
    }

    public function testHeadFallsBackToGet(): void
    {
        $router = new Router();
        $router->get('/x', fn () => 'x');

        self::assertSame([], $router->match('HEAD', '/x')['params']);
    }
}
