<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Csrf;
use App\Tools\ProcessRunner;
use App\Tools\ToolDetector;
use Tests\Support\AppTestCase;
use Tests\Support\TestSchema;

/**
 * Opsiyonel araçlar (Tesseract, LibreOffice, Ghostscript) yokken uygulama hata vermeden çalışır,
 * ilgili özellikler kapalıdır ve kullanıcıya açık mesaj gösterilir (spec §10).
 */
final class OptionalToolsHttpTest extends AppTestCase
{
    protected bool $useTestDatabase = true;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
    }

    protected function createApp(): Application
    {
        $app = parent::createApp();
        $root = $this->storageRoot();
        // Bu makinede araçlar kurulu olsa bile test, "araç yok" ortamını taklit eder
        $app->container()->set(ToolDetector::class, fn () => new ToolDetector(new ProcessRunner(), [
            ToolDetector::GHOSTSCRIPT => 'disabled',
            ToolDetector::LIBREOFFICE => 'disabled',
            ToolDetector::TESSERACT => 'disabled',
            ToolDetector::PDFTOPPM => 'disabled',
        ], $root . '/cache/tools.json'));

        return $app;
    }

    public function testToolPagesShowUnavailableMessageAndHomeStillWorks(): void
    {
        $ocr = $this->request('GET', '/tr/tools/ocr');
        self::assertSame(200, $ocr->status());
        // Görünür uyarı kutusu (JS çeviri verisindeki metinle karışmasın diye işaretlemeyle aranır)
        $alert = '#role="alert">\s*<svg[^>]*>.*?</svg>\s*<span>Bu özellik mevcut hosting ortamında kullanılamıyor\.</span>#s';
        self::assertMatchesRegularExpression($alert, $ocr->content());
        self::assertStringContainsString('Tesseract OCR', $ocr->content());

        $en = $this->request('GET', '/en/tools/ocr');
        self::assertMatchesRegularExpression('#role="alert">\s*<svg[^>]*>.*?</svg>\s*<span>This feature is not available in the current hosting environment\.</span>#s', $en->content());

        $home = $this->request('GET', '/tr');
        self::assertSame(200, $home->status());
        self::assertStringContainsString('<span class="badge text-bg-light border mt-2">Bu sunucuda kullanılamıyor</span>', $home->content());
        // Diğer araçlar çalışmaya devam eder
        self::assertSame(200, $this->request('GET', '/tr/tools/merge')->status());
        self::assertDoesNotMatchRegularExpression($alert, $this->request('GET', '/tr/tools/split')->content());
    }

    public function testOcrApiReturns503WithFriendlyMessage(): void
    {
        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();

        $response = $this->request('POST', '/api/operations/ocr', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => $token,
            'X-Locale' => 'tr',
        ], ['tfb_owner' => str_repeat('a', 64)], app: $app, body: '{"document":"' . str_repeat('a', 32) . '"}');

        self::assertSame(503, $response->status());
        $data = json_decode($response->content(), true);
        self::assertSame('tool_unavailable', $data['error']['category']);
        self::assertSame('Bu özellik mevcut hosting ortamında kullanılamıyor.', $data['error']['message']);
    }
}
