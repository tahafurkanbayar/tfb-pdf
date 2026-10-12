<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Exceptions\ProcessingException;
use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Pdf\Ocr\EvrenOcrEngine;
use App\Pdf\Ocr\SearchablePdfBuilder;
use App\Pdf\Parser\ExtendedPdfParser;
use App\Pdf\PdfInspector;
use App\Services\Evren\EvrenHttpClient;
use App\Services\Evren\EvrenOcrClient;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Tests\Support\TempDirectory;
use Tests\Support\TestPdf;

final class EvrenOcrTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = TempDirectory::create('evren');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->dir);
    }

    private function http(array $responses): EvrenHttpClient
    {
        return new class($responses) extends EvrenHttpClient {
            public array $calls = [];
            public function __construct(private array $responses) {}
            public function post(#[\SensitiveParameter] string $key, array $payload, int $timeout): array
            {
                $this->calls[] = $payload;
                return array_shift($this->responses) ?? ['status' => 500, 'body' => '{}'];
            }
        };
    }

    private function client(EvrenHttpClient $http, string $path = 'result.text'): EvrenOcrClient
    {
        return new EvrenOcrClient($http, true, 'test-secret', 'dots-ocr', $path);
    }

    private function image(int $number): UploadedFile
    {
        $image = imagecreatetruecolor(595, 842);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $path = $this->dir . '/' . $number . '.jpg';
        imagejpeg($image, $path);
        imagedestroy($image);
        return UploadedFile::fromPath($path, 'page.jpg', 'image/jpeg');
    }

    public function testConfiguredRequiresExplicitSchemaAndSupportedModel(): void
    {
        $http = $this->http([]);
        self::assertFalse($this->client($http, '')->configured());
        self::assertFalse((new EvrenOcrClient($http, false, 'key', 'dots-ocr', 'text'))->configured());
        self::assertFalse((new EvrenOcrClient($http, true, '', 'dots-ocr', 'text'))->configured());
        self::assertFalse((new EvrenOcrClient($http, true, "key\r\nInjected: header", 'dots-ocr', 'text'))->configured());
        self::assertFalse((new EvrenOcrClient($http, true, 'key', 'auto', 'text'))->configured());
        self::assertTrue($this->client($http, 'data.0.text')->configured());
    }

    public function testDedicatedOcrPayloadAndNestedText(): void
    {
        $http = $this->http([['status' => 200, 'body' => '{"result":{"text":"Türkçe\\r\\nmetin"}}']]);
        self::assertSame("Türkçe\nmetin", $this->client($http)->recognize('jpeg bytes'));
        self::assertSame(['model' => 'dots-ocr', 'image' => 'data:image/jpeg;base64,' . base64_encode('jpeg bytes')], $http->calls[0]);
        self::assertSame('https://evren-llmapi.ssyz.org.tr/v1/ocr', EvrenHttpClient::OCR_URL);
    }

    public function testArrayPathAndEmptyPageText(): void
    {
        self::assertSame('', $this->client($this->http([['status' => 200, 'body' => '{"data":[{"text":""}]}']]), 'data.0.text')->recognize('jpg'));
    }

    public function testProviderFailuresNeverExposeResponseContent(): void
    {
        foreach ([403, 429, 500, 302] as $status) {
            try {
                $this->client($this->http([['status' => $status, 'body' => 'secret-response']]))->recognize('jpg');
                self::fail('Expected provider failure');
            } catch (ProcessingException $e) {
                self::assertStringNotContainsString('secret-response', $e->getMessage());
                self::assertSame($status === 429 ? 'ocr.evren_rate_limit' : 'ocr.evren_failed', $e->messageKey());
            }
        }
    }

    public function testInvalidResponseSchemaIsRejected(): void
    {
        foreach (['not json', '{"object":"list","data":[{"id":"glm-5.3"}]}', '{"result":{"text":["not a string"]}}'] as $body) {
            try {
                $this->client($this->http([['status' => 200, 'body' => $body]]))->recognize('jpg');
                self::fail('Expected schema failure');
            } catch (ProcessingException $e) {
                self::assertSame('ocr.evren_response', $e->messageKey());
            }
        }
    }

    public function testUnacceptedTermsHaveSpecificActionableError(): void
    {
        try {
            $this->client($this->http([['status' => 403, 'body' => '{"error":{"code":"terms_not_accepted"}}']]))->recognize('jpg');
            self::fail('Expected terms failure');
        } catch (ProcessingException $e) {
            self::assertSame('ocr.evren_terms', $e->messageKey());
        }
    }

    public function testCreatesPdfWithInvisibleUnicodeTextAndPreservesOriginal(): void
    {
        $input = TestPdf::create($this->dir . '/input.pdf', 2);
        $before = hash_file('sha256', $input);
        $http = $this->http([
            ['status' => 200, 'body' => '{"result":{"text":"İstanbul OCR"}}'],
            ['status' => 200, 'body' => '{"result":{"text":"Second page"}}'],
        ]);
        $engine = new EvrenOcrEngine($this->client($http), new SearchablePdfBuilder());
        $out = $this->dir . '/out.pdf';
        self::assertSame(['pages' => 2, 'provider' => 'evren'], $engine->run($input, $out, [1 => $this->image(1), 2 => $this->image(2)], $this->dir));
        self::assertSame($before, hash_file('sha256', $input));
        self::assertSame(2, (new PdfInspector())->inspect($out)->pageCount);
        $stream = StreamReader::createByFile($out);
        try {
            $reader = new PdfReader(new ExtendedPdfParser($stream));
            $content = $reader->getPage(1)->getContentStream();
            self::assertStringContainsString('3 Tr', $content);
            self::assertStringContainsString((string) mb_convert_encoding('İstanbul OCR', 'UTF-16BE', 'UTF-8'), $content);
            self::assertStringContainsString(' Do', $content, 'Source page is still drawn');
        } finally {
            $stream->cleanUp();
        }
    }

    public function testAllImagesAreValidatedBeforeFirstApiCall(): void
    {
        $input = TestPdf::create($this->dir . '/input.pdf', 2);
        $http = $this->http([]);
        $bad = $this->dir . '/bad.jpg';
        file_put_contents($bad, 'not an image');
        try {
            (new EvrenOcrEngine($this->client($http), new SearchablePdfBuilder()))->run(
                $input, $this->dir . '/out.pdf', [1 => $this->image(1), 2 => UploadedFile::fromPath($bad, 'bad.jpg')], $this->dir
            );
            self::fail('Expected validation failure');
        } catch (ValidationException) {
            self::assertSame([], $http->calls);
            self::assertFileDoesNotExist($this->dir . '/out.pdf');
        }
    }

    public function testApiFailureProducesNoPartialPdf(): void
    {
        $input = TestPdf::create($this->dir . '/input.pdf', 2);
        $http = $this->http([['status' => 200, 'body' => '{"result":{"text":"one"}}'], ['status' => 500, 'body' => '{}']]);
        try {
            (new EvrenOcrEngine($this->client($http), new SearchablePdfBuilder()))->run(
                $input, $this->dir . '/out.pdf', [1 => $this->image(1), 2 => $this->image(2)], $this->dir
            );
            self::fail('Expected API failure');
        } catch (ProcessingException) {
            self::assertFileDoesNotExist($this->dir . '/out.pdf');
            self::assertSame([], glob($this->dir . '/evren-page-*.jpg'));
        }
    }
}
