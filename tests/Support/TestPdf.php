<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Pdf\Fpdi;

/**
 * Testler için gerçek PDF dosyaları üretir (klasik xref tablolu).
 */
final class TestPdf
{
    /**
     * @param list<array{float, float}>|null $sizes Sayfa boyutları (pt). Null: hepsi A4 dikey.
     */
    public static function create(string $path, int $pages = 3, ?array $sizes = null, string $label = 'Sayfa'): string
    {
        $pdf = new Fpdi('P', 'pt', 'A4');
        $pdf->SetTitle('tfb-pdf test');
        for ($i = 1; $i <= $pages; $i++) {
            $size = $sizes[$i - 1] ?? [595.28, 841.89];
            $pdf->AddPage($size[0] > $size[1] ? 'L' : 'P', [$size[0], $size[1]]);
            $pdf->useUnicodeFont(28);
            $pdf->SetXY(60, 80);
            $pdf->Cell(0, 30, $label . ' ' . $i . ' — çğıöşü ÇĞİÖŞÜ');
            $pdf->SetDrawColor(200, 0, 0);
            $pdf->Rect(40, 40, $size[0] - 80, $size[1] - 80);
        }
        $pdf->Output('F', $path);

        return $path;
    }
}
