<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Csrf;
use App\Http\UploadedFile;
use Tests\Support\AppTestCase;
use Tests\Support\TestPdf;
use Tests\Support\TestSchema;

/**
 * Uçtan uca imza: sahip talep oluşturur → imzalayan bağlantıyı açar, onaylar, imzalar → final PDF indirilir.
 */
final class SignatureHttpTest extends AppTestCase
{
    protected bool $useTestDatabase = true;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
    }

    public function testOwnerCreatesRequestAndSignerSignsOverHttp(): void
    {
        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();
        $json = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-CSRF-Token' => $token, 'X-Locale' => 'tr'];

        $path = TestPdf::create($this->storageRoot() . '/temporary/s.pdf', 1);
        $upload = $this->request('POST', '/api/documents', ['Accept' => 'application/json', 'X-CSRF-Token' => $token], app: $app,
            files: ['file' => [UploadedFile::fromPath($path, 'Sözleşme.pdf')]]);
        $owner = self::cookiesFrom($upload)['tfb_owner'];
        $docId = json_decode($upload->content(), true)['document']['id'];

        $create = $this->request('POST', '/api/signatures', $json, ['tfb_owner' => $owner], app: $app, body: json_encode([
            'document' => $docId,
            'signers' => [['name' => 'Zeynep Kaya', 'email' => '', 'fields' => [['page' => 1, 'x' => 0.1, 'y' => 0.8, 'w' => 0.4, 'h' => 0.08]]]],
        ]));
        self::assertSame(201, $create->status(), $create->content());
        $created = json_decode($create->content(), true);
        $link = $created['links'][0]['url'];
        self::assertSame('İmza talebi oluşturuldu.', $created['message']);
        $signerPath = (string) parse_url($link, PHP_URL_PATH);
        $signerToken = substr($signerPath, -64);

        // İmzalayan sayfası (owner cookie yok)
        $page = $this->request('GET', $signerPath, app: $app);
        self::assertSame(200, $page->status());
        self::assertStringContainsString('nitelikli elektronik imza', $page->content());
        self::assertSame('no-referrer', $page->header('Referrer-Policy'));
        self::assertSame('viewed', self::db()->scalar('SELECT status FROM signature_signers'));

        // Önizleme için belge: token ile erişilir
        $doc = $this->request('GET', '/api/sign/' . $signerToken . '/document', app: $app);
        self::assertSame(200, $doc->status());

        // Onaysız imza reddedilir
        $noConsent = $this->request('POST', '/api/sign/' . $signerToken, $json, app: $app, body: json_encode(['consent' => false, 'type' => 'typed', 'signature' => 'Zeynep Kaya']));
        self::assertSame(422, $noConsent->status());

        $sign = $this->request('POST', '/api/sign/' . $signerToken, $json, app: $app, body: json_encode(['consent' => true, 'type' => 'typed', 'signature' => 'Zeynep Kaya']));
        self::assertSame(200, $sign->status(), $sign->content());
        $signed = json_decode($sign->content(), true);
        self::assertTrue($signed['completed']);

        $final = $this->request('GET', '/api/sign/' . $signerToken . '/final', app: $app);
        self::assertSame(200, $final->status());
        $finalHash = (string) self::db()->scalar('SELECT final_sha256 FROM signature_requests');
        self::assertSame($finalHash, hash('sha256', self::body($final)));

        // Belge sayfası sahibe tamamlanmış talebi gösterir
        $docPage = $this->request('GET', '/tr/documents/' . $docId, cookies: ['tfb_owner' => $owner], app: $app);
        self::assertStringContainsString('Tamamlandı', $docPage->content());
        self::assertStringContainsString($finalHash, $docPage->content());

        // Geçersiz / tahmin edilen token
        self::assertSame(404, $this->request('GET', '/tr/sign/' . str_repeat('0', 64), app: $app)->status());
        self::assertSame(404, $this->request('GET', '/api/sign/' . str_repeat('0', 64) . '/document', ['Accept' => 'application/json'], app: $app)->status());
    }

    public function testSignToolPageRendersInBothLanguages(): void
    {
        self::assertStringContainsString('İmza İste', $this->request('GET', '/tr/tools/sign')->content());
        self::assertStringContainsString('qualified electronic signature', $this->request('GET', '/en/tools/sign')->content());
    }
}
