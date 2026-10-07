<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    /** @var array<string, string> */
    private array $original = [];

    protected function setUp(): void
    {
        $this->original = Env::all();
    }

    protected function tearDown(): void
    {
        Env::replace($this->original);
    }

    public function testParsesQuotedUnquotedAndCommentedValues(): void
    {
        $parsed = Env::parse(<<<'ENV'
            # yorum
            APP_NAME="TFB PDF"
            SINGLE='a # b'
            PLAIN=value # satır sonu yorumu
            export EXPORTED=yes
            EMPTY=
            HASH_IN_PASSWORD=p#ss
            INVALID KEY=x
            NO_EQUALS
            ENV);

        self::assertSame('TFB PDF', $parsed['APP_NAME']);
        self::assertSame('a # b', $parsed['SINGLE']);
        self::assertSame('value', $parsed['PLAIN']);
        self::assertSame('yes', $parsed['EXPORTED']);
        self::assertSame('', $parsed['EMPTY']);
        self::assertSame('p#ss', $parsed['HASH_IN_PASSWORD']);
        self::assertArrayNotHasKey('INVALID KEY', $parsed);
        self::assertArrayNotHasKey('NO_EQUALS', $parsed);
    }

    public function testStripsUtf8BomAndHandlesWindowsLineEndings(): void
    {
        $parsed = Env::parse("\xEF\xBB\xBFA=1\r\nB=2\r\n");

        self::assertSame(['A' => '1', 'B' => '2'], $parsed);
    }

    public function testTypedGetters(): void
    {
        Env::replace(['FLAG' => 'true', 'OFF' => 'false', 'NUM' => '42', 'BLANK' => '', 'STR' => 'x']);

        self::assertTrue(Env::bool('FLAG', false));
        self::assertFalse(Env::bool('OFF', true));
        self::assertSame(42, Env::int('NUM', 0));
        self::assertSame(7, Env::int('BLANK', 7));
        self::assertSame('fallback', Env::string('MISSING_TFB_KEY', 'fallback'));
        self::assertSame('x', Env::string('STR'));
    }
}
