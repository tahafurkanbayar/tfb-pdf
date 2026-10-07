<?php

declare(strict_types=1);

namespace Tests\Unit\I18n;

use App\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/tfb-lang-' . bin2hex(random_bytes(4));
        mkdir(self::$dir . '/tr', 0777, true);
        mkdir(self::$dir . '/en', 0777, true);

        file_put_contents(self::$dir . '/tr/upload.php', '<?php return ' . var_export([
            'success' => 'Dosya başarıyla yüklendi.',
            'too_big' => 'En fazla :max yükleyebilirsiniz.',
            'only_tr' => 'Sadece Türkçe',
            'nested' => ['deep' => 'İç içe'],
        ], true) . ';');
        file_put_contents(self::$dir . '/en/upload.php', '<?php return ' . var_export([
            'success' => 'File uploaded successfully.',
            'too_big' => 'The maximum is :max.',
            'pages' => ':count page|:count pages',
            'nested' => ['deep' => 'Nested'],
        ], true) . ';');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (['tr', 'en'] as $locale) {
            @unlink(self::$dir . "/$locale/upload.php");
            @rmdir(self::$dir . "/$locale");
        }
        @rmdir(self::$dir);
    }

    public function testTranslatesInBothLocales(): void
    {
        $t = new Translator(self::$dir, 'tr');
        self::assertSame('Dosya başarıyla yüklendi.', $t->get('upload.success'));
        self::assertSame('İç içe', $t->get('upload.nested.deep'));

        $t->setLocale('en');
        self::assertSame('File uploaded successfully.', $t->get('upload.success'));
        self::assertSame('Nested', $t->get('upload.nested.deep'));
    }

    public function testReplacesPlaceholders(): void
    {
        $t = new Translator(self::$dir, 'en');
        self::assertSame('The maximum is 25 MB.', $t->get('upload.too_big', ['max' => '25 MB']));
    }

    public function testFallsBackToTurkishThenReturnsKey(): void
    {
        $t = new Translator(self::$dir, 'en', 'tr');
        self::assertSame('Sadece Türkçe', $t->get('upload.only_tr'));
        self::assertSame('upload.does_not_exist', $t->get('upload.does_not_exist'));
        self::assertSame(['upload.does_not_exist'], $t->missingKeys());
    }

    public function testChoice(): void
    {
        $t = new Translator(self::$dir, 'en');
        self::assertSame('1 page', $t->choice('upload.pages', 1));
        self::assertSame('5 pages', $t->choice('upload.pages', 5));
    }

    public function testRejectsUnsafeLocaleAndGroupNames(): void
    {
        $t = new Translator(self::$dir, '../tr');
        self::assertSame('../tr', $t->locale());
        // Güvensiz locale ile dosya yüklenmez, Türkçe yedeğe düşer
        self::assertSame('Dosya başarıyla yüklendi.', $t->get('upload.success'));
        self::assertSame('../upload.x', $t->get('../upload.x'));
        self::assertSame('nogroup', $t->get('nogroup'));
    }

    public function testGroupFlattensKeys(): void
    {
        $t = new Translator(self::$dir, 'en');
        $group = $t->group('upload');
        self::assertSame('Nested', $group['upload.nested.deep']);
        self::assertArrayHasKey('upload.success', $group);
    }
}
