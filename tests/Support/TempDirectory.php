<?php

declare(strict_types=1);

namespace Tests\Support;

final class TempDirectory
{
    public static function create(string $prefix): string
    {
        $dir = str_replace('\\', '/', sys_get_temp_dir()) . '/tfb-' . $prefix . '-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }

    public static function remove(string $dir): void
    {
        if (!is_dir($dir) || !str_contains($dir, '/tfb-')) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
