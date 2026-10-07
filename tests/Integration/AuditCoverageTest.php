<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\UploadedFile;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

/**
 * Audit log kapsamı ve içeriği (spec §19, §41).
 */
final class AuditCoverageTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('audit');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'audit-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testAllOperationsAreRecordedWithHashesAndChainStaysIntact(): void
    {
        $a = $this->s->uploadPdf($this->owner, 4, 'Müşteri Sözleşmesi Ahmet Yılmaz.pdf');
        $b = $this->s->uploadPdf($this->owner, 1, 'Ek.pdf');

        $merge = $this->s->tools->merge($this->owner, [['document' => $a->publicId], ['document' => $b->publicId]]);
        $this->s->tools->split($this->owner, $a->publicId, null, 'ranges', '1-2');
        $this->s->tools->reorder($this->owner, $a->publicId, 0, [2, 1, 3, 4]);
        $this->s->tools->rotate($this->owner, $a->publicId, 0, [1 => 90]);
        $this->s->tools->compress($this->owner, $a->publicId, 0, 'medium');                   // no_change
        $this->s->tools->watermark($this->owner, $a->publicId, 0, ['text' => 'GİZLİ-PROJE-X']);
        $this->s->documents->pathForDownload($a, $this->s->documents->version($a, 0), audit: true);
        $this->s->documents->setExpiry($a, '30d');
        try {
            $this->s->tools->split($this->owner, $a->publicId, null, 'ranges', '1-99');       // doğrulama hatası: işlem kaydı yok
        } catch (\App\Exceptions\ValidationException) {
        }
        $this->s->documents->delete($b);

        $events = self::db()->select('SELECT * FROM audit_events ORDER BY id');
        $types = array_column($events, 'event_type');
        foreach (['upload', 'merge', 'split', 'reorder', 'rotate', 'compress', 'watermark', 'download', 'expiry_changed', 'delete'] as $type) {
            self::assertContains($type, $types, $type);
        }

        $byType = [];
        foreach ($events as $e) {
            $byType[$e['event_type']][] = $e;
        }
        // Her olay zorunlu alanlara sahip
        foreach ($events as $e) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $e['event_id']);
            self::assertNotEmpty($e['created_at']);
            self::assertContains($e['status'], ['success', 'failed', 'no_change']);
        }
        self::assertSame('no_change', $byType['compress'][0]['status']);
        self::assertSame($merge->versions[0]->sha256, $byType['merge'][0]['output_hash']);
        self::assertSame($merge->operationId, $byType['merge'][0]['operation_public_id']);
        self::assertNotNull($byType['rotate'][0]['input_hash']);

        // Silinen belgenin olayları kalır (append-only)
        self::assertGreaterThanOrEqual(2, (int) self::db()->scalar('SELECT COUNT(*) FROM audit_events WHERE document_public_id = ?', [$b->publicId]));

        // Hassas veri yok: dosya adları, filigran metni, IP / tarayıcı bilgisi (imza dışı olaylarda)
        $dump = json_encode($events, JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('Ahmet', $dump);
        self::assertStringNotContainsString('Sözleşmesi', $dump);
        self::assertStringNotContainsString('GİZLİ-PROJE-X', $dump);
        foreach ($events as $e) {
            self::assertNull($e['ip_address']);
            self::assertNull($e['user_agent']);
        }

        self::assertSame(['ok' => true, 'checked' => count($events), 'broken_at' => null, 'reason' => null], $this->s->audit->verify());
    }

    public function testUpdatingOrDeletingIsDetected(): void
    {
        $this->s->uploadPdf($this->owner, 1);
        $this->s->uploadPdf($this->owner, 1);

        // Son kaydın silinmesi: zincir başı artık son kayıtla eşleşmez
        self::db()->execute('DELETE FROM audit_events ORDER BY id DESC LIMIT 1');
        $result = $this->s->audit->verify();
        self::assertFalse($result['ok']);
        self::assertSame('head', $result['reason']);
    }
}
