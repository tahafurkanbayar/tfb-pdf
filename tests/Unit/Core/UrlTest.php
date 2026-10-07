<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Url;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testBuildsPathsUnderSubdirectory(): void
    {
        $url = new Url('http://localhost/tfb-pdf', 'tr');

        self::assertSame('/tfb-pdf', $url->basePath());
        self::assertSame('/tfb-pdf/api/documents', $url->to('/api/documents'));
        self::assertSame('/tfb-pdf/tr/', $url->page('/'));
        self::assertSame('/tfb-pdf/en/tools/merge?document=x', $url->page('tools/merge', ['document' => 'x'], 'en'));
        self::assertSame('http://localhost/tfb-pdf/tr/sign/abc', $url->absolute('/tr/sign/abc'));
    }

    public function testBuildsPathsAtDomainRoot(): void
    {
        $url = new Url('https://pdf.example.com', 'en');

        self::assertSame('', $url->basePath());
        self::assertSame('/en/about', $url->page('/about'));
        self::assertSame('https://pdf.example.com/x', $url->absolute('/x'));
    }

    public function testSwapLocaleKeepsPage(): void
    {
        self::assertSame('/en/tools/merge', Url::swapLocale('/tr/tools/merge', 'en', ['tr', 'en']));
        self::assertSame('/tr/', Url::swapLocale('/en', 'tr', ['tr', 'en']));
        self::assertSame('/en/', Url::swapLocale('/api/x', 'en', ['tr', 'en']));
    }

    public function testRejectsOpenRedirects(): void
    {
        self::assertTrue(Url::isSafeInternalPath('/tr/about?x=1'));
        foreach (['//evil.com', 'https://evil.com', '/\\evil.com', "/tr\r\nX: y", '', 'tr/about'] as $bad) {
            self::assertFalse(Url::isSafeInternalPath($bad), $bad);
        }
    }
}
