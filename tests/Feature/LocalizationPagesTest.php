<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Csrf;
use App\Http\UploadedFile;
use App\I18n\Translator;
use Tests\Support\AppTestCase;
use Tests\Support\TestPdf;
use Tests\Support\TestSchema;

/**
 * Spec §46: tüm sayfalar Türkçe ve İngilizce eksiksiz render edilir.
 * Menü, butonlar, formlar, hatalar, onay pencereleri, araçlar ve dashboard; çözülmemiş
 * çeviri anahtarı yok, İngilizce sayfada Türkçe metin yok, JS'e giden çeviriler sayfa diliyle aynı.
 */
final class LocalizationPagesTest extends AppTestCase
{
    protected bool $useTestDatabase = true;

    private const TOOLS = ['merge', 'split', 'reorder', 'rotate', 'compress', 'watermark', 'redact', 'ocr', 'office', 'sign'];

    private Translator $t;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->t = new Translator(APP_ROOT . '/resources/lang', 'tr');
    }

    /**
     * @return array{0: string, 1: string} [ownerCookie, documentId]
     */
    private function uploadDocument(): array
    {
        $app = $this->createApp();
        $source = TestPdf::create($this->storageRoot() . '/temporary/i18n.pdf', 2);
        $response = $this->request('POST', '/api/documents', [
            'Accept' => 'application/json',
            'X-CSRF-Token' => $app->container()->get(Csrf::class)->token(),
        ], app: $app, files: ['file' => [UploadedFile::fromPath($source, 'Report.pdf', 'application/pdf')]]);
        self::assertSame(201, $response->status(), $response->content());

        return [self::cookiesFrom($response)['tfb_owner'], json_decode($response->content(), true)['document']['id']];
    }

    /**
     * @return list<string>
     */
    private static function pages(string $documentId): array
    {
        $paths = ['', '/about', '/privacy', '/documents', '/documents/' . $documentId];
        foreach (self::TOOLS as $tool) {
            $paths[] = '/tools/' . $tool;
            $paths[] = '/tools/' . $tool . '?document=' . $documentId;
        }

        return $paths;
    }

    /**
     * Görünür metinler ve kullanıcıya okunan öznitelikler (script/style hariç).
     *
     * @return list<string>
     */
    private static function visibleTexts(string $html): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xp = new \DOMXPath($doc);
        foreach (iterator_to_array($xp->query('//script|//style')) as $node) {
            $node->parentNode->removeChild($node);
        }

        $texts = [];
        foreach ($xp->query('//text()|//@placeholder|//@aria-label|//@title|//@alt|//meta[@name="description"]/@content') as $node) {
            $text = trim((string) $node->nodeValue);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsConfig(string $html): array
    {
        self::assertMatchesRegularExpression('#<script type="application/json" id="tfb-config">#', $html);
        preg_match('#<script type="application/json" id="tfb-config">(.*?)</script>#s', $html, $m);

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function testAllPagesRenderCompletelyInBothLanguages(): void
    {
        [$owner, $documentId] = $this->uploadDocument();
        $groups = implode('|', array_map(static fn (string $f): string => basename($f, '.php'), glob(APP_ROOT . '/resources/lang/tr/*.php') ?: []));
        // Çözülmemiş anahtar: "grup.anahtar" biçiminde ve bilinen bir gruba ait metin
        $rawKey = '/^(?:' . $groups . ')\.[a-z0-9_]+(?:\.[a-z0-9_]+)*$/';

        foreach (['tr', 'en'] as $locale) {
            foreach (self::pages($documentId) as $path) {
                $label = $locale . $path;
                $app = $this->createApp();
                $response = $this->request('GET', '/' . $locale . $path, cookies: ['tfb_owner' => $owner], app: $app);
                self::assertSame(200, $response->status(), $label);
                $html = $response->content();

                self::assertStringContainsString('<html lang="' . $locale . '"', $html, $label);
                self::assertSame([], $app->container()->get(Translator::class)->missingKeys(), $label . ': eksik çeviri anahtarı');

                // Menü her sayfada sayfa dilinde
                foreach (['nav.tools', 'nav.documents', 'nav.about', 'nav.privacy'] as $key) {
                    self::assertStringContainsString(e($this->t->get($key, [], $locale)), $html, $label . ': ' . $key);
                }

                foreach (self::visibleTexts($html) as $text) {
                    self::assertDoesNotMatchRegularExpression($rawKey, $text, $label . ': çevrilmemiş anahtar');
                    if ($locale === 'en' && $text !== 'Türkçe') {
                        // Dil seçicideki "Türkçe" dışında İngilizce sayfada Türkçe harf olmamalı
                        self::assertDoesNotMatchRegularExpression('/[ğüşıöçĞÜŞİÖÇ]/u', $text, $label . ': Türkçe metin');
                    }
                }

                // JS'e giden yapılandırma ve çeviriler sayfa dilinde
                $config = self::jsConfig($html);
                self::assertSame($locale, $config['locale'], $label);
                self::assertNotEmpty($config['i18n'], $label);
                foreach ($config['i18n'] as $key => $value) {
                    self::assertSame($this->t->get($key, [], $locale), $value, $label . ': ' . $key);
                    self::assertNotSame($key, $value, $label . ': ' . $key);
                }
            }
        }
    }

    public function testConfirmationErrorAndDashboardTextsInBothLanguages(): void
    {
        [$owner, $documentId] = $this->uploadDocument();

        foreach (['tr', 'en'] as $locale) {
            // Silme onay penceresi
            $show = $this->request('GET', '/' . $locale . '/documents/' . $documentId, cookies: ['tfb_owner' => $owner])->content();
            foreach (['documents.delete_title', 'documents.delete_confirm', 'documents.delete_confirm_button'] as $key) {
                self::assertStringContainsString(e($this->t->get($key, [], $locale)), $show, $locale . ': ' . $key);
            }

            // Dashboard (ana sayfa): belge listesi ve depolama kullanımı
            $home = $this->request('GET', '/' . $locale, cookies: ['tfb_owner' => $owner])->content();
            self::assertStringContainsString('Report.pdf', $home);
            self::assertStringContainsString(e($this->t->get('dashboard.storage', [], $locale)), $home, $locale);

            // Hata sayfası
            $missing = $this->request('GET', '/' . $locale . '/olmayan-sayfa');
            self::assertSame(404, $missing->status());
            self::assertStringContainsString(e($this->t->get('errors.not_found_title', [], $locale)), $missing->content(), $locale);

            // API hata mesajı
            $api = $this->request('GET', '/api/documents/' . str_repeat('0', 32), ['Accept' => 'application/json', 'X-Locale' => $locale], ['tfb_owner' => $owner]);
            self::assertSame(404, $api->status());
            self::assertSame($this->t->get('errors.not_found', [], $locale), json_decode($api->content(), true)['error']['message'], $locale);
        }

        // İki dil gerçekten farklı metin üretir
        self::assertNotSame($this->t->get('documents.delete_confirm', [], 'tr'), $this->t->get('documents.delete_confirm', [], 'en'));
    }
}
