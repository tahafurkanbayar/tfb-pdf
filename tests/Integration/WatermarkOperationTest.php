<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Pdf\PdfInspector;
use App\Pdf\WatermarkOptions;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

final class WatermarkOperationTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('watermark');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'wm-owner');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->root);
    }

    public function testWatermarkAddsTransparentRotatedTextOnSelectedPages(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 3);

        $result = $this->s->tools->watermark($this->owner, $doc->publicId, null, [
            'text' => 'GİZLİ ŞİRKET İÇİ',
            'position' => 'center',
            'rotation' => 30,
            'opacity' => 0.25,
            'font_size' => 40,
            'color' => '#cc0000',
            'layer' => 'over',
        ], '1, 3');

        self::assertSame('completed', $result->status);
        $path = $this->s->storage->resolve($result->versions[0]->storagePath);
        self::assertSame(3, (new PdfInspector())->inspect($path)->pageCount);

        $raw = (string) file_get_contents($path);
        // Saydamlık grafik durumu ve PDF 1.4 başlığı
        self::assertStringContainsString('/Type /ExtGState', $raw);
        self::assertStringContainsString('/ca 0.250', $raw);
        self::assertStringStartsWith('%PDF-1.4', $raw);
        // Türkçe karakterler için gömülü Unicode font
        self::assertStringContainsString('DejaVu', $raw);

        $params = json_decode((string) self::db()->scalar("SELECT params FROM operations WHERE type = 'watermark'"), true);
        self::assertSame('1, 3', $params['pages']);
        self::assertSame('#cc0000', $params['color']);
        self::assertArrayNotHasKey('text', $params, 'Filigran metni kaydedilmez, yalnızca uzunluğu');
    }

    public function testInvalidSettingsAreRejected(): void
    {
        $cases = [
            [['text' => ''], 'watermark.text_invalid'],
            [['text' => str_repeat('x', 101)], 'watermark.text_invalid'],
            [['text' => 'A', 'position' => 'middle'], 'watermark.position_invalid'],
            [['text' => 'A', 'opacity' => 0.01], 'watermark.settings_invalid'],
            [['text' => 'A', 'font_size' => 500], 'watermark.settings_invalid'],
            [['text' => 'A', 'rotation' => 400], 'watermark.settings_invalid'],
            [['text' => 'A', 'color' => 'red'], 'watermark.settings_invalid'],
        ];
        foreach ($cases as [$input, $key]) {
            try {
                WatermarkOptions::fromInput($input);
                self::fail('Expected ' . $key);
            } catch (ValidationException $e) {
                self::assertSame($key, $e->messageKey());
            }
        }

        // Kontrol karakterleri temizlenir
        self::assertSame('A B', WatermarkOptions::fromInput(['text' => "A\r\nB"])->text);
    }

    public function testInvalidPageRangeIsRejectedBeforeOperation(): void
    {
        $doc = $this->s->uploadPdf($this->owner, 2);

        try {
            $this->s->tools->watermark($this->owner, $doc->publicId, null, ['text' => 'X'], '1-9');
            self::fail('Expected range error');
        } catch (ValidationException $e) {
            self::assertSame('split.range_out_of_bounds', $e->messageKey());
        }
        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM operations'));
    }
}
