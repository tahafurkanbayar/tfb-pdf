<?php

declare(strict_types=1);

namespace App\Pdf\Parser;

use setasign\Fpdi\PdfParser\CrossReference\CrossReference;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfToken;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;

/**
 * FPDI CrossReference'ın xref stream ve object stream destekleyen sürümü.
 * Klasik xref tablolu dosyalarda FPDI'nin kendi okuyucuları aynen kullanılır.
 */
final class ExtendedCrossReference extends CrossReference
{
    /** @var array<int, array<int, \setasign\Fpdi\PdfParser\Type\PdfType>> object stream no => [index => değer] */
    private array $objectStreamCache = [];

    protected function readXref($offset)
    {
        $reader = parent::readXref($offset);

        // Hybrid dosya: klasik tablo + /XRefStm
        if (!$reader instanceof XrefStreamReader) {
            $xrefStm = PdfDictionary::get($reader->getTrailer(), 'XRefStm');
            if ($xrefStm instanceof PdfNumeric && $xrefStm->value > 0) {
                try {
                    $stream = $this->readXrefStreamAt((int) $xrefStm->value + $this->fileHeaderOffset);
                    $reader = new HybridReader($reader, $stream);
                } catch (CrossReferenceException | PdfTypeException) {
                    // Bozuk /XRefStm: yalnızca tablo kullanılır
                }
            }
        }

        return $reader;
    }

    protected function initReaderInstance($initValue)
    {
        if ($initValue instanceof PdfIndirectObject && $initValue->value instanceof PdfStream) {
            $type = PdfDictionary::get($initValue->value->value, 'Type');
            if ($type instanceof PdfName && $type->value === 'XRef') {
                $this->checkForEncryption($initValue->value->value);

                return new XrefStreamReader($initValue);
            }
        }

        return parent::initReaderInstance($initValue);
    }

    /**
     * Okuyucuları yeniden eskiye doğru tarar; ilk tanımlayan bölüm kazanır.
     */
    public function getIndirectObject($objectNumber)
    {
        foreach ($this->getReaders() as $reader) {
            if ($reader instanceof XrefStreamReader || $reader instanceof HybridReader) {
                $location = $reader->getCompressedLocation($objectNumber);
                if ($location !== null) {
                    return $this->readCompressedObject($objectNumber, $location[0], $location[1]);
                }
            }

            $offset = $reader->getOffsetFor($objectNumber);
            if ($offset !== false) {
                return $this->readObjectAt($objectNumber, (int) $offset);
            }

            if ($reader instanceof XrefStreamReader && $reader->defines($objectNumber)) {
                break; // Bu bölümde "boş" olarak işaretli
            }
        }

        throw new CrossReferenceException(
            sprintf('Object (id:%s) not found.', $objectNumber),
            CrossReferenceException::OBJECT_NOT_FOUND
        );
    }

    private function readObjectAt(int $objectNumber, int $offset): PdfIndirectObject
    {
        $parser = $this->parser;
        $parser->getTokenizer()->clearStack();
        $parser->getStreamReader()->reset($offset + $this->fileHeaderOffset);

        try {
            /** @var PdfIndirectObject $object */
            $object = $parser->readValue(null, PdfIndirectObject::class);
        } catch (PdfTypeException $e) {
            throw new CrossReferenceException(
                sprintf('Object (id:%s) not found at location (%s).', $objectNumber, $offset),
                CrossReferenceException::OBJECT_NOT_FOUND,
                $e
            );
        }

        if ($object->objectNumber !== $objectNumber) {
            throw new CrossReferenceException(
                sprintf('Wrong object found, got %s while %s was expected.', $object->objectNumber, $objectNumber),
                CrossReferenceException::OBJECT_NOT_FOUND
            );
        }

        return $object;
    }

    private function readXrefStreamAt(int $offset): XrefStreamReader
    {
        $this->parser->getStreamReader()->reset($offset);
        $this->parser->getTokenizer()->clearStack();
        $value = $this->parser->readValue();
        if (!$value instanceof PdfIndirectObject) {
            throw new CrossReferenceException('Invalid /XRefStm offset.', CrossReferenceException::INVALID_DATA);
        }

        return new XrefStreamReader($value);
    }

    /**
     * Object stream (/Type /ObjStm) içindeki nesneyi okur (ISO 32000-1 §7.5.7).
     */
    private function readCompressedObject(int $objectNumber, int $streamNumber, int $index): PdfIndirectObject
    {
        if (!isset($this->objectStreamCache[$streamNumber])) {
            $this->objectStreamCache[$streamNumber] = $this->parseObjectStream($streamNumber);
        }

        $objects = $this->objectStreamCache[$streamNumber];
        if (!isset($objects[$objectNumber])) {
            throw new CrossReferenceException(
                sprintf('Object (id:%s) not found in object stream %s.', $objectNumber, $streamNumber),
                CrossReferenceException::OBJECT_NOT_FOUND
            );
        }

        return PdfIndirectObject::create($objectNumber, 0, $objects[$objectNumber]);
    }

    /**
     * @return array<int, \setasign\Fpdi\PdfParser\Type\PdfType> nesne no => değer
     */
    private function parseObjectStream(int $streamNumber): array
    {
        $container = $this->getIndirectObject($streamNumber);
        $stream = PdfStream::ensure($container->value);

        $type = PdfDictionary::get($stream->value, 'Type');
        if (!$type instanceof PdfName || $type->value !== 'ObjStm') {
            throw new CrossReferenceException('Referenced object is not an object stream.', CrossReferenceException::INVALID_DATA);
        }

        $count = (int) PdfDictionary::get($stream->value, 'N', PdfNumeric::create(0))->value;
        $first = (int) PdfDictionary::get($stream->value, 'First', PdfNumeric::create(0))->value;
        $data = (string) $stream->getUnfilteredStream();

        // Başlık: "nesneNo ofset nesneNo ofset ..."
        $header = preg_split('/\s+/', trim(substr($data, 0, $first))) ?: [];
        if (count($header) < $count * 2) {
            throw new CrossReferenceException('Invalid object stream header.', CrossReferenceException::INVALID_DATA);
        }

        $parser = new PdfParser(StreamReader::createByString($data));
        $objects = [];
        for ($i = 0; $i < $count; $i++) {
            $number = (int) $header[$i * 2];
            $offset = (int) $header[$i * 2 + 1];

            $parser->getTokenizer()->clearStack();
            $parser->getStreamReader()->reset($first + $offset);
            $value = $parser->readValue();
            if ($value === false || $value instanceof PdfToken) {
                continue;
            }
            $objects[$number] = $value;
        }

        return $objects;
    }
}
