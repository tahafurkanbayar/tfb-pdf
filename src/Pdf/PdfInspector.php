<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Exceptions\ProcessingException;
use App\Exceptions\ValidationException;
use App\Pdf\Parser\ExtendedPdfParser;
use App\Pdf\Parser\HybridReader;
use App\Pdf\Parser\XrefStreamReader;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfBoolean;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\DataStructure\Rectangle;
use setasign\Fpdi\PdfReader\PdfReader;

/**
 * PDF'i dosyayı belleğe almadan ayrıştırır: geçerlilik, sayfa sayısı ve sayfa boyutları,
 * şifreleme, form / imza / ek dosya gibi işlemde etkilenecek özellikler.
 */
final class PdfInspector
{
    public function inspect(string $path, int $maxPages = PHP_INT_MAX): PdfInfo
    {
        $stream = null;
        try {
            $stream = StreamReader::createByFile($path);
            $parser = new ExtendedPdfParser($stream);
            $reader = new PdfReader($parser);

            $pageCount = $reader->getPageCount();
            if ($pageCount < 1) {
                throw new ValidationException('PDF has no pages', 'upload.invalid_pdf');
            }
            if ($pageCount > $maxPages) {
                throw new ValidationException('Too many pages: ' . $pageCount, 'upload.too_many_pages', ['max' => $maxPages]);
            }

            $pages = [];
            for ($i = 1; $i <= $pageCount; $i++) {
                $page = $reader->getPage($i);
                $box = $page->getBoundary();
                $pages[] = [
                    'width' => $box instanceof Rectangle ? round((float) $box->getWidth(), 2) : 0.0,
                    'height' => $box instanceof Rectangle ? round((float) $box->getHeight(), 2) : 0.0,
                    'rotation' => (int) $page->getRotation(),
                ];
            }

            $catalog = $parser->getCatalog();
            $resolve = static fn (string $key): PdfType => PdfType::resolve(PdfDictionary::get($catalog, $key), $parser);

            $acroForm = $resolve('AcroForm');
            $hasForms = false;
            $hasSignatures = false;
            if ($acroForm instanceof PdfDictionary) {
                $fields = PdfType::resolve(PdfDictionary::get($acroForm, 'Fields'), $parser);
                $hasForms = $fields instanceof PdfArray && count($fields->value) > 0;
                $sigFlags = PdfType::resolve(PdfDictionary::get($acroForm, 'SigFlags'), $parser);
                $hasSignatures = $sigFlags instanceof PdfNumeric && ((int) $sigFlags->value & 1) === 1;
            }

            $names = $resolve('Names');
            $hasEmbedded = $names instanceof PdfDictionary && !PdfDictionary::get($names, 'EmbeddedFiles') instanceof PdfNull;
            $hasJs = ($names instanceof PdfDictionary && !PdfDictionary::get($names, 'JavaScript') instanceof PdfNull)
                || !PdfDictionary::get($catalog, 'OpenAction') instanceof PdfNull;

            $markInfo = $resolve('MarkInfo');
            $marked = $markInfo instanceof PdfDictionary ? PdfType::resolve(PdfDictionary::get($markInfo, 'Marked'), $parser) : null;

            $trailer = $parser->getCrossReference()->getTrailer();
            $compressed = false;
            foreach ($parser->getCrossReference()->getReaders() as $xrefReader) {
                $compressed = $compressed || $xrefReader instanceof XrefStreamReader || $xrefReader instanceof HybridReader;
            }

            return new PdfInfo(
                pageCount: $pageCount,
                version: $parser->getPdfVersion()[0] . '.' . $parser->getPdfVersion()[1],
                pages: $pages,
                hasForms: $hasForms,
                hasSignatures: $hasSignatures,
                hasEmbeddedFiles: $hasEmbedded,
                hasOutlines: !$resolve('Outlines') instanceof PdfNull,
                hasJavaScript: $hasJs,
                isTagged: !$resolve('StructTreeRoot') instanceof PdfNull || ($marked instanceof PdfBoolean && $marked->value),
                hasMetadata: !$resolve('Metadata') instanceof PdfNull || !PdfDictionary::get($trailer, 'Info') instanceof PdfNull,
                usesCompressedXref: $compressed,
            );
        } catch (CrossReferenceException $e) {
            if ($e->getCode() === CrossReferenceException::ENCRYPTED) {
                throw new ValidationException('Encrypted PDF', 'upload.encrypted_pdf', previous: $e);
            }
            throw new ValidationException('Invalid PDF: ' . $e->getMessage(), 'upload.invalid_pdf', previous: $e);
        } catch (PdfParserException | \setasign\Fpdi\PdfParser\Type\PdfTypeException | \setasign\Fpdi\PdfReader\PdfReaderException $e) {
            throw new ValidationException('Invalid PDF: ' . $e->getMessage(), 'upload.invalid_pdf', previous: $e);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\ErrorException | \ValueError | \TypeError | \UnexpectedValueException $e) {
            // Bozuk dosyalar ayrıştırıcıda beklenmedik hatalara yol açabilir
            throw new ValidationException('Invalid PDF: ' . $e->getMessage(), 'upload.invalid_pdf', previous: $e);
        } finally {
            $stream?->cleanUp();
        }
    }

    /**
     * Doğrulanmış bir dosyanın işlem sırasında açılamaması (ör. bozuk sürüm) işlem hatasıdır.
     */
    public function pageCount(string $path): int
    {
        try {
            return $this->inspect($path)->pageCount;
        } catch (ValidationException $e) {
            throw new ProcessingException('Cannot read PDF: ' . $e->getMessage(), 'upload.invalid_pdf', previous: $e);
        }
    }
}
