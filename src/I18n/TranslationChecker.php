<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * Çeviri bütünlüğü kontrolü (spec §46):
 *  - Bir dilde olup diğerinde olmayan anahtarlar
 *  - Boş çeviriler
 *  - Yer tutucu (:name) uyumsuzlukları
 *  - Kodda kullanılan ama hiçbir dilde tanımlı olmayan anahtarlar
 */
final class TranslationChecker
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly string $langPath,
        private readonly array $locales,
    ) {
    }

    /**
     * @return array<string, array<string, string>> locale => [anahtar => değer]
     */
    public function catalog(): array
    {
        $catalog = [];
        foreach ($this->locales as $locale) {
            $catalog[$locale] = [];
            foreach (glob($this->langPath . '/' . $locale . '/*.php') ?: [] as $file) {
                $data = require $file;
                Translator::flatten(is_array($data) ? $data : [], basename($file, '.php'), $catalog[$locale]);
            }
            ksort($catalog[$locale]);
        }

        return $catalog;
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->locales as $locale) {
            foreach (glob($this->langPath . '/' . $locale . '/*.php') ?: [] as $file) {
                $groups[basename($file, '.php')] = true;
            }
        }

        return array_keys($groups);
    }

    /**
     * @param list<string> $scanPaths Kullanılan anahtarlar için taranacak dizinler
     * @return list<string> Bulunan sorunlar (boşsa her şey yolunda)
     */
    public function check(array $scanPaths = []): array
    {
        $problems = [];
        $catalog = $this->catalog();

        foreach ($this->locales as $locale) {
            foreach ($this->locales as $other) {
                if ($locale === $other) {
                    continue;
                }
                foreach (array_diff_key($catalog[$locale], $catalog[$other]) as $key => $_) {
                    $problems[] = sprintf('[%s] eksik anahtar: %s (%s dilinde var)', $other, $key, $locale);
                }
            }

            foreach ($catalog[$locale] as $key => $value) {
                if (trim($value) === '') {
                    $problems[] = sprintf('[%s] boş çeviri: %s', $locale, $key);
                }
            }
        }

        $base = $this->locales[0];
        foreach ($catalog[$base] as $key => $value) {
            foreach (array_slice($this->locales, 1) as $other) {
                if (!isset($catalog[$other][$key])) {
                    continue;
                }
                $a = self::placeholders($value);
                $b = self::placeholders($catalog[$other][$key]);
                if ($a !== $b) {
                    $problems[] = sprintf(
                        'yer tutucu uyumsuz: %s (%s: %s, %s: %s)',
                        $key,
                        $base,
                        implode(' ', $a) ?: '-',
                        $other,
                        implode(' ', $b) ?: '-'
                    );
                }
            }
        }

        foreach ($this->usedKeys($scanPaths) as $key => $file) {
            foreach ($this->locales as $locale) {
                if (!isset($catalog[$locale][$key])) {
                    $problems[] = sprintf('[%s] kodda kullanılan anahtar tanımlı değil: %s (%s)', $locale, $key, $file);
                }
            }
        }

        return $problems;
    }

    /**
     * PHP dosyalarında: ilk bölümü bir çeviri grubu olan tüm string sabitleri ('upload.success').
     * JS dosyalarında: yalnızca t('...') çağrıları.
     *
     * @param list<string> $paths
     * @return array<string, string> anahtar => ilk görüldüğü dosya
     */
    public function usedKeys(array $paths): array
    {
        $groups = array_flip($this->groups());
        $found = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                $ext = $file->getExtension();
                if (!in_array($ext, ['php', 'js'], true) || str_contains($file->getPathname(), 'vendor')) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                // Noktayla biten dinamik önekler ('hash.status.' + durum) anahtar sayılmaz
                $pattern = $ext === 'js'
                    ? '/\bt\(\s*[\'"]([a-z_]+(?:\.[a-z0-9_]+)+)[\'"]/'
                    : '/[\'"]([a-z_]+\.[a-z0-9_]+(?:\.[a-z0-9_]+)*)[\'"]/';

                if (!preg_match_all($pattern, $source, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $key) {
                    $group = explode('.', $key)[0];
                    // Dosya adı gibi görünen değerleri ("pdf.worker.min.js") atla
                    if (!isset($groups[$group]) || preg_match('/\.(php|js|mjs|css|pdf|json|png|jpg|svg|zip|txt)$/', $key)) {
                        continue;
                    }
                    $found[$key] ??= str_replace('\\', '/', $file->getPathname());
                }
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * @return list<string>
     */
    private static function placeholders(string $value): array
    {
        preg_match_all('/:([a-z_]+)/', $value, $m);
        $names = array_values(array_unique($m[1]));
        sort($names);

        return $names;
    }
}
