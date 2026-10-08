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
        // Ana sayfada araç kartı soluk, "Devre dışı" rozetli; gereken bileşen tooltip'te ve ekran okuyucu metninde
        self::assertMatchesRegularExpression('#class="tool-card card h-100 text-decoration-none tool-card-disabled"\s*href="[^"]*/tr/tools/ocr"\s*data-bs-toggle="tooltip"#', $home->content());
        self::assertStringContainsString('data-bs-title="Bu sunucuda kullanılamıyor. Gereken: Tesseract OCR"', $home->content());
        self::assertStringContainsString('<span class="visually-hidden" data-tool-requirement>Bu sunucuda kullanılamıyor. Gereken: LibreOffice</span>', $home->content());
        self::assertStringContainsString('<span class="badge badge-soft">Devre dışı</span>', $home->content());
        self::assertDoesNotMatchRegularExpression('#tool-card-disabled"\s*href="[^"]*/tr/tools/merge"#', $home->content());
        $office = $this->request('GET', '/tr/tools/office');
        self::assertMatchesRegularExpression($alert, $office->content());
        self::assertStringContainsString('LibreOffice', $office->content());
        self::assertStringNotContainsString('data-dropzone', $office->content(), 'Araç yokken yükleme alanı gösterilmez');

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

    public function testOfficeUploadIsRefusedWith503(): void
    {
        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();
        $docx = $this->storageRoot() . '/temporary/a.docx';
        file_put_contents($docx, \Tests\Support\TinyZip::build(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<x/>']));

        $response = $this->request('POST', '/api/documents', [
            'Accept' => 'application/json',
            'X-CSRF-Token' => $token,
            'X-Locale' => 'en',
        ], app: $app, post: ['office' => '1'], files: ['file' => [\App\Http\UploadedFile::fromPath($docx, 'a.docx')]]);

        self::assertSame(503, $response->status());
        self::assertSame('This feature is not available in the current hosting environment.', json_decode($response->content(), true)['error']['message']);
        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM documents'));
    }
}
