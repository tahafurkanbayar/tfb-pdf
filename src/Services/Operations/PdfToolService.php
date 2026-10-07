<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Domain\Document;
use App\Domain\DocumentVersion;
use App\Exceptions\ValidationException;
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

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private function rebuildWarnings(array $paths): array
    {
        return WarningCollector::forPageRebuild(array_map(fn (string $p) => $this->inspector->inspect($p), $paths));
    }
}
