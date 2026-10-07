<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Config;
use App\Core\Csrf;
use App\Http\Response;
use App\Services\Install\InstallGuard;
use Tests\Support\AppTestCase;
use Tests\Support\TestSchema;

/**
 * Web kurulum sayfası (/install): SSH'siz cPanel kurulumu için erişim koruması, ortam kontrolü
 * ve boş veritabanında migration çalıştırma.
 */
final class InstallHttpTest extends AppTestCase
{
    protected bool $useTestDatabase = true;

    private const KEY = 'test-install-key-0123456789';

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
    }

    private function installApp(string $key = self::KEY): Application
    {
        $app = $this->createApp();
        $c = $app->container();
        $c->get(Config::class)->set('app.install_key', $key);
        // Deneme sayacı gerçek storage/ yerine testin geçici dizinine
        $attempts = $this->storageRoot() . '/cache/install-attempts.json';
        $c->set(InstallGuard::class, fn (): InstallGuard => new InstallGuard($key, $attempts));

        return $app;
    }

    private function post(Application $app, string $path, array $fields = []): Response
    {
        $token = $app->container()->get(Csrf::class)->token();

        return $this->request('POST', $path, ['X-Locale' => 'en'], post: $fields + ['_token' => $token], app: $app);
    }

    private function page(Application $app, string $locale = 'en'): string
    {
        $response = $this->request('GET', '/install', ['Accept-Language' => $locale], app: $app);
        self::assertSame(200, $response->status(), $response->content());
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringContainsString('noindex', (string) $response->header('X-Robots-Tag'));

        return $response->content();
    }

    public function testDisabledWhenInstallKeyIsEmpty(): void
    {
        $app = $this->installApp('');
        self::assertSame(404, $this->request('GET', '/install', app: $app)->status());
        self::assertSame(404, $this->post($app, '/install/login', ['key' => ''])->status());
        self::assertSame(404, $this->post($app, '/install/migrate')->status());
    }

    public function testWeakKeyCannotBeUsed(): void
    {
        $app = $this->installApp('short');
        self::assertStringContainsString('at least 16 characters', $this->page($app));
        $this->post($app, '/install/login', ['key' => 'short']);
        self::assertStringNotContainsString('data-install-summary', $this->page($app));
    }

    public function testLoginRequiresCorrectKeyAndCsrf(): void
    {
        $app = $this->installApp();
        $html = $this->page($app);
        self::assertStringContainsString('name="key"', $html);
        self::assertStringNotContainsString('data-install-summary', $html);

        // CSRF olmadan reddedilir
        self::assertSame(403, $this->request('POST', '/install/login', post: ['key' => self::KEY], app: $app)->status());

        $wrong = $this->post($app, '/install/login', ['key' => 'yanlis-anahtar-0123456789']);
        self::assertSame(302, $wrong->status());
        self::assertStringContainsString('The installation key is incorrect.', $this->page($app));

        $this->post($app, '/install/login', ['key' => self::KEY]);
        $html = $this->page($app);
        self::assertStringContainsString('data-install-summary', $html);
        foreach (['php_version', 'extensions', 'app_key', 'storage_writable', 'db_connection', 'db_version', 'migrations', 'ghostscript'] as $check) {
            self::assertStringContainsString('data-check="' . $check . '"', $html, $check);
        }
        self::assertMatchesRegularExpression('/data-check="db_connection" data-status="ok"/', $html);
        self::assertMatchesRegularExpression('/data-check="migrations" data-status="ok"/', $html);

        // Kimlik bilgileri, anahtarın kendisi ve sunucu yolları dışında teknik ayrıntı sayfada yok
        $db = $app->container()->get(Config::class)->get('database');
        self::assertStringNotContainsString(self::KEY, $html);
        if ((string) $db['password'] !== '') {
            self::assertStringNotContainsString((string) $db['password'], $html);
        }

        // Türkçe arayüz
        self::assertStringContainsString('Veritabanı', $this->page($app, 'tr'));

        // Çıkış
        $this->post($app, '/install/logout');
        self::assertStringNotContainsString('data-install-summary', $this->page($app));
    }

    public function testRepeatedFailuresLockTheLoginEvenForTheCorrectKey(): void
    {
        $app = $this->installApp();
        for ($i = 0; $i < InstallGuard::MAX_FAILURES; $i++) {
            $this->post($app, '/install/login', ['key' => 'yanlis-' . $i . '-0123456789']);
        }

        $this->post($app, '/install/login', ['key' => self::KEY]);
        $html = $this->page($app);
        self::assertStringContainsString('Too many failed attempts', $html);
        self::assertStringNotContainsString('data-install-summary', $html);
        // Sayaç dosyasında istemci adresi düz metin tutulmaz
        self::assertStringNotContainsString('127.0.0.1', (string) file_get_contents($this->storageRoot() . '/cache/install-attempts.json'));
    }

    public function testMigrationsRunOnEmptyDatabase(): void
    {
        TestSchema::dropAllTables(self::db());

        $app = $this->installApp();
        // Uygulama sayfaları yapılandırma tamamlanana kadar çalışmaz, kurulum sayfası çalışır
        $this->post($app, '/install/login', ['key' => self::KEY]);
        $before = $this->page($app);
        self::assertMatchesRegularExpression('/data-check="migrations" data-status="warning"/', $before);
        self::assertStringContainsString('Run migrations', $before);

        $migrate = $this->post($app, '/install/migrate');
        self::assertSame(302, $migrate->status());

        $after = $this->page($app);
        $files = glob(APP_ROOT . '/database/migrations/*.sql') ?: [];
        self::assertStringContainsString(count($files) . ' migrations applied.', $after);
        self::assertMatchesRegularExpression('/data-check="migrations" data-status="ok"/', $after);
        self::assertStringNotContainsString('Run migrations', $after);
        self::assertSame(count($files), (int) self::db()->scalar('SELECT COUNT(*) FROM migrations'));
        foreach (['documents', 'document_versions', 'operations', 'audit_events', 'rate_limits'] as $table) {
            self::assertSame(1, (int) self::db()->scalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]), $table);
        }

        // İkinci çalıştırma bir şey yapmaz
        $this->post($app, '/install/migrate');
        self::assertStringContainsString('There were no pending migrations.', $this->page($app));
    }

    public function testMigrateRequiresAuthorization(): void
    {
        TestSchema::dropAllTables(self::db());
        $app = $this->installApp();

        $response = $this->post($app, '/install/migrate');
        self::assertSame(302, $response->status());
        self::assertSame([], self::db()->select("SHOW TABLES LIKE 'documents'"));
    }
}
