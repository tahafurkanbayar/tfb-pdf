<?php

declare(strict_types=1);

namespace Tests\Unit\I18n;

use App\I18n\TranslationChecker;
use PHPUnit\Framework\TestCase;

/**
 * Gerçek çeviri dosyaları üzerinde: TR ve EN eksiksiz ve tutarlı olmalı,
 * kodda kullanılan her anahtar iki dilde de tanımlı olmalı (spec §46).
 */
final class TranslationCompletenessTest extends TestCase
{
    public function testTurkishAndEnglishCatalogsAreComplete(): void
    {
        $checker = new TranslationChecker(APP_ROOT . '/resources/lang', ['tr', 'en']);

        $problems = $checker->check([
            APP_ROOT . '/src',
            APP_ROOT . '/resources/views',
            APP_ROOT . '/routes',
            APP_ROOT . '/public/assets/js',
        ]);

        self::assertSame([], $problems, implode("\n", $problems));
    }

    public function testRequiredSpecKeysExist(): void
    {
        $catalog = (new TranslationChecker(APP_ROOT . '/resources/lang', ['tr', 'en']))->catalog();

        $required = [
            'common.save', 'common.cancel', 'common.delete',
            'upload.title', 'upload.success', 'upload.invalid_file', 'upload.file_too_large',
            'pdf.merge', 'pdf.split', 'pdf.rotate', 'pdf.compress',
            'errors.generic', 'errors.permission_denied', 'errors.processing_failed',
            'errors.tool_unavailable', 'notices.privacy',
        ];

        foreach (['tr', 'en'] as $locale) {
            foreach ($required as $key) {
                self::assertArrayHasKey($key, $catalog[$locale], "[$locale] $key");
            }
        }

        self::assertSame('Bu özellik mevcut hosting ortamında kullanılamıyor.', $catalog['tr']['errors.tool_unavailable']);
        self::assertSame('Merge PDF', $catalog['en']['pdf.merge']);
        self::assertSame('PDF Birleştir', $catalog['tr']['pdf.merge']);
    }

    public function testCheckerDetectsMissingKeysAndPlaceholderMismatch(): void
    {
        $dir = sys_get_temp_dir() . '/tfb-check-' . bin2hex(random_bytes(4));
        mkdir($dir . '/tr', 0777, true);
        mkdir($dir . '/en', 0777, true);
        mkdir($dir . '/src', 0777, true);
        file_put_contents($dir . '/tr/demo.php', "<?php return ['a' => 'A :x', 'b' => 'B', 'empty' => ''];");
        file_put_contents($dir . '/en/demo.php', "<?php return ['a' => 'A :y', 'empty' => 'E'];");
        file_put_contents($dir . '/src/x.php', "<?php echo __('demo.used_but_missing'); \$f = 'demo.worker.min.js';");

        try {
            $problems = implode("\n", (new TranslationChecker($dir, ['tr', 'en']))->check([$dir . '/src']));

            self::assertStringContainsString('[en] eksik anahtar: demo.b', $problems);
            self::assertStringContainsString('[tr] boş çeviri: demo.empty', $problems);
            self::assertStringContainsString('yer tutucu uyumsuz: demo.a', $problems);
            self::assertStringContainsString('demo.used_but_missing', $problems);
            self::assertStringNotContainsString('demo.worker', $problems);
        } finally {
            array_map('unlink', [$dir . '/tr/demo.php', $dir . '/en/demo.php', $dir . '/src/x.php']);
            array_map('rmdir', [$dir . '/tr', $dir . '/en', $dir . '/src', $dir]);
        }
    }
}
