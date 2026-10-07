<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kullanıcının verdiği dosya adı ASLA dosya sistemi yolu olarak kullanılmaz (spec §15).
 * Bu sınıf adı yalnızca metadata ve indirme adı olarak güvenle saklamak için temizler.
 */
final class FilenameSanitizer
{
    private const MAX_LENGTH = 180;

    /**
     * "../../etc/passwd", "C:\\x\\rapor.pdf", "a\0b.pdf" gibi girdilerden yalnızca
     * temiz bir dosya adı bırakır.
     */
    public static function clean(string $name, string $fallback = 'document'): string
    {
        // Geçersiz UTF-8 dizilerini at
        $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');

        // Yol bileşenlerini kaldır (hem / hem \)
        $name = str_replace('\\', '/', $name);
        $name = (string) substr($name, (int) strrpos('/' . $name, '/'));

        // Kontrol karakterleri, null byte, Windows'ta yasaklı karakterler
        $name = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]/u', '', $name) ?? '';
        // Unicode yön kontrol karakterleri (dosya uzantısını görsel olarak gizleme saldırısı)
        $name = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim($name, " .\t");

        if ($name === '' || preg_match('/^\.+$/', $name)) {
            return $fallback;
        }

        if (mb_strlen($name) > self::MAX_LENGTH) {
            $ext = self::extension($name);
            $base = mb_substr(self::basename($name), 0, self::MAX_LENGTH - mb_strlen($ext) - 1);
            $name = $ext === '' ? $base : $base . '.' . $ext;
        }

        return $name;
    }

    /**
     * Küçük harfli uzantı (bilgi amaçlı; güvenlik kararı için kullanılmaz).
     */
    public static function extension(string $name): string
    {
        $pos = strrpos($name, '.');
        if ($pos === false || $pos === 0) {
            return '';
        }

        $ext = mb_strtolower(substr($name, $pos + 1));

        return preg_match('/^[a-z0-9]{1,10}$/', $ext) ? $ext : '';
    }

    public static function basename(string $name): string
    {
        $pos = strrpos($name, '.');

        return $pos === false || $pos === 0 ? $name : substr($name, 0, $pos);
    }

    /**
     * İndirme adı: orijinal adın gövdesi + ek + yeni uzantı. "rapor.docx" → "rapor-v002.pdf"
     */
    public static function downloadName(string $originalName, string $suffix, string $extension): string
    {
        $base = self::basename(self::clean($originalName));
        $suffix = $suffix === '' ? '' : '-' . preg_replace('/[^a-z0-9\-]/i', '', $suffix);

        return self::clean($base . $suffix . '.' . $extension);
    }
}
