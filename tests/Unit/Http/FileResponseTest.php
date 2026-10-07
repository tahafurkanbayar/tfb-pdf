<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\FileResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileResponseTest extends TestCase
{
    /**
     * @return iterable<array{string, array{int, int}|false|null}>
     */
    public static function ranges(): iterable
    {
        yield ['bytes=0-99', [0, 99]];
        yield ['bytes=100-', [100, 999]];
        yield ['bytes=-100', [900, 999]];
        yield ['bytes=990-5000', [990, 999]];
        yield ['bytes=1000-', false];
        yield ['bytes=50-10', false];
        yield ['bytes=-0', false];
        yield ['bytes=0-1,5-6', null];
        yield ['items=0-1', null];
        yield ['bytes=-', null];
    }

    #[DataProvider('ranges')]
    public function testParseRange(string $header, array|false|null $expected): void
    {
        self::assertSame($expected, FileResponse::parseRange($header, 1000));
    }

    public function testStreamsRequestedRange(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tfb');
        file_put_contents($file, '0123456789');

        try {
            $response = new FileResponse($file, 'belge.pdf', 'application/pdf', true, 'bytes=2-5');
            self::assertSame(206, $response->status());
            self::assertSame('bytes 2-5/10', $response->header('Content-Range'));
            self::assertSame('4', $response->header('Content-Length'));

            ob_start();
            $response->send();
            self::assertSame('2345', ob_get_clean());
        } finally {
            unlink($file);
        }
    }

    public function testDispositionIsSafeForUnicodeAndInjection(): void
    {
        $header = FileResponse::disposition('attachment', "Şirket \"rapor\"\r\n../a.pdf");

        self::assertStringNotContainsString("\n", $header);
        self::assertStringNotContainsString('../', $header);
        self::assertStringContainsString("filename*=UTF-8''", $header);
        self::assertStringContainsString('filename="_irket rapor..a.pdf"', $header);
    }
}
