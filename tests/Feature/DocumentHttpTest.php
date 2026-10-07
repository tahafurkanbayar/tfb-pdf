<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Csrf;
use App\Http\UploadedFile;
use Tests\Support\AppTestCase;
use Tests\Support\TestPdf;
use Tests\Support\TestSchema;

/**
 * HTTP üzerinden: yükle → belge sayfası → indir (tam + aralıklı) → yetki → sil.
 */
final class DocumentHttpTest extends AppTestCase
{
    protected bool $useTestDatabase = true;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: string} [json, ownerCookie, sourcePath]
     */
    private function upload(string $name = 'Teklif.pdf'): array
    {
        $source = TestPdf::create($this->storageRoot() . '/temporary/up-' . bin2hex(random_bytes(3)) . '.pdf', 2);
        // Kaynak dosyanın kopyası yüklenir (UploadedFile kopyalar; orijinali karşılaştırma için kalır)
        $tmp = $source . '.upload';
        copy($source, $tmp);

        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();

        $response = $this->request('POST', '/api/documents', [
            'Accept' => 'application/json',
            'X-CSRF-Token' => $token,
            'X-Locale' => 'tr',
        ], app: $app, files: ['file' => [UploadedFile::fromPath($tmp, $name, 'application/pdf')]]);

        self::assertSame(201, $response->status(), $response->content());
        $owner = self::cookiesFrom($response)['tfb_owner'] ?? '';
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $owner);

        return [json_decode($response->content(), true), $owner, $source];
    }

    public function testUploadViewDownloadAndDeleteFlow(): void
    {
        [$json, $owner, $source] = $this->upload();
        $doc = $json['document'];

        self::assertTrue($json['ok']);
        self::assertSame('Dosya başarıyla yüklendi.', $json['message']);
        self::assertSame('Teklif.pdf', $doc['name']);
        self::assertSame(hash_file('sha256', $source), $doc['versions'][0]['sha256']);
        self::assertArrayNotHasKey('storage_path', $doc['versions'][0], 'Dosya sistemi yolu dışarı verilmemeli');
        self::assertStringNotContainsString('storage', json_encode($json));

        // Belge sayfası
        $page = $this->request('GET', '/tr/documents/' . $doc['id'], cookies: ['tfb_owner' => $owner]);
        self::assertSame(200, $page->status());
        self::assertStringContainsString('Teklif.pdf', $page->content());
        self::assertStringContainsString('kalıcı olarak silecektir', $page->content());

        // İndirme: içerik orijinalle aynı, attachment
        $download = $this->request('GET', '/api/documents/' . $doc['id'] . '/versions/0/download', cookies: ['tfb_owner' => $owner]);
        self::assertSame(200, $download->status());
        self::assertStringStartsWith('attachment;', (string) $download->header('Content-Disposition'));
        self::assertSame(hash_file('sha256', $source), hash('sha256', self::body($download)));

        // Önizleme (inline + Range)
        $range = $this->request('GET', '/api/documents/' . $doc['id'] . '/versions/0/download?inline=1', ['Range' => 'bytes=0-7'], ['tfb_owner' => $owner]);
        self::assertSame(206, $range->status());
        self::assertSame(substr((string) file_get_contents($source), 0, 8), self::body($range));

        // Audit: yalnızca gerçek indirme kaydedilir
        $events = self::db()->select('SELECT event_type FROM audit_events WHERE document_public_id = ? ORDER BY id', [$doc['id']]);
        self::assertSame(['upload', 'download'], array_column($events, 'event_type'));

        // Başka tarayıcı (owner) erişemez
        $stranger = str_repeat('b', 64);
        self::assertSame(404, $this->request('GET', '/tr/documents/' . $doc['id'], cookies: ['tfb_owner' => $stranger])->status());
        self::assertSame(404, $this->request('GET', '/api/documents/' . $doc['id'] . '/versions/0/download', ['Accept' => 'application/json'], ['tfb_owner' => $stranger])->status());
        self::assertSame(404, $this->request('GET', '/api/documents/' . $doc['id'] . '/versions/0/download', ['Accept' => 'application/json'])->status());

        // Silme GET ile yapılamaz: aynı adrese GET yalnızca belgeyi döndürür, silmez
        $get = $this->request('GET', '/api/documents/' . $doc['id'], ['Accept' => 'application/json'], ['tfb_owner' => $owner]);
        self::assertSame(200, $get->status());
        self::assertSame(1, (int) self::db()->scalar('SELECT COUNT(*) FROM documents WHERE public_id = ?', [$doc['id']]));

        // Silme: CSRF olmadan reddedilir
        $noCsrf = $this->request('DELETE', '/api/documents/' . $doc['id'], ['Accept' => 'application/json'], ['tfb_owner' => $owner]);
        self::assertSame(403, $noCsrf->status());

        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();
        $deleted = $this->request('DELETE', '/api/documents/' . $doc['id'], ['Accept' => 'application/json', 'X-CSRF-Token' => $token], ['tfb_owner' => $owner], app: $app);
        self::assertSame(200, $deleted->status(), $deleted->content());

        self::assertSame(404, $this->request('GET', '/tr/documents/' . $doc['id'], cookies: ['tfb_owner' => $owner])->status());
        self::assertSame([], glob($this->storageRoot() . '/documents/*/*') ?: []);
    }

    public function testDocumentListAndDashboardShowOnlyOwnDocuments(): void
    {
        [, $owner] = $this->upload('Benim.pdf');
        [, $other] = $this->upload('Baskasi.pdf');

        $list = $this->request('GET', '/en/documents', cookies: ['tfb_owner' => $owner]);
        self::assertStringContainsString('Benim.pdf', $list->content());
        self::assertStringNotContainsString('Baskasi.pdf', $list->content());

        $home = $this->request('GET', '/tr', cookies: ['tfb_owner' => $other]);
        self::assertStringContainsString('Baskasi.pdf', $home->content());
        self::assertStringNotContainsString('Benim.pdf', $home->content());
        self::assertStringContainsString('Depolama kullanımı', $home->content());
    }

    public function testInvalidUploadReturnsTranslatedValidationError(): void
    {
        $tmp = $this->storageRoot() . '/temporary/fake.pdf';
        file_put_contents($tmp, '<html>not a pdf</html>');

        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();
        $response = $this->request('POST', '/api/documents', [
            'Accept' => 'application/json',
            'X-CSRF-Token' => $token,
            'X-Locale' => 'en',
        ], app: $app, files: ['file' => [UploadedFile::fromPath($tmp, 'fake.pdf')]]);

        self::assertSame(422, $response->status());
        $json = json_decode($response->content(), true);
        self::assertSame('validation', $json['error']['category']);
        self::assertSame('The file is not a valid PDF or it is damaged.', $json['error']['message']);
        self::assertTrue($json['error']['recoverable']);
    }

    public function testExpiryUpdateViaApi(): void
    {
        [$json, $owner] = $this->upload();
        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();

        $response = $this->request(
            'PUT',
            '/api/documents/' . $json['document']['id'] . '/expiry',
            ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-CSRF-Token' => $token, 'X-Locale' => 'en'],
            ['tfb_owner' => $owner],
            app: $app,
            body: '{"policy":"30d"}'
        );

        self::assertSame(200, $response->status(), $response->content());
        $data = json_decode($response->content(), true);
        self::assertSame('Retention period updated.', $data['message']);
        self::assertSame('30d', $data['expiry']['policy']);
    }
}
