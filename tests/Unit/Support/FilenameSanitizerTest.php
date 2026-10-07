<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\FilenameSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FilenameSanitizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'normal turkish' => ['Şirket Raporu 2026.pdf', 'Şirket Raporu 2026.pdf'];
        yield 'unix traversal' => ['../../etc/passwd', 'passwd'];
        yield 'deep traversal hidden' => ['../../../../var/www/.env', 'env'];
        yield 'windows path' => ['C:\\Users\\x\\Desktop\\rapor.pdf', 'rapor.pdf'];
        yield 'absolute unix' => ['/etc/shadow', 'shadow'];
        yield 'null byte' => ["fatura.php\0.pdf", 'fatura.php.pdf'];
        yield 'control chars' => ["a\r\nb\t.pdf", 'ab.pdf'];
        yield 'windows reserved chars' => ['a<b>c:d"e|f?g*h.pdf', 'abcdefgh.pdf'];
        yield 'rtl override' => ["fatura\u{202E}fdp.exe", 'faturafdp.exe'];
        yield 'only dots' => ['...', 'document'];
        yield 'empty' => ['', 'document'];
        yield 'trailing slash' => ['folder/', 'document'];
        yield 'whitespace collapse' => ["  çok   boşluk  .pdf ", 'çok boşluk .pdf'];
        yield 'invalid utf8 dropped' => ["bozuk\xC3\x28.pdf", 'bozuk(.pdf'];
    }

    #[DataProvider('names')]
    public function testClean(string $input, string $expected): void
    {
        $clean = FilenameSanitizer::clean($input);

        self::assertSame($expected, $clean);
        self::assertStringNotContainsString('/', $clean);
        self::assertStringNotContainsString('\\', $clean);
        self::assertStringNotContainsString("\0", $clean);
    }

    public function testLongNamesKeepExtension(): void
    {
        $clean = FilenameSanitizer::clean(str_repeat('ğ', 400) . '.pdf');

        self::assertLessThanOrEqual(180, mb_strlen($clean));
        self::assertStringEndsWith('.pdf', $clean);
    }

    public function testExtension(): void
    {
        self::assertSame('pdf', FilenameSanitizer::extension('Rapor.PDF'));
        self::assertSame('docx', FilenameSanitizer::extension('a.b.docx'));
        self::assertSame('', FilenameSanitizer::extension('.htaccess'));
        self::assertSame('', FilenameSanitizer::extension('noext'));
        self::assertSame('', FilenameSanitizer::extension('x.p<h>p'));
    }

    public function testDownloadName(): void
    {
        self::assertSame('rapor-v002.pdf', FilenameSanitizer::downloadName('rapor.docx', 'v002', 'pdf'));
        self::assertSame('passwd-merged.pdf', FilenameSanitizer::downloadName('../../passwd', 'merged', 'pdf'));
    }
}
