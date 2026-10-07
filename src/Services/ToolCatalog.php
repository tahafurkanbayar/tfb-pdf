<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Arayüzde gösterilen PDF araçları. Adlar pdf.{slug}, açıklamalar tools.descriptions.{slug}
 * çeviri anahtarlarından gelir.
 */
final class ToolCatalog
{
    /** @var array<string, array{icon: string, requires: ?string}> */
    private const TOOLS = [
        'merge' => ['icon' => 'files', 'requires' => null],
        'split' => ['icon' => 'scissors', 'requires' => null],
        'reorder' => ['icon' => 'grid-3x3-gap', 'requires' => null],
        'rotate' => ['icon' => 'arrow-clockwise', 'requires' => null],
        'compress' => ['icon' => 'file-zip', 'requires' => null],
        'watermark' => ['icon' => 'droplet', 'requires' => null],
        'redact' => ['icon' => 'eraser', 'requires' => null],
        'ocr' => ['icon' => 'fonts', 'requires' => 'ocr'],
        'office' => ['icon' => 'file-earmark-word', 'requires' => 'office'],
        'sign' => ['icon' => 'pen', 'requires' => null],
    ];

    /**
     * @param array<string, bool> $capabilities Yetenek adı => mevcut mu (ör. ['ocr' => false])
     */
    public function __construct(private readonly array $capabilities = [])
    {
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::TOOLS);
    }

    public static function exists(string $slug): bool
    {
        return isset(self::TOOLS[$slug]);
    }

    /**
     * @return list<array{slug: string, icon: string, available: bool, requires: ?string}>
     */
    public function all(): array
    {
        $tools = [];
        foreach (self::TOOLS as $slug => $tool) {
            $tools[] = [
                'slug' => $slug,
                'icon' => $tool['icon'],
                'requires' => $tool['requires'],
                'available' => $tool['requires'] === null || ($this->capabilities[$tool['requires']] ?? false),
            ];
        }

        return $tools;
    }
}
