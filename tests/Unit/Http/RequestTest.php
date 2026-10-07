<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    /**
     * @return iterable<array{string, string, string}>
     */
    public static function paths(): iterable
    {
        yield ['/tfb-pdf/tr/tools/merge?x=1', '/tfb-pdf', '/tr/tools/merge'];
        yield ['/tfb-pdf/', '/tfb-pdf', '/'];
        yield ['/tfb-pdf', '/tfb-pdf', '/'];
        yield ['/tfb-pdf/tr/', '/tfb-pdf', '/tr'];
        yield ['/tr/about', '', '/tr/about'];
        yield ['/index.php/tr/about', '', '/tr/about'];
        yield ['/tfb-pdf/index.php', '/tfb-pdf', '/'];
        yield ['/tfb-pdfx/tr', '/tfb-pdf', '/tfb-pdfx/tr'];
        yield ['/tr/%00evil', '', '/__invalid__'];
        yield ['/tr/..%5C..%5Cwin.ini', '', '/__invalid__'];
        yield ['/tr/ara%C3%A7lar', '', '/tr/araçlar'];
    }

    #[DataProvider('paths')]
    public function testExtractPath(string $uri, string $base, string $expected): void
    {
        self::assertSame($expected, Request::extractPath($uri, $base));
    }

    public function testForwardedHeadersOnlyTrustedFromConfiguredProxy(): void
    {
        $server = ['REMOTE_ADDR' => '10.0.0.5'];
        $headers = ['x-forwarded-for' => '203.0.113.9, 10.0.0.5', 'x-forwarded-proto' => 'https'];
        $request = new Request('GET', '/', headers: $headers, server: $server);

        self::assertSame('10.0.0.5', $request->ip());
        self::assertFalse($request->isSecure());
        self::assertSame('203.0.113.9', $request->ip(['10.0.0.5']));
        self::assertTrue($request->isSecure(['10.0.0.5']));
    }

    public function testInputReadsPostThenJson(): void
    {
        $request = new Request('POST', '/api/x', post: ['a' => '1'], rawBody: '{"b": 2}');

        self::assertSame('1', $request->input('a'));
        self::assertSame(2, $request->input('b'));
        self::assertNull($request->input('c'));
        self::assertTrue($request->isApi());
        self::assertTrue($request->isStateChanging());
    }
}
