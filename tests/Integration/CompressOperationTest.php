<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Pdf\Fpdi;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

final class CompressOperationTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD yok');
        }
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('compressop');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'compress-owner');
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            TempDirectory::remove($this->root);
        }
    }

    public function testCompressionCreatesSmallerVersionWithSizesRecorded(): void
    {
        $img = imagecreatetruecolor(2000, 1500);
        for ($i = 0; $i < 3000; $i++) {
            imageline($img, mt_rand(0, 2000), mt_rand(0, 1500), mt_rand(0, 2000), mt_rand(0, 1500), (int) imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        imagejpeg($img, $this->root . '/scan.jpg', 95);
        $pdf = new Fpdi();
        $pdf->AddPage();
        $pdf->Image($this->root . '/scan.jpg', 0, 0, 595, 446);
        $pdf->Output('F', $this->root . '/scan.pdf');

        $doc = $this->s->documents->upload(UploadedFile::fromPath($this->root . '/scan.pdf', 'tarama.pdf'), $this->owner);
        $result = $this->s->tools->compress($this->owner, $doc->publicId, null, 'medium');

        self::assertSame('completed', $result->status);
        self::assertLessThan($result->meta['size_before'], $result->meta['size_after']);
        self::assertSame($result->versions[0]->fileSize, $result->meta['size_after']);
        self::assertGreaterThanOrEqual(3, $result->meta['saved_percent']);
        self::assertContains('warnings.images_recompressed', $result->warnings);
        self::assertSame('php', self::db()->scalar("SELECT engine FROM operations WHERE type = 'compress'"));
    }

    public function testNoMeaningfulSavingCreatesNoVersion(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 2);

        $result = $this->s->tools->compress($this->owner, $doc->publicId, null, 'high');

        self::assertSame('no_change', $result->status);
        self::assertSame([], $result->versions);
        self::assertCount(1, $this->s->documents->versions($doc));
        self::assertArrayHasKey('size_after', $result->meta);
    }

    public function testInvalidLevelIsRejected(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 1);

        $this->expectException(ValidationException::class);
        $this->s->tools->compress($this->owner, $doc->publicId, null, 'ultra');
    }
}
