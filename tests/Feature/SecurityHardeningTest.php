<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Exceptions\RateLimitException;
use App\Http\Middleware\ThrottleRequests;
use App\Http\UploadedFile;
use App\Security\Hmac;
use App\Security\RateLimiter;
use Tests\Support\AppTestCase;
use Tests\Support\TestPdf;
use Tests\Support\TestSchema;

/**
 * Güvenlik sertleştirme (spec §31–32).
 */
final class SecurityHardeningTest extends AppTestCase
{
    protected bool $useTestDatabase = true;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
    }

    public function testRateLimiterCountsPerActionAndClientWithoutStoringIp(): void
    {
        $limiter = new RateLimiter(self::db(), new Hmac(str_repeat('k', 64)));

        $limiter->hit('upload', '203.0.113.9', 2);
        $limiter->hit('upload', '203.0.113.9', 2);
        $limiter->hit('operation', '203.0.113.9', 2);   // ayrı eylem
        $limiter->hit('upload', '198.51.100.1', 2);     // ayrı istemci

        try {
            $limiter->hit('upload', '203.0.113.9', 2);
            self::fail('Expected rate limit');
        } catch (RateLimitException $e) {
            self::assertSame('errors.rate_limited', $e->messageKey());
            self::assertSame(429, $e->httpStatus());
        }

        $limiter->hit('upload', '203.0.113.9', 0); // 0 = sınırsız
        $dump = json_encode(self::db()->select('SELECT * FROM rate_limits'));
        self::assertStringNotContainsString('203.0.113.9', (string) $dump);
    }

    public function testUploadsAreThrottledOverHttpWithTranslatedMessage(): void
    {
        $app = $this->createApp();
        $app->container()->set(ThrottleRequests::class, fn () => new ThrottleRequests(
            new RateLimiter(self::db(), $app->container()->get(Hmac::class)),
            ['upload' => 2, 'operation' => 100, 'signature' => 100],
            []
        ));
        $token = $app->container()->get(Csrf::class)->token();
        $path = TestPdf::create($this->storageRoot() . '/temporary/r.pdf', 1);

        $statuses = [];
        for ($i = 0; $i < 3; $i++) {
            copy($path, $path . $i);
            $response = $this->request('POST', '/api/documents', ['Accept' => 'application/json', 'X-CSRF-Token' => $token, 'X-Locale' => 'tr'],
                app: $app, files: ['file' => [UploadedFile::fromPath($path . $i, 'r.pdf')]]);
            $statuses[] = $response->status();
        }

        self::assertSame([201, 201, 429], $statuses);
        $data = json_decode($response->content(), true);
        self::assertSame('Kısa sürede çok fazla istek gönderdiniz. Lütfen biraz bekleyip tekrar deneyin.', $data['error']['message']);
        self::assertSame('60', $response->header('Retry-After'));
        self::assertSame(2, (int) self::db()->scalar('SELECT COUNT(*) FROM documents'));
    }

    public function testUnconfiguredApplicationShowsFriendlyPage(): void
    {
        $app = $this->createApp();
        $app->container()->get(Config::class)->set('app.key', '');

        $response = $this->request('GET', '/tr', app: $app);

        self::assertSame(503, $response->status());
        self::assertStringContainsString('Uygulama henüz yapılandırılmamış', $response->content());
    }

    public function testProductionNeverShowsTechnicalDetails(): void
    {
        $app = $this->createApp();
        // APP_DEBUG=true olsa bile APP_ENV=production iken ayrıntı gösterilmez
        $app->container()->set(ErrorHandler::class, fn () => new ErrorHandler(new Logger($this->storageRoot() . '/logs'), false));
        $app->container()->get(Config::class)->set('app.debug', true);
        $app->container()->get(Config::class)->set('app.env', 'production');

        $json = $this->request('GET', '/api/documents/' . str_repeat('a', 32), ['Accept' => 'application/json'], ['tfb_owner' => str_repeat('b', 64)], app: $app);
        $html = $this->request('GET', '/tr/olmayan', app: $app);

        foreach ([$json->content(), $html->content()] as $body) {
            foreach (['Exception', 'SELECT', 'C:\\', '/var/', 'xampp', 'Router.php', 'stack', 'DB_PASSWORD'] as $leak) {
                self::assertStringNotContainsString($leak, $body, $leak);
            }
        }
        self::assertArrayNotHasKey('debug', json_decode($json->content(), true)['error']);
    }

    public function testDownloadResponsesAreSandboxedAndNotSniffed(): void
    {
        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();
        $path = TestPdf::create($this->storageRoot() . '/temporary/d.pdf', 1);
        $upload = $this->request('POST', '/api/documents', ['Accept' => 'application/json', 'X-CSRF-Token' => $token], app: $app,
            files: ['file' => [UploadedFile::fromPath($path, '<script>Tom & Jerry.pdf')]]);
        $owner = self::cookiesFrom($upload)['tfb_owner'];
        $id = json_decode($upload->content(), true)['document']['id'];

        $download = $this->request('GET', '/api/documents/' . $id . '/versions/0/download?inline=1', cookies: ['tfb_owner' => $owner], app: $app);

        self::assertSame('nosniff', $download->header('X-Content-Type-Options'));
        self::assertStringContainsString('sandbox', (string) $download->header('Content-Security-Policy'));
        self::assertStringNotContainsString('<script>', (string) $download->header('Content-Disposition'));

        // Belge adı sayfada kaçışlanır (XSS)
        $page = $this->request('GET', '/tr/documents/' . $id, cookies: ['tfb_owner' => $owner], app: $app);
        // < > yüklemede temizlenir; kalan özel karakterler HTML çıktısında kaçışlanır
        self::assertStringNotContainsString('<script>', $page->content());
        self::assertStringContainsString('scriptTom &amp; Jerry.pdf', $page->content());
    }
}
