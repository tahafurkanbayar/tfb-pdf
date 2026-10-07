<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @var array<string, mixed> Router ve middleware tarafından eklenen değerler */
    private array $attributes = [];

    /** @var array<string, mixed>|null */
    private ?array $jsonBody = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, list<UploadedFile>> $files
     * @param array<string, string> $cookies
     * @param array<string, string> $headers Küçük harfli başlık adları
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $files = [],
        public readonly array $cookies = [],
        public readonly array $headers = [],
        public readonly array $server = [],
        private readonly string $rawBody = '',
    ) {
    }

    public static function fromGlobals(string $basePath): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $key, 5)))] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', (string) $key))] = (string) $value;
            }
        }

        $files = [];
        foreach ($_FILES as $field => $spec) {
            $files[(string) $field] = UploadedFile::normalize((array) $spec);
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $contentType = $headers['content-type'] ?? '';
        // JSON ve görsel gövdeleri (küçük resim önbelleği) okunur; üst sınır 1 MB
        $readBody = str_contains($contentType, 'application/json') || str_starts_with($contentType, 'image/');
        $rawBody = $readBody ? (string) file_get_contents('php://input', false, null, 0, 1024 * 1024) : '';

        return new self(
            $method,
            self::extractPath((string) ($_SERVER['REQUEST_URI'] ?? '/'), $basePath),
            $_GET,
            $_POST,
            $files,
            array_map('strval', array_filter($_COOKIE, 'is_string')),
            $headers,
            $_SERVER,
            $rawBody,
        );
    }

    /**
     * "/tfb-pdf/tr/tools/merge?x=1" → "/tr/tools/merge" (taban yol ve index.php çıkarılır).
     */
    public static function extractPath(string $requestUri, string $basePath): string
    {
        $path = rawurldecode((string) (parse_url($requestUri, PHP_URL_PATH) ?? '/'));
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath));
        }
        if ($path === '/index.php' || str_starts_with($path, '/index.php/')) {
            $path = substr($path, strlen('/index.php'));
        }

        // Null byte ve ters bölü içeren yollar reddedilir (router hiçbir şeyle eşleşmez)
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return '/__invalid__';
        }

        // Sondaki / kaldırılır: "/tr/" ve "/tr" aynı rotadır
        return '/' . trim($path, '/');
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * POST form alanı veya JSON gövdesindeki değer.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        $json = $this->json();

        return $json[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->jsonBody === null) {
            $decoded = $this->rawBody !== '' ? json_decode($this->rawBody, true, 32) : null;
            $this->jsonBody = is_array($decoded) ? $decoded : [];
        }

        return $this->jsonBody;
    }

    /**
     * @return list<UploadedFile>
     */
    public function files(string $field): array
    {
        return $this->files[$field] ?? [];
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isStateChanging(): bool
    {
        return !in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/');
    }

    public function wantsJson(): bool
    {
        return $this->isApi() || str_contains((string) $this->header('accept'), 'application/json');
    }

    /**
     * @param list<string> $trustedProxies
     */
    public function isSecure(array $trustedProxies = []): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if ((string) ($this->server['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        return $this->fromTrustedProxy($trustedProxies)
            && strtolower((string) $this->header('x-forwarded-proto')) === 'https';
    }

    /**
     * İstemci IP'si. X-Forwarded-For yalnızca güvenilir proxy'den gelirse dikkate alınır.
     *
     * @param list<string> $trustedProxies
     */
    public function ip(array $trustedProxies = []): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!$this->fromTrustedProxy($trustedProxies)) {
            return $remote;
        }

        $forwarded = array_map('trim', explode(',', (string) $this->header('x-forwarded-for', '')));
        $candidate = $forwarded[0] ?? '';

        return filter_var($candidate, FILTER_VALIDATE_IP) !== false ? $candidate : $remote;
    }

    public function userAgent(): string
    {
        return mb_substr((string) $this->header('user-agent', ''), 0, 255);
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @param list<string> $trustedProxies
     */
    private function fromTrustedProxy(array $trustedProxies): bool
    {
        return $trustedProxies !== [] && in_array((string) ($this->server['REMOTE_ADDR'] ?? ''), $trustedProxies, true);
    }
}
