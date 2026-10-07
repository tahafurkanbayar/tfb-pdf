<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Size;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SizeTest extends TestCase
{
    /**
     * @return iterable<array{string|int, int}>
     */
    public static function sizes(): iterable
    {
        yield ['25M', 25 * 1024 * 1024];
        yield ['512K', 512 * 1024];
        yield ['1G', 1024 ** 3];
        yield ['1.5M', (int) round(1.5 * 1024 * 1024)];
        yield ['10MB', 10 * 1024 * 1024];
        yield ['1000', 1000];
        yield [2048, 2048];
        yield ['abc', 0];
        yield ['-5M', 0];
    }

    #[DataProvider('sizes')]
    public function testParse(string|int $input, int $expected): void
    {
        self::assertSame($expected, Size::parse($input));
    }

    public function testFormat(): void
    {
        self::assertSame('512 B', Size::format(512));
        self::assertSame('1.5 KB', Size::format(1536));
        self::assertSame('25.0 MB', Size::format(25 * 1024 * 1024));
    }
}
