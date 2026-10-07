<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Csrf;
use App\Http\UploadedFile;
use App\Services\AuditService;
use Tests\Support\AppTestCase;
use Tests\Support\TestPdf;
use Tests\Support\TestSchema;

/**
 * Spec §45 uçtan uca happy path, gerçek bir PDF ile ve yalnızca HTTP üzerinden:
 * PDF yükle → PDF işle → Version oluştur → Hash oluştur → Audit event oluştur → Sonucu indir.
 */
final class HappyPathTest extends AppTestCase
{
    protected bool $useTestDatabase = true;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
    }

    public function testUploadProcessVersionHashAuditDownload(): void
    {
        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();
        $headers = ['Accept' => 'application/json', 'X-CSRF-Token' => $token, 'X-Locale' => 'en'];

        // PDF yükle
        $source = TestPdf::create($this->storageRoot() . '/temporary/happy.pdf', 3);
        $sourceHash = hash_file('sha256', $source);
        copy($source, $source . '.upload');
        $upload = $this->request('POST', '/api/documents', $headers, app: $app,
            files: ['file' => [UploadedFile::fromPath($source . '.upload', 'Report.pdf', 'application/pdf')]]);
        self::assertSame(201, $upload->status(), $upload->content());
        $owner = self::cookiesFrom($upload)['tfb_owner'];
        $documentId = json_decode($upload->content(), true)['document']['id'];

        // PDF işle (2. sayfayı döndür)
        $rotate = $this->request('POST', '/api/operations/rotate', $headers + ['Content-Type' => 'application/json'], ['tfb_owner' => $owner],
            app: $app, body: json_encode(['document' => $documentId, 'version' => 0, 'rotations' => ['2' => 90]]));
        self::assertSame(201, $rotate->status(), $rotate->content());
        $data = json_decode($rotate->content(), true);
        self::assertTrue($data['changed']);
        self::assertSame('completed', $data['operation']['status']);

        // Version oluştu
        self::assertCount(1, $data['outputs']);
        $output = $data['outputs'][0];
        self::assertSame(1, $output['version']);
        self::assertSame(3, $output['pages']);

        // Hash oluştu ve veritabanındakiyle aynı
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $output['sha256']);
        self::assertNotSame($sourceHash, $output['sha256']);
        self::assertSame($output['sha256'], self::db()->scalar(
            'SELECT v.sha256 FROM document_versions v JOIN documents d ON d.id = v.document_id WHERE d.public_id = ? AND v.version_number = 1',
            [$documentId]
        ));

        // Sonucu indir: içerik bildirilen hash ile birebir aynı
        $download = $this->request('GET', parse_url($output['download_url'], PHP_URL_PATH), cookies: ['tfb_owner' => $owner]);
        self::assertSame(200, $download->status());
        self::assertStringStartsWith('attachment;', (string) $download->header('Content-Disposition'));
        $body = self::body($download);
        self::assertStringStartsWith('%PDF-', $body);
        self::assertSame($output['sha256'], hash('sha256', $body));

        // Audit event'leri: yükleme, işlem ve indirme; özetler ve zincir tutarlı
        $events = self::db()->select('SELECT * FROM audit_events WHERE document_public_id = ? ORDER BY id', [$documentId]);
        self::assertSame(['upload', 'rotate', 'download'], array_column($events, 'event_type'));
        self::assertSame($sourceHash, $events[0]['output_hash']);
        self::assertSame($sourceHash, $events[1]['input_hash']);
        self::assertSame($output['sha256'], $events[1]['output_hash']);
        self::assertSame($data['operation']['id'], $events[1]['operation_public_id']);
        self::assertSame($output['sha256'], $events[2]['output_hash']);
        self::assertTrue((new AuditService(self::db()))->verify()['ok']);

        // Orijinal sürüm hâlâ indirilebilir ve değişmemiş
        $original = $this->request('GET', '/api/documents/' . $documentId . '/versions/0/download', cookies: ['tfb_owner' => $owner]);
        self::assertSame($sourceHash, hash('sha256', self::body($original)));
    }
}
