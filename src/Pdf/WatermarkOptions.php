<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Exceptions\ValidationException;

/**
 * Metin filigranı ayarları (spec §12: metin, konum, döndürme, opaklık, font boyutu).
 */
final class WatermarkOptions
{
    public const POSITIONS = ['center', 'top', 'bottom', 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'tile'];

    /**
     * @param array{int, int, int} $color
     */
    public function __construct(
        public readonly string $text,
        public readonly string $position = 'center',
        public readonly int $rotation = 45,
        public readonly float $opacity = 0.3,
        public readonly int $fontSize = 48,
        public readonly array $color = [128, 128, 128],
        public readonly bool $bold = true,
        public readonly bool $under = false,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromInput(array $input): self
    {
        $text = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($input['text'] ?? '')) ?? '');
        if ($text === '' || mb_strlen($text) > 100) {
            throw new ValidationException('Invalid watermark text', 'watermark.text_invalid');
        }

        $position = (string) ($input['position'] ?? 'center');
        if (!in_array($position, self::POSITIONS, true)) {
            throw new ValidationException('Invalid position', 'watermark.position_invalid');
        }

        $rotation = filter_var($input['rotation'] ?? 45, FILTER_VALIDATE_INT, ['options' => ['min_range' => -180, 'max_range' => 180]]);
        $opacity = filter_var($input['opacity'] ?? 0.3, FILTER_VALIDATE_FLOAT);
        $fontSize = filter_var($input['font_size'] ?? 48, FILTER_VALIDATE_INT, ['options' => ['min_range' => 6, 'max_range' => 200]]);
        if ($rotation === false || $fontSize === false || $opacity === false || $opacity < 0.05 || $opacity > 1) {
            throw new ValidationException('Invalid watermark settings', 'watermark.settings_invalid');
        }

        $hex = (string) ($input['color'] ?? '#808080');
        if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            throw new ValidationException('Invalid color', 'watermark.settings_invalid');
        }

        return new self(
            $text,
            $position,
            (int) $rotation,
            round((float) $opacity, 2),
            (int) $fontSize,
            [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])],
            filter_var($input['bold'] ?? true, FILTER_VALIDATE_BOOL),
            ($input['layer'] ?? 'over') === 'under',
        );
    }

    /**
     * @return array<string, mixed> Kayıt için (operation params)
     */
    public function toArray(): array
    {
        return [
            'text_length' => mb_strlen($this->text),
            'position' => $this->position,
            'rotation' => $this->rotation,
            'opacity' => $this->opacity,
            'font_size' => $this->fontSize,
            'color' => sprintf('#%02x%02x%02x', ...$this->color),
            'bold' => $this->bold,
            'layer' => $this->under ? 'under' : 'over',
        ];
    }
}
