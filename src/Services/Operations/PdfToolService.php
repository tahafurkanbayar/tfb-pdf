<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Domain\Document;
use App\Domain\DocumentVersion;
use App\Exceptions\ValidationException;
use App\Pdf\Compression\Compressor;
use App\Pdf\PageRangeParser;
use App\Pdf\PdfInspector;
use App\Pdf\PdfService;
use App\Pdf\WarningCollector;
use App\Services\DocumentService;
use App\Support\FilenameSanitizer;

/**
 * Araçların iş kuralları: girdi doğrulama + OperationService ile çalıştırma.
 * HTTP'den bağımsızdır (gelecekte REST API / CLI aynı metotları çağırabilir).
 */
final class PdfToolService
{
    public function __construct(
        private readonly OperationService $operations,
        private readonly DocumentService $documents,
        private readonly PdfService $pdf,
        private readonly PdfInspector $inspector,
        private readonly int $maxFilesPerOperation,
        private readonly ?Compressor $compressor = null,
    ) {
    }

    /**
     * Sahibin belge + sürüm seçimini çözer; sürüm verilmezse en son PDF sürümü.
     *
     * @return array{Document, DocumentVersion}
     */
    public function resolveInput(string $ownerHash, string $documentId, ?int $versionNumber = null): array
    {
        $document = $this->documents->get($documentId, $ownerHash);
        $version = $versionNumber === null
            ? $this->documents->latestPdfVersion($document)
            : $this->documents->version($document, $versionNumber);

        if ($version === null || !$version->isPdf()) {
            throw new ValidationException('Input is not a PDF version', 'operations.input_not_pdf');
        }

        return [$document, $version];
    }

    /**
     * @param list<array{document: string, version?: int|null}> $items Sıra = birleştirme sırası
     */
    public function merge(string $ownerHash, array $items): OperationResult
    {
        $count = count($items);
        if ($count < 2) {
            throw new ValidationException('Merge needs at least two files', 'operations.merge_min_files');
        }
        if ($count > $this->maxFilesPerOperation) {
            throw new ValidationException('Too many files', 'upload.too_many_files', ['max' => $this->maxFilesPerOperation]);
        }

        $inputs = array_map(
            fn (array $item): array => $this->resolveInput($ownerHash, (string) ($item['document'] ?? ''), isset($item['version']) ? (int) $item['version'] : null),
            $items
        );

        $firstName = FilenameSanitizer::basename($inputs[0][0]->originalName);

        return $this->operations->run(
            'merge',
            $ownerHash,
            $inputs,
            ['files' => $count],
            function (string $tmp, array $paths): ProcessResult {
                $output = $tmp . '/merged.pdf';
                $pages = $this->pdf->merge($paths, $output);

                return new ProcessResult(
                    [new OperationOutput($output, $pages)],
                    'fpdi',
                    ['pages' => $pages, 'files' => count($paths)],
                    $this->rebuildWarnings($paths)
                );
            },
            newDocumentName: $firstName . '-merged.pdf'
        );
    }

    public const SPLIT_MODES = ['each', 'ranges', 'extract'];

    /**
     * Bölme (spec §12):
     *   each    → her sayfa ayrı sürüm
     *   ranges  → her aralık ayrı sürüm ("1-3", "5", "8-12")
     *   extract → seçilen sayfalar tek sürümde (yazılan sırayla)
     * Sürüm etiketi sayfa aralığıdır.
     */
    public function split(string $ownerHash, string $documentId, ?int $versionNumber, string $mode, string $ranges = ''): OperationResult
    {
        if (!in_array($mode, self::SPLIT_MODES, true)) {
            throw new ValidationException('Invalid split mode', 'errors.validation');
        }

        [$document, $version] = $this->resolveInput($ownerHash, $documentId, $versionNumber);
        $total = (int) $version->pageCount;

        // Geçersiz aralıklar işlem başlamadan yakalanır (operation kaydı oluşmaz)
        $parsed = $mode === 'each'
            ? array_map(static fn (int $p): array => [$p, $p], range(1, max(1, $total)))
            : PageRangeParser::parse($ranges, $total);

        if ($mode === 'each' && $total < 2) {
            throw new ValidationException('Single page document', 'split.single_page');
        }

        return $this->operations->run(
            'split',
            $ownerHash,
            [[$document, $version]],
            ['mode' => $mode, 'ranges' => array_map([PageRangeParser::class, 'label'], $parsed)],
            function (string $tmp, array $paths) use ($mode, $parsed): ProcessResult {
                $outputs = [];
                if ($mode === 'extract') {
                    $pages = array_merge(...array_map([PageRangeParser::class, 'pages'], $parsed));
                    $file = $tmp . '/extract.pdf';
                    $this->pdf->extract($paths[0], $file, $pages);
                    $outputs[] = new OperationOutput($file, count($pages), implode(', ', array_map([PageRangeParser::class, 'label'], $parsed)));
                } else {
                    foreach ($parsed as $i => $range) {
                        $file = $tmp . '/part-' . ($i + 1) . '.pdf';
                        $pages = PageRangeParser::pages($range);
                        $this->pdf->extract($paths[0], $file, $pages);
                        $outputs[] = new OperationOutput($file, count($pages), PageRangeParser::label($range));
                    }
                }

                return new ProcessResult($outputs, 'fpdi', ['mode' => $mode, 'files' => count($outputs)], $this->rebuildWarnings($paths));
            }
        );
    }

