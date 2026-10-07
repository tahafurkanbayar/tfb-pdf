<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Pdf\Parser\ExtendedPdfParser;
use App\Pdf\Parser\PdfSerializer;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfStream;

/**
 * Klasik bir PDF'i PDF 1.5 "sıkıştırılmış" yapıya çevirir: stream olmayan nesneler bir
 * object stream'e (/ObjStm) paketlenir, xref tablosu yerine FlateDecode + PNG Up predictor'lü
 * cross-reference stream yazılır. Ücretsiz FPDI bu dosyaları okuyamaz; parser uzantımızı test eder.
 */
final class CompressedPdfWriter
{
    public static function convert(string $source, string $target, bool $hybrid = false): string
    {
        $stream = StreamReader::createByFile($source);
        $parser = new ExtendedPdfParser($stream);
        $xref = $parser->getCrossReference();
        $trailer = $xref->getTrailer();
        $size = (int) PdfDictionary::get($trailer, 'Size')->value;

        $plain = [];   // stream nesneleri: no => serileştirilmiş
        $packed = [];  // object stream'e girecekler: no => serileştirilmiş
        for ($n = 1; $n < $size; $n++) {
            try {
                $object = $xref->getIndirectObject($n);
            } catch (CrossReferenceException) {
                continue;
            }
            if ($object->value instanceof PdfStream) {
                $plain[$n] = PdfSerializer::stream($object->value->value, (string) $object->value->getStream());
            } else {
                $packed[$n] = PdfSerializer::value($object->value);
            }
        }
        $stream->cleanUp();

        $objStmNumber = $size;
        $xrefNumber = $size + 1;
        $newSize = $size + 2;

        $out = "%PDF-1.5\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($plain as $n => $body) {
            $offsets[$n] = strlen($out);
            $out .= $n . " 0 obj\n" . $body . "\nendobj\n";
        }

        // Object stream
        $header = '';
        $bodies = '';
        $index = [];
        $i = 0;
        foreach ($packed as $n => $body) {
            $header .= $n . ' ' . strlen($bodies) . ' ';
            $bodies .= $body . "\n";
            $index[$n] = $i++;
        }
        $objStmData = gzcompress($header . $bodies);
        $offsets[$objStmNumber] = strlen($out);
        $out .= sprintf(
            "%d 0 obj\n<</Type /ObjStm /N %d /First %d /Filter /FlateDecode /Length %d>>\nstream\n%s\nendstream\nendobj\n",
            $objStmNumber,
            count($packed),
            strlen($header),
            strlen($objStmData),
            $objStmData
        );

        // Cross-reference stream: W [1 4 2], PNG Up predictor (Columns 7)
        $offsets[$xrefNumber] = strlen($out);
        $rows = [];
        for ($n = 0; $n < $newSize; $n++) {
            if (isset($index[$n])) {
                $rows[] = pack('CNn', 2, $objStmNumber, $index[$n]);
            } elseif (isset($offsets[$n])) {
                $rows[] = pack('CNn', 1, $offsets[$n], 0);
            } else {
                $rows[] = pack('CNn', 0, 0, $n === 0 ? 65535 : 0);
            }
        }
        $predicted = '';
        $previous = str_repeat("\0", 7);
        foreach ($rows as $row) {
            $predicted .= "\x02";
            for ($b = 0; $b < 7; $b++) {
                $predicted .= chr((ord($row[$b]) - ord($previous[$b])) & 0xFF);
            }
            $previous = $row;
        }
        $xrefData = gzcompress($predicted);

        $root = PdfDictionary::get($trailer, 'Root');
        $info = PdfDictionary::get($trailer, 'Info');
        $trailerEntries = '/Root ' . PdfSerializer::value($root)
            . ($info instanceof PdfIndirectObjectReference ? ' /Info ' . PdfSerializer::value($info) : '');

        $xrefObject = sprintf(
            "%d 0 obj\n<</Type /XRef /Size %d /W [1 4 2] /Index [0 %d] %s /Filter /FlateDecode /DecodeParms <</Predictor 12 /Columns 7>> /Length %d>>\nstream\n%s\nendstream\nendobj\n",
            $xrefNumber,
            $newSize,
            $newSize,
            $trailerEntries,
            strlen($xrefData),
            $xrefData
        );

        if (!$hybrid) {
            $out .= $xrefObject;
            $out .= "startxref\n" . $offsets[$xrefNumber] . "\n%%EOF\n";
        } else {
            // Hybrid: klasik tabloda yalnızca düz nesneler; sıkıştırılmışlar /XRefStm ile bulunur
            $out .= $xrefObject;
            $tableOffset = strlen($out);
            $out .= "xref\n0 " . $newSize . "\n";
            for ($n = 0; $n < $newSize; $n++) {
                $out .= isset($offsets[$n]) && !isset($index[$n])
                    ? sprintf("%010d 00000 n \n", $offsets[$n])
                    : sprintf("%010d %05d f \n", 0, $n === 0 ? 65535 : 0);
            }
            $out .= "trailer\n<</Size " . $newSize . ' ' . $trailerEntries . ' /XRefStm ' . $offsets[$xrefNumber] . ">>\n";
            $out .= "startxref\n" . $tableOffset . "\n%%EOF\n";
        }

        file_put_contents($target, $out);

        return $target;
    }
}
