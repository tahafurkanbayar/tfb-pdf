<?php

declare(strict_types=1);

namespace Tests\Unit\I18n;

use App\I18n\LocaleNegotiator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocaleNegotiatorTest extends TestCase
{
    private LocaleNegotiator $negotiator;

    protected function setUp(): void
    {
        $this->negotiator = new LocaleNegotiator(['tr', 'en'], 'tr');
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function acceptLanguageHeaders(): iterable
    {
        yield 'english us' => ['en-US,en;q=0.9', 'en'];
        yield 'turkish' => ['tr-TR,tr;q=0.9,en;q=0.8', 'tr'];
        yield 'quality order wins' => ['en;q=0.5,tr;q=0.8', 'tr'];
        yield 'unsupported first' => ['de-DE,de;q=0.9,en;q=0.7', 'en'];
        yield 'only unsupported' => ['de,fr', null];
        yield 'zero quality ignored' => ['en;q=0,de', null];
        yield 'wildcard ignored' => ['*', null];
        yield 'empty' => ['', null];
        yield 'null' => [null, null];
        yield 'garbage' => [';;;,,,q=', null];
    }

    #[DataProvider('acceptLanguageHeaders')]
    public function testAcceptLanguage(?string $header, ?string $expected): void
    {
        self::assertSame($expected, $this->negotiator->fromAcceptLanguage($header));
    }

    public function testPriorityUrlThenCookieThenBrowserThenDefault(): void
    {
        self::assertSame('en', $this->negotiator->negotiate('en', 'tr', 'tr'));
        self::assertSame('en', $this->negotiator->negotiate(null, 'en', 'tr'));
        self::assertSame('en', $this->negotiator->negotiate(null, null, 'en-GB'));
        self::assertSame('tr', $this->negotiator->negotiate(null, null, 'de-DE'));
        self::assertSame('tr', $this->negotiator->negotiate(null, null, null));
    }

    public function testInvalidUrlOrCookieValuesAreIgnored(): void
    {
        self::assertSame('en', $this->negotiator->negotiate('xx', '<script>', 'en'));
    }
}