    /**
     * Sayfa sıralama ve kaldırma. $order yeni sıradaki kaynak sayfa numaralarıdır; listede olmayan
     * sayfalar yeni sürümde yer almaz. Sıra değişmemişse yeni sürüm oluşturulmaz.
     *
     * @param list<int> $order
     */
    public function reorder(string $ownerHash, string $documentId, ?int $versionNumber, array $order): OperationResult
    {
        [$document, $version] = $this->resolveInput($ownerHash, $documentId, $versionNumber);
        $total = (int) $version->pageCount;
        $order = array_values(array_map('intval', $order));

        if ($order === []) {
            throw new ValidationException('All pages removed', 'reorder.all_removed');
        }
        if (count($order) !== count(array_unique($order)) || min($order) < 1 || max($order) > $total) {
            throw new ValidationException('Invalid page order', 'reorder.invalid_order');
        }

        $unchanged = $order === range(1, $total);

        return $this->operations->run(
            'reorder',
            $ownerHash,
            [[$document, $version]],
            ['order' => $order, 'removed' => array_values(array_diff(range(1, $total), $order))],
            function (string $tmp, array $paths) use ($order, $unchanged, $total): ProcessResult {
                if ($unchanged) {
                    return new ProcessResult([], 'fpdi', ['pages' => $total], changed: false);
                }
                $file = $tmp . '/reordered.pdf';
                $this->pdf->extract($paths[0], $file, $order);

                return new ProcessResult(
                    [new OperationOutput($file, count($order))],
                    'fpdi',
                    ['pages' => count($order), 'removed' => $total - count($order)],
                    $this->rebuildWarnings($paths)
                );
            }
        );
    }

    /**
     * Sayfa döndürme. $rotations: sayfa no => saat yönünde derece (90/180/270; 0 = değişmez).
     * Döndürme sayfanın /Rotate değeriyle yapılır: içerik yeniden çizilmez, kalite kaybı olmaz.
     *
     * @param array<int|string, int|string> $rotations
     */
    public function rotate(string $ownerHash, string $documentId, ?int $versionNumber, array $rotations): OperationResult
    {
        [$document, $version] = $this->resolveInput($ownerHash, $documentId, $versionNumber);
        $total = (int) $version->pageCount;

        $normalized = [];
        foreach ($rotations as $page => $degrees) {
            $page = (int) $page;
            if ($page < 1 || $page > $total || !is_numeric($degrees)) {
                throw new ValidationException('Invalid page rotation', 'operations.page_out_of_range', ['page' => $page, 'total' => $total]);
            }
            $deg = PdfService::normalizeRotation((int) $degrees);
            if ($deg !== 0) {
                $normalized[$page] = $deg;
            }
        }
        ksort($normalized);

        return $this->operations->run(
            'rotate',
            $ownerHash,
            [[$document, $version]],
            ['rotations' => $normalized],
            function (string $tmp, array $paths) use ($normalized, $total): ProcessResult {
                if ($normalized === []) {
                    return new ProcessResult([], 'fpdi', ['pages' => $total], changed: false);
                }
                $file = $tmp . '/rotated.pdf';
                $this->pdf->extract($paths[0], $file, range(1, $total), $normalized);

                return new ProcessResult(
                    [new OperationOutput($file, $total)],
                    'fpdi',
                    ['pages' => $total, 'rotated_pages' => count($normalized)],
                    $this->rebuildWarnings($paths)
                );
            }
        );
    }

    /** Sıkıştırmanın "gerçek" sayılması için gereken en az kazanç */
    public const MIN_COMPRESSION_SAVING = 0.03;

    /**
     * Sıkıştırma. Dosya en az %3 küçülmezse yeni sürüm oluşturulmaz ve bu açıkça bildirilir.
     */
    public function compress(string $ownerHash, string $documentId, ?int $versionNumber, string $level): OperationResult
    {
        if (!in_array($level, Compressor::LEVELS, true)) {
            throw new ValidationException('Invalid level', 'compress.invalid_level');
        }
        if ($this->compressor === null) {
            throw new \LogicException('Compressor not configured');
        }

        [$document, $version] = $this->resolveInput($ownerHash, $documentId, $versionNumber);

        return $this->operations->run(
            'compress',
            $ownerHash,
            [[$document, $version]],
            ['level' => $level],
            function (string $tmp, array $paths) use ($level): ProcessResult {
                $file = $tmp . '/compressed.pdf';
                $info = $this->compressor->compress($paths[0], $file, $level);
                $before = $info['size_before'];
                $after = $info['size_after'];
                $saved = $before > 0 ? 1 - $after / $before : 0.0;

                $meta = [
                    'level' => $level,
                    'size_before' => $before,
                    'size_after' => $after,
                    'saved_percent' => (int) round(max(0, $saved) * 100),
                ] + $info['details'];

                if ($saved < self::MIN_COMPRESSION_SAVING) {
                    return new ProcessResult([], $info['engine'], $meta, changed: false);
                }

                $source = $this->inspector->inspect($paths[0]);
                $warnings = $info['engine'] === 'php'
                    ? $this->rebuildWarnings($paths)
                    : array_values(array_filter([
                        $source->hasSignatures ? 'warnings.signature_invalidated' : null,
                        $source->hasForms ? 'warnings.forms_removed' : null,
                        'warnings.metadata_changed',
                    ]));
                if (($info['details']['images_optimized'] ?? 0) > 0 || $info['engine'] === 'ghostscript') {
                    $warnings[] = 'warnings.images_recompressed';
                }

                return new ProcessResult([new OperationOutput($file, $info['pages'])], $info['engine'], $meta, $warnings);
            }
        );
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private function rebuildWarnings(array $paths): array
    {
        return WarningCollector::forPageRebuild(array_map(fn (string $p) => $this->inspector->inspect($p), $paths));
    }
}
