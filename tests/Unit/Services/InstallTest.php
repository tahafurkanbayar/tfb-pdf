<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Session;
use App\Exceptions\DatabaseException;
use App\Services\Install\EnvironmentCheck;
use App\Services\Install\InstallGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

/**
 * Kurulum sayfasının saf mantığı: anahtar doğrulama ve kilitleme, veritabanı hata/sürüm sınıflandırması.
 */
final class InstallTest extends TestCase
{
    private const KEY = 'install-key-for-unit-tests';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = TempDirectory::create('install');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->dir);
    }

    private function guard(string $key = self::KEY): InstallGuard
    {
        return new InstallGuard($key, $this->dir . '/attempts.json');
    }

    private static function session(): Session
    {
        return new Session(sys_get_temp_dir(), false);
    }

    public function testEnabledAndWeakKeys(): void
    {
        self::assertFalse($this->guard('')->enabled());
        self::assertTrue($this->guard('short')->keyTooShort());
        self::assertSame('weak', $this->guard('short')->attempt(self::session(), '1.2.3.4', 'short'));
        self::assertFalse($this->guard(str_repeat('a', 15))->authorized(self::session()));
    }

    public function testCorrectKeyAuthorizesSessionAndKeyChangeRevokesIt(): void
    {
        $session = self::session();
        self::assertFalse($this->guard()->authorized($session));
        self::assertSame('ok', $this->guard()->attempt($session, '1.2.3.4', self::KEY));
        self::assertTrue($this->guard()->authorized($session));
        // Oturumda anahtarın kendisi tutulmaz
        self::assertNotSame(self::KEY, $session->get('install_auth'));

        // .env'de anahtar değişirse eski oturum geçersiz
        self::assertFalse($this->guard(self::KEY . '-changed')->authorized($session));

        $this->guard()->logout($session);
        self::assertFalse($this->guard()->authorized($session));
    }

    public function testLockoutWindowPerClient(): void
    {
        $guard = $this->guard();
        $now = 1_800_000_000;
        for ($i = 0; $i < InstallGuard::MAX_FAILURES; $i++) {
            self::assertSame('invalid', $guard->attempt(self::session(), '1.2.3.4', 'wrong', $now));
        }
        self::assertSame('locked', $guard->attempt(self::session(), '1.2.3.4', self::KEY, $now + 60));
        // Başka istemci etkilenmez
        self::assertSame('ok', $guard->attempt(self::session(), '5.6.7.8', self::KEY, $now + 60));
        // Pencere bitince tekrar denenebilir; başarı sayacı sıfırlar
        self::assertSame('ok', $guard->attempt(self::session(), '1.2.3.4', self::KEY, $now + InstallGuard::LOCK_SECONDS));
        self::assertSame(0, $guard->failures('1.2.3.4', $now + InstallGuard::LOCK_SECONDS));
    }

    public function testUnwritableAttemptsFileDoesNotBlockCorrectKey(): void
    {
        $guard = new InstallGuard(self::KEY, $this->dir . '/missing/' . str_repeat('x', 300) . '/attempts.json');
        self::assertSame('invalid', $guard->attempt(self::session(), '1.2.3.4', 'wrong'));
        self::assertSame('ok', $guard->attempt(self::session(), '1.2.3.4', self::KEY));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function connectionErrors(): iterable
    {
        yield 'access denied' => [1045, 'access_denied'];
        yield 'no db access' => [1044, 'access_denied'];
        yield 'unknown database' => [1049, 'unknown_database'];
        yield 'socket' => [2002, 'unreachable'];
        yield 'host' => [2005, 'unreachable'];
        yield 'other' => [1234, 'failed'];
    }

    #[DataProvider('connectionErrors')]
    public function testConnectionProblemCategories(int $code, string $expected): void
    {
        $e = new DatabaseException('Connection failed [' . $code . ']: SQLSTATE[HY000] secret details');
        self::assertSame($expected, EnvironmentCheck::connectionProblem($e));
    }

    public function testServerVersionSupport(): void
    {
        self::assertTrue(EnvironmentCheck::serverSupported('8.0.36'));
        self::assertTrue(EnvironmentCheck::serverSupported('8.4.2-log'));
        self::assertFalse(EnvironmentCheck::serverSupported('5.7.44'));
        self::assertTrue(EnvironmentCheck::serverSupported('10.4.32-MariaDB'));
        self::assertTrue(EnvironmentCheck::serverSupported('11.4.2-MariaDB-log'));
        self::assertFalse(EnvironmentCheck::serverSupported('10.3.39-MariaDB'));
        self::assertFalse(EnvironmentCheck::serverSupported('garbage'));
    }

    public function testSummaryCounts(): void
    {
        $summary = EnvironmentCheck::summary([
            'a' => [['status' => 'ok'], ['status' => 'warning']],
            'b' => [['status' => 'error'], ['status' => 'ok']],
        ]);
        self::assertSame(['ok' => 2, 'warning' => 1, 'error' => 1], $summary);
    }
}
