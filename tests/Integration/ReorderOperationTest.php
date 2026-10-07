<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Pdf\PdfInspector;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

final class ReorderOperationTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('reorder');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'reorder-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testReorderAndRemovePages(): void
    {
        // Sayfa boyutları farklı: sıranın gerçekten değiştiği boyutlardan doğrulanır
        $doc = $this->s->uploadPdf($this->owner, 3, 'a.pdf', [[200, 300], [400, 500], [600, 700]]);

        $result = $this->s->tools->reorder($this->owner, $doc->publicId, 0, [3, 1]);

        self::assertSame('completed', $result->status);
        self::assertSame(2, $result->versions[0]->pageCount);
        $info = (new PdfInspector())->inspect($this->s->storage->resolve($result->versions[0]->storagePath));
        self::assertEqualsWithDelta(600.0, $info->pages[0]['width'], 0.01);
        self::assertEqualsWithDelta(200.0, $info->pages[1]['width'], 0.01);

        $op = self::db()->first("SELECT params FROM operations WHERE type = 'reorder'");
        self::assertSame(['order' => [3, 1], 'removed' => [2]], json_decode((string) $op['params'], true));
    }

    public function testUnchangedOrderCreatesNoVersion(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 3);

        $result = $this->s->tools->reorder($this->owner, $doc->publicId, null, [1, 2, 3]);

        self::assertSame('no_change', $result->status);
        self::assertSame([], $result->versions);
        self::assertCount(1, $this->s->documents->versions($doc), 'Yeni sürüm oluşturulmamalı');
        self::assertSame('no_change', self::db()->scalar("SELECT status FROM audit_events WHERE event_type = 'reorder'"));
    }

    public function testInvalidOrdersAreRejected(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 3);

        foreach ([[[], 'reorder.all_removed'], [[1, 1, 2], 'reorder.invalid_order'], [[0, 1], 'reorder.invalid_order'], [[4], 'reorder.invalid_order']] as [$order, $key]) {
            try {
                $this->s->tools->reorder($this->owner, $doc->publicId, null, $order);
                self::fail('Expected ' . $key);
            } catch (ValidationException $e) {
                self::assertSame($key, $e->messageKey());
            }
        }
    }
}
