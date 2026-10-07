<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Pdf\Fpdi;
use App\Pdf\Parser\ExtendedPdfParser;
use App\Pdf\PdfInspector;
use App\Pdf\Redaction\RedactionBoxes;
use App\Pdf\Redaction\Redactor;
use App\Tools\CommandResult;
use App\Tools\ProcessRunner;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PdfReader;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Services;
use Tests\Support\TempDirectory;
use Tests\Support\TestSchema;

final class RedactOperationTest extends DatabaseTestCase
{
    private string $root;

    private Services $s;

    private string $owner;

    protected function setUp(): void
    {
        if (!Redactor::available()) {
            self::markTestSkipped('GD yok');
        }
        TestSchema::migrateFresh(self::db());
        $this->root = TempDirectory::create('redact');
        $this->s = new Services(self::db(), $this->root);
        $this->owner = hash('sha256', 'redact-owner');
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            TempDirectory::remove($this->root);
        }
    }

    private function secretPdf(): string
    {
        $pdf = new Fpdi();
        foreach (['TCKN 12345678901 GİZLİ', 'Kamuya açık metin'] as $text) {
            $pdf->AddPage('P', [595.28, 841.89]);
            $pdf->useUnicodeFont(18);
            $pdf->Text(60, 100, $text);
        }
        $pdf->Output('F', $this->root . '/secret.pdf');

        return $this->root . '/secret.pdf';
    }

    /**
     * Tarayıcının ürettiği sayfa görüntüsünü taklit eder (A4, 150 DPI).
     */
    private function browserImage(string $name, int $width = 1240, int $height = 1754): UploadedFile
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 100, 150, 900, 250, (int) imagecolorallocate($img, 200, 30, 30)); // "gizli bilgi"
        imagejpeg($img, $this->root . '/' . $name, 92);

        return UploadedFile::fromPath($this->root . '/' . $name, $name, 'image/jpeg');
    }

    /**
     * @return array{content: string, fonts: bool, images: int}
     */
    private static function pageContent(string $file, int $page): array
    {
        $stream = StreamReader::createByFile($file);
        $parser = new ExtendedPdfParser($stream);
        $reader = new PdfReader($parser);
        $p = $reader->getPage($page);
        $content = $p->getContentStream();

        // Sayfa içeriği bir form XObject (FPDI şablonu) ise onun içeriğine de bak
        $resources = PdfType::resolve($p->getAttribute('Resources'), $parser);
        $xobjects = $resources instanceof PdfDictionary ? PdfType::resolve(PdfDictionary::get($resources, 'XObject'), $parser) : null;
        // Sayfanın kendi içeriği (şablon dışında) font seçiyor mu
        $fonts = (bool) preg_match('/\bTf\b/', $content);
        $images = 0;
        // FPDF tüm sayfalar için tek kaynak sözlüğü kullanır: yalnızca bu sayfanın Do ile çizdiği nesnelere bakılır
        preg_match_all('/\/(\S+)\s+Do\b/', $content, $used);
        if ($xobjects instanceof PdfDictionary) {
            foreach (array_intersect_key($xobjects->value, array_flip($used[1])) as $ref) {
                $x = PdfType::resolve($ref, $parser);
                $subtype = PdfDictionary::get($x->value, 'Subtype')->value;
                if ($subtype === 'Image') {
                    $images++;
                } elseif ($subtype === 'Form') {
                    $content .= "\n" . $x->getUnfilteredStream();
                    $fonts = $fonts || (bool) preg_match('/\bTf\b/', (string) $x->getUnfilteredStream());
                }
            }
        }
        $stream->cleanUp();

        return ['content' => $content, 'fonts' => $fonts, 'images' => $images];
    }

    /**
     * Dosyadaki tüm akışların filtresi çözülmüş içeriği.
     *
     * @return list<string>
     */
    private static function allStreams(string $file): array
    {
        $stream = StreamReader::createByFile($file);
        $parser = new ExtendedPdfParser($stream);
        $xref = $parser->getCrossReference();
        $out = [];
        for ($n = 1; $n < (int) PdfDictionary::get($xref->getTrailer(), 'Size')->value; $n++) {
            try {
                $object = $xref->getIndirectObject($n);
            } catch (\Throwable) {
                continue;
            }
            if ($object->value instanceof \setasign\Fpdi\PdfParser\Type\PdfStream) {
                try {
                    $out[] = (string) $object->value->getUnfilteredStream();
                } catch (\Throwable) {
                    $out[] = (string) $object->value->getStream();
                }
            }
        }
        $stream->cleanUp();

        return $out;
    }

    public function testRedactedPageLosesAllTextAndBoxIsBurnedIntoPixels(): void
    {
        $doc = $this->s->documents->upload(UploadedFile::fromPath($this->secretPdf(), 'gizli.pdf'), $this->owner);
        $before = self::pageContent($this->s->versionPath($doc, 0), 1);
        self::assertStringContainsString('BT', $before['content'], 'Kaynakta metin nesnesi olmalı');
        self::assertTrue($before['fonts']);

        // Kırmızı "gizli bilgi" bölgesi: x 100-900 / 1240, y 150-250 / 1754
        $box = [100 / 1240, 150 / 1754, 800 / 1240, 100 / 1754];
        $result = $this->s->tools->redact($this->owner, $doc->publicId, null, json_encode(['1' => [$box]]), [1 => $this->browserImage('p1.jpg')]);

        self::assertSame('completed', $result->status);
        $out = $this->s->storage->resolve($result->versions[0]->storagePath);
        self::assertSame(2, (new PdfInspector())->inspect($out)->pageCount);

        // 1. sayfa: metin operatörü yok, font yok, yalnızca görüntü
        $after = self::pageContent($out, 1);
        self::assertStringNotContainsString('BT', $after['content']);
        self::assertStringNotContainsString('Tj', $after['content']);
        self::assertFalse($after['fonts']);
        self::assertSame(1, $after['images']);

        // 2. sayfa (karartılmadı): metin korunur
        $page2 = self::pageContent($out, 2);
        self::assertStringContainsString('BT', $page2['content']);

        // Dosyanın HİÇBİR akışında gizli metin kalmamalı (UTF-16BE ve düz metin olarak)
        foreach (self::allStreams($out) as $data) {
            self::assertStringNotContainsString(mb_convert_encoding('12345678901', 'UTF-16BE', 'UTF-8'), $data);
            self::assertStringNotContainsString('12345678901', $data);
        }
        // Kontrol: aynı arama kaynakta gizli metni bulur (yöntemin çalıştığının kanıtı)
        $found = false;
        foreach (self::allStreams($this->s->versionPath($doc, 0)) as $data) {
            $found = $found || str_contains($data, mb_convert_encoding('12345678901', 'UTF-16BE', 'UTF-8'));
        }
        self::assertTrue($found);

        // Görüntüde kutu bölgesi siyah, kutu dışı beyaz
        $raw = (string) file_get_contents($out);
        $start = (int) strpos($raw, "\xFF\xD8\xFF");
        $jpeg = substr($raw, $start, (int) strpos($raw, "\xFF\xD9", $start) - $start + 2);
        $img = imagecreatefromstring($jpeg);
        self::assertNotFalse($img);
        $center = imagecolorsforindex($img, imagecolorat($img, 500, 200));
        $outside = imagecolorsforindex($img, imagecolorat($img, 600, 900));
        self::assertLessThan(30, $center['red'] + $center['green'] + $center['blue'], 'Kutu bölgesi siyah olmalı');
        self::assertGreaterThan(700, $outside['red'] + $outside['green'] + $outside['blue']);

        // Audit ve uyarılar
        self::assertContains('warnings.rasterized_pages', $result->warnings);
        self::assertContains('redact.residual_warning', $result->warnings);
        self::assertSame('browser+gd', self::db()->scalar("SELECT engine FROM operations WHERE type = 'redact'"));
    }

    public function testServerBoxesAreAppliedEvenIfBrowserImageWasNotRedacted(): void
    {
        $doc = $this->s->documents->upload(UploadedFile::fromPath($this->secretPdf(), 'gizli.pdf'), $this->owner);
        // browserImage() kırmızı alanı karartılmamış olarak gönderir; sunucu kutuyu yine de uygulamalı
        $result = $this->s->tools->redact($this->owner, $doc->publicId, null, ['1' => [[0, 0, 1, 0.5]]], [1 => $this->browserImage('raw.jpg')]);

        $raw = (string) file_get_contents($this->s->storage->resolve($result->versions[0]->storagePath));
        $start = (int) strpos($raw, "\xFF\xD8\xFF");
        $img = imagecreatefromstring(substr($raw, $start, (int) strpos($raw, "\xFF\xD9", $start) - $start + 2));
        $c = imagecolorsforindex($img, imagecolorat($img, 500, 200));
        self::assertLessThan(30, $c['red'] + $c['green'] + $c['blue']);
    }

    public function testValidation(): void
    {
        $doc = $this->s->documents->upload(UploadedFile::fromPath($this->secretPdf(), 'gizli.pdf'), $this->owner);
        $box = ['1' => [[0.1, 0.1, 0.2, 0.2]]];

        $cases = [
            [fn () => $this->s->tools->redact($this->owner, $doc->publicId, null, $box, []), 'redact.image_missing'],
            [fn () => $this->s->tools->redact($this->owner, $doc->publicId, null, $box, [1 => $this->browserImage('wide.jpg', 1754, 1240)]), 'redact.image_invalid'],
            [fn () => $this->s->tools->redact($this->owner, $doc->publicId, null, ['5' => [[0, 0, 1, 1]]], []), 'redact.invalid_boxes'],
            [fn () => $this->s->tools->redact($this->owner, $doc->publicId, null, ['1' => [[0, 0, 'x', 1]]], []), 'redact.invalid_boxes'],
            [fn () => $this->s->tools->redact($this->owner, $doc->publicId, null, 'not json', []), 'redact.no_boxes'],
        ];
        foreach ($cases as [$fn, $key]) {
            try {
                $fn();
                self::fail('Expected ' . $key);
            } catch (ValidationException $e) {
                self::assertSame($key, $e->messageKey());
            }
        }
    }

    public function testBoxesAreClampedAndTinyBoxesDropped(): void
    {
        $boxes = RedactionBoxes::fromInput(['1' => [[-0.5, 0.9, 1, 0.5], [0.2, 0.2, 0.0001, 0.5]], '2' => [[0.1, 0.1, 0.0002, 0.0002]]], 2);

        self::assertSame([1], $boxes->pageNumbers());
        self::assertSame([[0.0, 0.9, 0.5, 0.09999999999999998]], $boxes->pages[1]);
    }

    public function testGhostscriptRenderingPath(): void
    {
        $input = $this->secretPdf();
        $runner = new class () extends ProcessRunner {
            public array $command = [];

            public function run(array $command, int $timeoutSeconds = 60, ?string $cwd = null, ?array $env = null): CommandResult
            {
                $this->command = $command;
                $out = substr((string) current(array_filter($command, static fn ($a) => str_starts_with($a, '-sOutputFile='))), 13);
                $img = imagecreatetruecolor(1240, 1754);
                imagefill($img, 0, 0, (int) imagecolorallocate($img, 255, 255, 255));
                imagepng($img, $out);

                return new CommandResult(0, '', '', false, 3);
            }
        };

        $redactor = new Redactor($runner, '/usr/bin/gs', 30);
        self::assertTrue($redactor->serverRendering());
        $info = (new PdfInspector())->inspect($input);
        $redactor->redact($input, $this->root . '/gs-out.pdf', $info, RedactionBoxes::fromInput(['2' => [[0, 0, 0.5, 0.5]]], 2), [], $this->root);

        self::assertContains('-dSAFER', $runner->command);
        self::assertContains('-dFirstPage=2', $runner->command);
        self::assertContains('-r150', $runner->command);
        self::assertStringContainsString('BT', self::pageContent($this->root . '/gs-out.pdf', 1)['content']);
        self::assertStringNotContainsString('BT', self::pageContent($this->root . '/gs-out.pdf', 2)['content']);
    }
}
