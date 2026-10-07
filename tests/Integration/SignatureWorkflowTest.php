<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Logger;
use App\Core\Url;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\I18n\Translator;
use App\Pdf\PdfInspector;
use App\Repositories\DocumentRepository;
use App\Repositories\SignatureRepository;
use App\Security\Hmac;
use App\Services\MailService;
use App\Services\SignatureService;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

/**
 * Basit imza akışı (spec §26).
 */
final class SignatureWorkflowTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    private SignatureService $signatures;

    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD yok');
        }
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('signature');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'sig-owner');
        $logger = new Logger($this->root . '/logs', 'error');
        $this->signatures = new SignatureService(
            self::db(),
            new SignatureRepository(self::db()),
            new DocumentRepository(self::db()),
            $this->s->documents,
            $this->s->tools,
            $this->s->operations,
            $this->s->storage,
            $this->s->audit,
            new MailService(['host' => '', 'port' => 587, 'username' => '', 'password' => '', 'encryption' => 'tls', 'from_address' => '', 'from_name' => '', 'timeout' => 5], $logger),
            new Hmac(str_repeat('k', 64)),
            new Url('http://localhost/tfb-pdf', 'tr'),
            new Translator(APP_ROOT . '/resources/lang', 'tr'),
            $logger,
            14
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            TempDirectory::remove($this->root);
        }
    }

    private static function drawing(): string
    {
        $img = imagecreatetruecolor(400, 150);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, (int) imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagesetthickness($img, 4);
        imageline($img, 20, 120, 380, 30, (int) imagecolorallocate($img, 10, 20, 120));
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    }

    /**
     * @return array{0: \App\Domain\Document, 1: array{request: string, links: list<array{name: string, email: ?string, url: string, emailed: bool}>}}
     */
    private function createRequest(): array
    {
        $doc = $this->s->uploadPdf($this->owner, 2, 'Sözleşme.pdf');
        $result = $this->signatures->create($this->owner, $doc->publicId, null, [
            ['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com', 'fields' => [['page' => 2, 'x' => 0.1, 'y' => 0.7, 'w' => 0.35, 'h' => 0.08]]],
            ['name' => 'Mehmet Öz', 'email' => '', 'fields' => [['page' => 2, 'x' => 0.55, 'y' => 0.7, 'w' => 0.35, 'h' => 0.08]]],
        ], 'Lütfen imzalayın.', 'tr');

        return [$doc, $result];
    }

    private static function token(string $url): string
    {
        return substr($url, -64);
    }

    public function testFullFlowProducesSignedFinalPdfWithCertificateAndAudit(): void
    {
        [$doc, $created] = $this->createRequest();

        self::assertCount(2, $created['links']);
        self::assertStringStartsWith('http://localhost/tfb-pdf/tr/sign/', $created['links'][0]['url']);
        self::assertFalse($created['links'][0]['emailed'], 'SMTP yapılandırılmadı: e-posta gönderilmez');
        // Token veritabanında düz metin saklanmaz
        $token = self::token($created['links'][0]['url']);
        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM signature_signers WHERE token_hash = ?', [$token]));

        $context = $this->signatures->signerContext($token);
        $this->signatures->markViewed($context, '203.0.113.5', 'TestBrowser/1.0');

        $first = $this->signatures->sign($token, true, 'drawn', self::drawing(), '203.0.113.5', 'TestBrowser/1.0', 'tr');
        self::assertFalse($first['completed'], 'İkinci imzalayan bekleniyor');

        $second = $this->signatures->sign(self::token($created['links'][1]['url']), true, 'typed', 'Mehmet Öz', '198.51.100.7', 'Other/2.0', 'en');
        self::assertTrue($second['completed']);

        $request = self::db()->first('SELECT * FROM signature_requests WHERE public_id = ?', [$created['request']]);
        self::assertSame('completed', $request['status']);
        self::assertNotNull($request['final_version_id']);

        $final = null;
        foreach ($this->s->documents->versions($doc) as $version) {
            if ($version->id === (int) $request['final_version_id']) {
                $final = $version;
            }
        }
        self::assertNotNull($final);
        self::assertSame($final->sha256, $request['final_sha256']);
        $path = $this->s->storage->resolve($final->storagePath);
        self::assertSame($final->sha256, hash_file('sha256', $path));
        // 2 belge sayfası + sertifika sayfası
        self::assertSame(3, (new PdfInspector())->inspect($path)->pageCount);
        self::assertGreaterThan(filesize($this->s->versionPath($doc, 0)), filesize($path), 'İmza görseli ve sertifika eklenmiş olmalı');

        $events = array_column(self::db()->select('SELECT event_type FROM signature_events WHERE request_id = ? ORDER BY id', [$request['id']]), 'event_type');
        self::assertSame(['created', 'invited', 'invited', 'viewed', 'consented', 'signed', 'consented', 'signed', 'completed'], $events);

        $consent = self::db()->first("SELECT * FROM signature_events WHERE event_type = 'consented' ORDER BY id LIMIT 1");
        self::assertSame('203.0.113.5', $consent['ip_address']);
        $meta = json_decode((string) $consent['metadata'], true);
        self::assertSame(SignatureService::CONSENT_VERSION, $meta['consent_version']);
        self::assertSame(64, strlen($meta['consent_text_sha256']));

        $auditTypes = array_column(self::db()->select('SELECT event_type FROM audit_events ORDER BY id'), 'event_type');
        foreach (['signature_created', 'signature_viewed', 'signature_consented', 'signature_signed', 'signature_completed'] as $type) {
            self::assertContains($type, $auditTypes);
        }
        self::assertSame('203.0.113.5', self::db()->scalar("SELECT ip_address FROM audit_events WHERE event_type = 'signature_viewed'"));
        self::assertTrue($this->s->audit->verify()['ok']);

        // İmzalayan final PDF'e erişebilir; aynı bağlantıyla tekrar imzalanamaz
        [, $forSigner] = $this->signatures->finalVersionForSigner($token);
        self::assertSame($final->id, $forSigner->id);
        $this->expectException(ValidationException::class);
        $this->signatures->sign($token, true, 'typed', 'Tekrar', '1.1.1.1', 'x', 'tr');
    }

    public function testConsentIsRequiredAndInvalidSignaturesRejected(): void
    {
        [, $created] = $this->createRequest();
        $token = self::token($created['links'][0]['url']);

        foreach ([
            [false, 'typed', 'Ayşe', 'signature.consent_required'],
            [true, 'typed', '', 'signature.typed_invalid'],
            [true, 'drawn', 'data:image/svg+xml;base64,PHN2Zz4=', 'signature.image_invalid'],
            [true, 'stamp', 'x', 'signature.type_invalid'],
        ] as [$consent, $type, $payload, $key]) {
            try {
                $this->signatures->sign($token, $consent, $type, $payload, '1.1.1.1', 'x', 'tr');
                self::fail('Expected ' . $key);
            } catch (ValidationException $e) {
                self::assertSame($key, $e->messageKey());
            }
        }
        self::assertSame(0, (int) self::db()->scalar("SELECT COUNT(*) FROM signature_events WHERE event_type = 'signed'"));
    }

    public function testDeclineCancelExpiryAndLinkRegeneration(): void
    {
        [, $created] = $this->createRequest();
        $first = self::token($created['links'][0]['url']);

        // Yeni bağlantı eskisini geçersiz kılar
        $signerId = (int) self::db()->scalar('SELECT id FROM signature_signers ORDER BY id LIMIT 1');
        $newUrl = $this->signatures->regenerateLink($this->owner, $created['request'], $signerId, 'en');
        self::assertStringContainsString('/en/sign/', $newUrl);
        try {
            $this->signatures->signerContext($first);
            self::fail('Old link must be invalid');
        } catch (NotFoundException) {
            self::addToAssertionCount(1);
        }

        // Ret: talep "declined" olur, diğer imzalayan artık imzalayamaz
        $this->signatures->decline(self::token($newUrl), 'Şartları kabul etmiyorum', '1.2.3.4', 'x');
        self::assertSame('declined', self::db()->scalar('SELECT status FROM signature_requests'));
        try {
            $this->signatures->sign(self::token($created['links'][1]['url']), true, 'typed', 'Mehmet', '1.1.1.1', 'x', 'tr');
            self::fail('Expected not pending');
        } catch (ValidationException $e) {
            self::assertSame('signature.not_pending', $e->messageKey());
        }

        // İptal ve süre dolumu
        [, $second] = $this->createRequest();
        $this->signatures->cancel($this->owner, $second['request']);
        self::assertSame('cancelled', self::db()->scalar('SELECT status FROM signature_requests WHERE public_id = ?', [$second['request']]));

        [, $third] = $this->createRequest();
        self::db()->execute('UPDATE signature_requests SET expires_at = ? WHERE public_id = ?', [gmdate('Y-m-d H:i:s', time() - 10), $third['request']]);
        self::assertSame(1, $this->signatures->expireDue());
        self::assertSame('expired', self::db()->scalar('SELECT status FROM signature_requests WHERE public_id = ?', [$third['request']]));

        // Başka sahip talebi iptal edemez
        $this->expectException(NotFoundException::class);
        $this->signatures->cancel(hash('sha256', 'stranger'), $third['request']);
    }

    public function testCreationValidation(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 1);
        $valid = ['page' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.3, 'h' => 0.1];

        $cases = [
            [[], 'signature.signer_count'],
            [[['name' => '', 'fields' => [$valid]]], 'signature.name_invalid'],
            [[['name' => 'A', 'email' => 'not-mail', 'fields' => [$valid]]], 'signature.email_invalid'],
            [[['name' => 'A', 'fields' => []]], 'signature.fields_required'],
            [[['name' => 'A', 'fields' => [['page' => 2] + $valid]]], 'signature.field_invalid'],
            [[['name' => 'A', 'fields' => [['x' => 0.9, 'w' => 0.3] + $valid]]], 'signature.field_invalid'],
        ];
        foreach ($cases as [$signers, $key]) {
            try {
                $this->signatures->create($this->owner, $doc->publicId, null, $signers, '', 'tr');
                self::fail('Expected ' . $key);
            } catch (ValidationException $e) {
                self::assertSame($key, $e->messageKey());
            }
        }
        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM signature_requests'));
    }
}
