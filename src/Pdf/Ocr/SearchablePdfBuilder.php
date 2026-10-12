<?php

declare(strict_types=1);

namespace App\Pdf\Ocr;

use App\Pdf\Fpdi;

/** Preserve source page appearance; text-only OCR has approximate selection geometry. */
final class SearchablePdfBuilder
{
    /** @param list<string> $texts */
    public function build(string $input, string $output, array $texts): int
    {
        $pdf = new Fpdi();
        try {
            $count = $pdf->setSourceFile($input);
            if ($count !== count($texts)) {
                throw new \InvalidArgumentException('OCR page count does not match source');
            }
            for ($page = 1; $page <= $count; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
                $text = $texts[$page - 1];
                if ($text === '') {
                    continue;
                }
                // Fixed-length Unicode lines bound PDF output and avoid losing long OCR text.
                $lines = [];
                foreach (explode("\n", $text) as $line) {
                    $parts = mb_str_split(str_replace("\t", '    ', $line), 100);
                    array_push($lines, ...($parts ?: ['']));
                }
                $height = (float) $size['height'];
                $fontSize = min(10.0, max(0.5, ($height - 20) / max(1, count($lines)) / 1.2));
                $pdf->useUnicodeFont($fontSize);
                $longest = max(array_map(fn (string $line): float => $pdf->GetStringWidth($line), $lines));
                if ($longest > (float) $size['width'] - 20) {
                    $pdf->useUnicodeFont(max(0.1, $fontSize * ((float) $size['width'] - 20) / $longest));
                }
                $pdf->saveState();
                $pdf->setTextRenderingMode(3);
                foreach ($lines as $i => $line) {
                    $pdf->Text(10, 10 + ($i + 1) * ($height - 20) / count($lines), $line);
                }
                $pdf->restoreState();
            }
            $pdf->Output('F', $output);
            return $count;
        } finally {
            $pdf->cleanUp(true);
        }
    }
}
