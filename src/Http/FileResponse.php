<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Dosyayı belleğe yüklemeden parça parça gönderir (streaming).
 * Tek aralıklı HTTP Range isteklerini destekler; PDF.js büyük dosyalarda
 * yalnızca gereken bölümleri bu sayede indirir.
 */
final class FileResponse extends Response
{
    private const CHUNK_SIZE = 65536;

    private int $start = 0;

    private int $length;

    public function __construct(
        private readonly string $path,
        string $downloadName,
        string $mimeType,
        bool $inline = false,
        ?string $rangeHeader = null,
    ) {
        $size = (int) filesize($path);
        $this->length = $size;

        parent::__construct('', 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => self::disposition($inline ? 'inline' : 'attachment', $downloadName),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Accept-Ranges' => 'bytes',
            // İndirilen içerik aynı origin'de çalıştırılamasın
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);

        $range = $rangeHeader !== null ? self::parseRange($rangeHeader, $size) : null;
        if ($range === false) {
            $this->status = 416;
            $this->length = 0;
            $this->setHeader('Content-Range', 'bytes */' . $size);
        } elseif ($range !== null) {
            [$this->start, $end] = $range;
            $this->length = $end - $this->start + 1;
            $this->status = 206;
            $this->setHeader('Content-Range', sprintf('bytes %d-%d/%d', $this->start, $end, $size));
        }

        $this->setHeader('Content-Length', (string) $this->length);
    }

    /**
     * RFC 6266: ASCII yedek ad + UTF-8 filename*.
     */
    public static function disposition(string $type, string $filename): string
    {
        $filename = str_replace(["\r", "\n", "\0", '"', '\\', '/'], '', $filename);
        $ascii = preg_replace('/[^\x20-\x7E]/u', '_', $filename) ?? 'download';

        return sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $type, $ascii, rawurlencode($filename));
    }

    /**
     * @return array{int, int}|false|null null: aralık yok/desteklenmiyor (tüm dosya), false: karşılanamaz (416)
     */
    public static function parseRange(string $header, int $size): array|false|null
    {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) || ($m[1] === '' && $m[2] === '')) {
            return null; // Çoklu aralık veya bozuk başlık: tüm dosyayı gönder
        }

        if ($m[1] === '') {
            // Son N bayt
            $suffix = (int) $m[2];
            if ($suffix === 0) {
                return false;
            }

            return [max(0, $size - $suffix), $size - 1];
        }

        $start = (int) $m[1];
        $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);

        if ($start >= $size || $start > $end) {
            return false;
        }

        return [$start, $end];
    }

    public function length(): int
    {
        return $this->length;
    }

    protected function sendBody(): void
    {
        if ($this->length <= 0) {
            return;
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            return;
        }

        // Çıktı tamponlarını boşalt: büyük dosya belleğe birikmesin
        while (ob_get_level() > 0 && !defined('TFB_TESTING')) {
            ob_end_flush();
        }

        fseek($handle, $this->start);
        $remaining = $this->length;
        while ($remaining > 0 && !feof($handle)) {
            $chunk = fread($handle, min(self::CHUNK_SIZE, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
            if (!defined('TFB_TESTING')) {
                flush();
            }
            if (connection_aborted() === 1) {
                break;
            }
        }
        fclose($handle);
    }
}
