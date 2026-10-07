<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Csrf;
use App\Http\UploadedFile;
use Tests\Support\AppTestCase;
use Tests\Support\TestPdf;
use Tests\Support\TestSchema;

/**
 * Uçtan uca: iki PDF yükle → birleştir → sürüm + hash → audit → sonucu indir.
 */
final class MergeHttpTest extends AppTestCase
{
    protected bool $useTestDatabase = true;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
    }

    public function testToolPagesRenderInBothLanguages(): void
    {
        $tr = $this->request('GET', '/tr/tools/merge');
        self::assertSame(200, $tr->status());
        self::assertStringContainsString('PDF Birleştir', $tr->content());
        self::assertStringContainsString("PDF'leri birleştir", html_entity_decode($tr->content(), ENT_QUOTES | ENT_HTML5));

        $en = $this->request('GET', '/en/tools/merge');
        self::assertStringContainsString('Merge PDFs', $en->content());

        self::assertSame(404, $this->request('GET', '/tr/tools/olmayan')->status());
    }

    public function testUploadMergeAndDownload(): void
    {
        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();
        $headers = ['Accept' => 'application/json', 'X-CSRF-Token' => $token, 'X-Locale' => 'tr'];

        $owner = null;
        $ids = [];
        foreach ([2, 3] as $pages) {
            $path = TestPdf::create($this->storageRoot() . '/temporary/in-' . $pages . '.pdf', $pages);
            $response = $this->request('POST', '/api/documents', $headers, $owner === null ? [] : ['tfb_owner' => $owner], app: $app,
                files: ['file' => [UploadedFile::fromPath($path, 'dosya-' . $pages . '.pdf')]]);
            self::assertSame(201, $response->status(), $response->content());
            $owner ??= self::cookiesFrom($response)['tfb_owner'];
            $ids[] = json_decode($response->content(), true)['document']['id'];
        }

        $merge = $this->request('POST', '/api/operations/merge', $headers + ['Content-Type' => 'application/json'], ['tfb_owner' => $owner],
            app: $app, body: json_encode(['items' => [['document' => $ids[1]], ['document' => $ids[0], 'version' => 0]]]));

        self::assertSame(201, $merge->status(), $merge->content());
        $data = json_decode($merge->content(), true);
        self::assertTrue($data['changed']);
        self::assertSame("PDF'ler başarıyla birleştirildi.", $data['message']);
        self::assertSame(5, $data['outputs'][0]['pages']);
        self::assertNotEmpty($data['warnings']);

        $download = $this->request('GET', parse_url($data['outputs'][0]['download_url'], PHP_URL_PATH), cookies: ['tfb_owner' => $owner]);
        self::assertSame(200, $download->status());
        self::assertSame($data['outputs'][0]['sha256'], hash('sha256', self::body($download)));

        // Başka bir tarayıcı bu belgelerle birleştirme yapamaz
        $stranger = $this->request('POST', '/api/operations/merge', $headers + ['Content-Type' => 'application/json'], ['tfb_owner' => str_repeat('c', 64)],
            app: $app, body: json_encode(['items' => [['document' => $ids[0]], ['document' => $ids[1]]]]));
        self::assertSame(404, $stranger->status());
    }
}
