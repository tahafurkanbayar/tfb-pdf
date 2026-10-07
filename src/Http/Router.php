<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\NotFoundException;

/**
 * Basit regex router. Desen örnekleri:
 *   /{locale}/tools/{tool}
 *   /api/documents/{id:[a-f0-9]{32}}
 * Parametre kısıtı verilmezse [^/]+ kullanılır.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: callable|array{class-string, string}, name: ?string}> */
    private array $routes = [];

    /**
     * @param callable|array{class-string, string} $handler
     */
    public function add(string $method, string $pattern, callable|array $handler, ?string $name = null): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => self::compile($pattern),
            'handler' => $handler,
            'name' => $name,
        ];
    }

    /** @param callable|array{class-string, string} $handler */
    public function get(string $pattern, callable|array $handler, ?string $name = null): void
    {
        $this->add('GET', $pattern, $handler, $name);
    }

    /** @param callable|array{class-string, string} $handler */
    public function post(string $pattern, callable|array $handler, ?string $name = null): void
    {
        $this->add('POST', $pattern, $handler, $name);
    }

    /** @param callable|array{class-string, string} $handler */
    public function put(string $pattern, callable|array $handler, ?string $name = null): void
    {
        $this->add('PUT', $pattern, $handler, $name);
    }

    /** @param callable|array{class-string, string} $handler */
    public function delete(string $pattern, callable|array $handler, ?string $name = null): void
    {
        $this->add('DELETE', $pattern, $handler, $name);
    }

    /**
     * @return array{handler: callable|array{class-string, string}, params: array<string, string>}
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $routeMethod = $route['method'];
            if ($routeMethod !== $method && !($method === 'HEAD' && $routeMethod === 'GET')) {
                $allowed[] = $routeMethod;
                continue;
            }

            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            return ['handler' => $route['handler'], 'params' => array_map('strval', $params)];
        }

        if ($allowed !== []) {
            throw new MethodNotAllowedException(array_values(array_unique($allowed)));
        }

        throw new NotFoundException('No route for ' . $method . ' ' . $path);
    }

    private static function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            // Kısıt içinde {32} / {2,5} gibi tekrar sayılarına izin verilir
            '/\{([a-z_]+)(?::((?:[^{}]|\{\d+(?:,\d*)?\})+))?\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . (($m[2] ?? '') !== '' ? $m[2] : '[^/]+') . ')',
            $pattern
        );

        return '#^' . $regex . '$#';
    }
}
