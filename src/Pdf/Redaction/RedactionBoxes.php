<?php

declare(strict_types=1);

namespace App\Pdf\Redaction;

use App\Exceptions\ValidationException;

/**
 * Karartma alanları: sayfa no => [[x, y, genişlik, yükseklik], ...]
 * Koordinatlar sayfanın GÖRÜNEN haline göre oransaldır (0..1), sol üst köşe orijinli.
 */
final class RedactionBoxes
{
    public const MAX_PAGES = 30;

    public const MAX_BOXES_PER_PAGE = 100;

    /**
     * @param array<int, list<array{float, float, float, float}>> $pages
     */
    private function __construct(public readonly array $pages)
    {
    }

    /**
     * @param mixed $input JSON çözülmüş dizi: {"1": [[x,y,w,h], ...], ...}
     */
    public static function fromInput(mixed $input, int $totalPages): self
    {
        if (is_string($input)) {
            $input = json_decode($input, true, 8);
        }
        if (!is_array($input)) {
            throw new ValidationException('Boxes missing', 'redact.no_boxes');
        }

        $pages = [];
        foreach ($input as $page => $boxes) {
            $page = filter_var($page, FILTER_VALIDATE_INT);
            if ($page === false || $page < 1 || $page > $totalPages || !is_array($boxes)) {
                throw new ValidationException('Invalid page', 'redact.invalid_boxes');
            }
            if (count($boxes) > self::MAX_BOXES_PER_PAGE) {
                throw new ValidationException('Too many boxes', 'redact.invalid_boxes');
            }

            $clean = [];
            foreach ($boxes as $box) {
                if (!is_array($box) || count($box) !== 4) {
                    throw new ValidationException('Invalid box', 'redact.invalid_boxes');
                }
                $values = [];
                foreach (array_values($box) as $v) {
                    if (!is_numeric($v) || !is_finite((float) $v)) {
                        throw new ValidationException('Invalid box value', 'redact.invalid_boxes');
                    }
                    $values[] = (float) $v;
                }
                [$x, $y, $w, $h] = $values;
                // Sayfa sınırlarına kırp
                $x1 = max(0.0, min(1.0, $x));
                $y1 = max(0.0, min(1.0, $y));
                $x2 = max(0.0, min(1.0, $x + $w));
                $y2 = max(0.0, min(1.0, $y + $h));
                if ($x2 - $x1 < 0.001 || $y2 - $y1 < 0.001) {
                    continue; // Görünmeyecek kadar küçük
                }
                $clean[] = [$x1, $y1, $x2 - $x1, $y2 - $y1];
            }
            if ($clean !== []) {
                $pages[$page] = $clean;
            }
        }

        if (count($pages) > self::MAX_PAGES) {
            throw new ValidationException('Too many pages', 'redact.too_many_pages', ['max' => self::MAX_PAGES]);
        }
        ksort($pages);

        return new self($pages);
    }

    public function isEmpty(): bool
    {
        return $this->pages === [];
    }

    /**
     * @return list<int>
     */
    public function pageNumbers(): array
    {
        return array_keys($this->pages);
    }

    public function boxCount(): int
    {
        return array_sum(array_map('count', $this->pages));
    }

    /**
     * GD görüntüsüne kutuları (dolu siyah) yakar. Kenarlarda yuvarlama kaçağı olmasın diye
     * her kutu 1 piksel genişletilir.
     */
    public function burn(\GdImage $image, int $page): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $black = (int) imagecolorallocate($image, 0, 0, 0);

        foreach ($this->pages[$page] ?? [] as [$x, $y, $w, $h]) {
            imagefilledrectangle(
                $image,
                max(0, (int) floor($x * $width) - 1),
                max(0, (int) floor($y * $height) - 1),
                min($width - 1, (int) ceil(($x + $w) * $width) + 1),
                min($height - 1, (int) ceil(($y + $h) * $height) + 1),
                $black
            );
        }
    }
}
