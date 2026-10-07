<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Csrf;
use App\Http\UploadedFile;
use Tests\Support\AppTestCase;
use Tests\Support\TestPdf;
use Tests\Support\TestSchema;

final class VersioningHttpTest extends AppTestCase
{
    protected bool $useTestDatabase = true;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
    }

    public function testDocumentPageShowsProvenanceAndToolsCanTargetAVersion(): void
    {
        $app = $this->createApp();
        $token = $app->container()->get(Csrf::class)->token();
        $headers = ['Accept' => 'application/json', 'X-CSRF-Token' => $token, 'X-Locale' => 'tr'];

        $path = TestPdf::create($this->storageRoot() . '/temporary/v.pdf', 2);
        $upload = $this->request('POST', '/api/documents', $headers, app: $app, files: ['file' => [UploadedFile::fromPath($path, 'Rapor.pdf')]]);
        $owner = self::cookiesFrom($upload)['tfb_owner'];
        $id = json_decode($upload->content(), true)['document']['id'];

        $rotate = $this->request('POST', '/api/operations/rotate', $headers + ['Content-Type' => 'application/json'], ['tfb_owner' => $owner],
            app: $app, body: json_encode(['document' => $id, 'version' => 0, 'rotations' => ['1' => 90]]));
        self::assertSame(201, $rotate->status(), $rotate->content());

        $page = $this->request('GET', '/tr/documents/' . $id, cookies: ['tfb_owner' => $owner]);
        self::assertStringContainsString('Kaynak: Orijinal', $page->content());
        self::assertStringContainsString('v001.pdf', $page->content());
        self::assertStringContainsString('version=1', html_entity_decode($page->content()));

        $tool = $this->request('GET', '/tr/tools/split?document=' . $id . '&version=0', cookies: ['tfb_owner' => $owner]);
        self::assertStringContainsString('Rapor.pdf — Orijinal', html_entity_decode($tool->content(), ENT_QUOTES | ENT_HTML5));

        $latest = $this->request('GET', '/en/tools/split?document=' . $id, cookies: ['tfb_owner' => $owner]);
        self::assertStringContainsString('Rapor.pdf — Version 1', html_entity_decode($latest->content(), ENT_QUOTES | ENT_HTML5));
    }
}
