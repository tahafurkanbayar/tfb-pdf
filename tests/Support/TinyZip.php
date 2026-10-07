<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * ext-zip olmadan, sıkıştırmasız (stored) ZIP üretir. Office (OOXML) test dosyaları için.
 */
final class TinyZip
{
    /**
     * @param array<string, string> $entries ad => içerik
     */
    public static function build(array $entries): string
    {
        $local = '';
        $central = '';
        foreach ($entries as $name => $data) {
            $crc = crc32($data);
            $offset = strlen($local);
            $local .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, strlen($data), strlen($data), strlen($name), 0) . $name . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, strlen($data), strlen($data), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        }

        return $local . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($local), 0);
    }
}
