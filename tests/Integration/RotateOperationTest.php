<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Pdf\Fpdi;
use App\Pdf\PdfInspector;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

final class RotateOperationTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('rotate');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'rotate-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testRotatesSelectedPagesOnly(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 3, 'a.pdf', [[200, 300], [200, 300], [200, 300]]);

        $result = $this->s->tools->rotate($this->owner, $doc->publicId, 0, ['1' => 90, '3' => -90, '2' => 0]);

        self::assertSame('completed', $result->status);
        $info = (new PdfInspector())->inspect($this->s->storage->resolve($result->versions[0]->storagePath));
        self::assertSame(3, $info->pageCount);
        self::assertSame([90, 0, 270], array_column($info->pages, 'rotation'));

        $params = json_decode((string) self::db()->scalar("SELECT params FROM operations WHERE type = 'rotate'"), true);
        self::assertSame(['rotations' => ['1' => 90, '3' => 270]], $params);
    }

    public function testRotationIsAppliedOnTopOfExistingPageRotation(): void
    {
        // Kaynak sayfa: 200x300 kutu, /Rotate 90 → görünüşte yatay (300x200)
        $path = $this->root . '/pre-rotated.pdf';
        $pdf = new Fpdi();
        $pdf->AddPage('P', [200, 300], 90);
        $pdf->useUnicodeFont(12);
        $pdf->Text(20, 40, 'Döndürülmüş');
        $pdf->Output('F', $path);
        $doc = $this->s->documents->upload(UploadedFile::fromPath($path, 'pre.pdf'), $this->owner);

        $result = $this->s->tools->rotate($this->owner, $doc->publicId, 0, [1 => 90]);
        $page = (new PdfInspector())->inspect($this->s->storage->resolve($result->versions[0]->storagePath))->pages[0];

        // FPDI kaynağın döndürmesini şablona uygular (300x200 yatay şablon); ek 90° yeni /Rotate olur.
        // Görünen sonuç: kaynak görünümünün 90° daha döndürülmüş hali = toplam 180°.
        self::assertEqualsWithDelta(300.0, $page['width'], 0.01);
        self::assertEqualsWithDelta(200.0, $page['height'], 0.01);
        self::assertSame(90, $page['rotation']);
    }

    public function testNoRotationMeansNoNewVersionAndInvalidInputIsRejected(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 2);

        $result = $this->s->tools->rotate($this->owner, $doc->publicId, null, ['1' => 360, '2' => 0]);
        self::assertSame('no_change', $result->status);
        self::assertCount(1, $this->s->documents->versions($doc));

        foreach ([['5' => 90], ['1' => 45], ['1' => 'abc']] as $bad) {
            try {
                $this->s->tools->rotate($this->owner, $doc->publicId, null, $bad);
                self::fail('Expected validation error');
            } catch (ValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
