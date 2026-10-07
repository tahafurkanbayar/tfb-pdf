<?php

declare(strict_types=1);

namespace App\Pdf;

/**
 * İşlem sonucunda kaybolan veya değişen PDF özellikleri için kullanıcı uyarıları (spec §36).
 * Uyarılar çeviri anahtarıdır (warnings.*).
 */
final class WarningCollector
{
    /**
     * FPDI ile sayfaları yeniden oluşturan işlemler (birleştirme, bölme, sıralama, döndürme, filigran ...).
     *
     * @param list<PdfInfo> $inputs
     * @return list<string>
     */
    public static function forPageRebuild(array $inputs): array
    {
        $warnings = [];
        foreach ($inputs as $info) {
            foreach ($info->features() as $feature) {
                $key = match ($feature) {
                    'signatures' => 'warnings.signature_invalidated',
                    'forms' => 'warnings.forms_removed',
                    'embedded_files' => 'warnings.embedded_files_removed',
                    'outlines' => 'warnings.bookmarks_removed',
                    'javascript' => 'warnings.javascript_removed',
                    'tagged' => 'warnings.tags_removed',
                    'metadata' => 'warnings.metadata_changed',
                    default => null,
                };
                if ($key !== null) {
                    $warnings[$key] = true;
                }
            }
        }

        // Her yeniden oluşturmada geçerli genel not: bağlantılar ve açıklamalar aktarılmaz
        $warnings['warnings.links_removed'] = true;

        // İmza uyarısı en önce gelsin
        $ordered = array_keys($warnings);
        usort($ordered, static fn (string $a, string $b): int => ($b === 'warnings.signature_invalidated') <=> ($a === 'warnings.signature_invalidated'));

        return $ordered;
    }
}
