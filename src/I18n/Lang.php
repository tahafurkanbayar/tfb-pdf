<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * __() yardımcı fonksiyonunun kullandığı aktif Translator. Bootstrap sırasında ayarlanır.
 */
final class Lang
{
    private static ?Translator $translator = null;

    public static function set(Translator $translator): void
    {
        self::$translator = $translator;
    }

    public static function translator(): Translator
    {
        if (self::$translator === null) {
            self::$translator = new Translator(APP_ROOT . '/resources/lang', 'tr');
        }

        return self::$translator;
    }
}
