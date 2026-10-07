<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\AppTestCase;

final class CoreHttpTest extends AppTestCase
{
    public function testRootRedirectsByBrowserLanguageAndCookie(): void
    {
        $response = $this->request('GET', '/', ['Accept-Language' => 'en-US,en;q=0.9']);
        self::assertSame(302, $response->status());
        self::assertStringEndsWith('/en/', (string) $response->header('Location'));

        // Açık tercih tarayıcı dilinden önce gelir
        $response = $this->request('GET', '/', ['Accept-Language' => 'en'], ['tfb_locale' => 'tr']);
        self::assertStringEndsWith('/tr/', (string) $response->header('Location'));

        // Desteklenmeyen dil → Türkçe
        $response = $this->request('GET', '/', ['Accept-Language' => 'de-DE']);
        self::assertStringEndsWith('/tr/', (string) $response->header('Location'));
    }

    public function testHomeRendersInBothLanguagesWithSecurityHeaders(): void
    {
        $tr = $this->request('GET', '/tr');
        self::assertSame(200, $tr->status());
        self::assertStringContainsString('<html lang="tr">', $tr->content());
        self::assertStringContainsString('PDF Birleştir', $tr->content());
        self::assertStringContainsString('üçüncü taraf PDF işleme servislerine gönderilmez', $tr->content());

        $en = $this->request('GET', '/en');
        self::assertStringContainsString('<html lang="en">', $en->content());
        self::assertStringContainsString('Merge PDF', $en->content());
        self::assertStringNotContainsString('PDF Birleştir', $en->content());

        self::assertStringContainsString("script-src 'self'", (string) $en->header('Content-Security-Policy'));
        self::assertSame('DENY', $en->header('X-Frame-Options'));
        self::assertSame('nosniff', $en->header('X-Content-Type-Options'));
        self::assertSame('en', $en->header('Content-Language'));
        // Inline script yok (CSP); yalnızca JSON veri bloğu
        self::assertDoesNotMatchRegularExpression('/<script>(?!\s*<\/script>)/', $en->content());
    }

    public function testLanguageSwitchKeepsPageAndSetsCookie(): void
    {
        $response = $this->request('GET', '/language/en?return=' . rawurlencode('/tr/about'));

        self::assertSame(302, $response->status());
        self::assertStringEndsWith('/en/about', (string) $response->header('Location'));
        $cookie = $response->cookies()[0];
        self::assertSame(['tfb_locale', 'en'], [$cookie['name'], $cookie['value']]);
        self::assertTrue($cookie['options']['httponly']);
    }

    public function testLanguageSwitchBlocksOpenRedirect(): void
    {
        $response = $this->request('GET', '/language/tr?return=' . rawurlencode('//evil.example/x'));

        self::assertStringEndsWith('/tr/', (string) $response->header('Location'));
        self::assertStringNotContainsString('evil', (string) $response->header('Location'));
    }

    public function testNotFoundPageIsTranslatedAndLeaksNothing(): void
    {
        $tr = $this->request('GET', '/tr/olmayan-sayfa');
        self::assertSame(404, $tr->status());
        self::assertStringContainsString('Sayfa bulunamadı', $tr->content());
        self::assertStringNotContainsString('No route', $tr->content());

        $en = $this->request('GET', '/en/missing');
        self::assertStringContainsString('Page not found', $en->content());
    }

    public function testStateChangingRequestWithoutCsrfIsRejected(): void
    {
        $response = $this->request('POST', '/api/documents', ['Accept' => 'application/json', 'X-Locale' => 'en']);

        self::assertSame(403, $response->status());
        $data = json_decode($response->content(), true);
        self::assertFalse($data['ok']);
        self::assertSame('permission', $data['error']['category']);
        self::assertSame('Your session has expired. Please refresh the page and try again.', $data['error']['message']);
        self::assertArrayNotHasKey('debug', $data['error'], 'Hata ayrıntısı sızmamalı');
    }

    public function testCrossOriginStateChangeIsRejectedEvenWithToken(): void
    {
        $app = $this->createApp();
        $token = $app->container()->get(\App\Core\Csrf::class)->token();

        $response = $this->request('POST', '/api/documents', [
            'Accept' => 'application/json',
            'X-CSRF-Token' => $token,
            'Origin' => 'https://evil.example',
        ], app: $app);

        self::assertSame(403, $response->status());
    }
}
