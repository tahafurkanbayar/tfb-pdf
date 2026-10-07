<?php

declare(strict_types=1);

namespace App\Services\Upload;

use App\Exceptions\ValidationException;
use App\Http\UploadedFile;
use App\Pdf\PdfInspector;
use App\Support\FilenameSanitizer;
use App\Support\Size;

/**
 * Yüklenen dosyaların güvenli doğrulaması (spec §14).
 *
 * Kontroller: yükleme hata kodu → gerçek HTTP yüklemesi mi → boyut → uzantı (yalnızca ön eleme)
 * → gerçek içerik imzası (magic bytes) → MIME (fileinfo varsa) → yapı (PDF ayrıştırma / Office paket yapısı)
 * → sayfa sayısı. Kullanıcının verdiği uzantı ve MIME tipine güvenilmez.
 */
final class UploadValidator
{
    public const OFFICE_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    private const PDF_MIMES = ['application/pdf', 'application/x-pdf', 'application/acrobat'];

    private const OLE_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    private const ZIP_SIGNATURE = "PK\x03\x04";

    /** OOXML paket içinde bulunması gereken ana bölüm */
    private const OOXML_PARTS = ['docx' => 'word/', 'xlsx' => 'xl/', 'pptx' => 'ppt/'];

    /** OLE (eski Office) dizinindeki akış adları, UTF-16LE */
    private const OLE_STREAMS = ['doc' => ['WordDocument'], 'xls' => ['Workbook', 'Book'], 'ppt' => ['PowerPoint Document']];

    public function __construct(
        private readonly PdfInspector $inspector,
        private readonly int $maxUploadSize,
        private readonly int $maxPages,
    ) {
    }

    /**
     * Etkin üst sınır: uygulama ayarı ile php.ini (upload_max_filesize, post_max_size) en küçüğü.
     */
    public function effectiveMaxSize(): int
    {
        return min($this->maxUploadSize, Size::fromIni('upload_max_filesize'), Size::fromIni('post_max_size'));
    }

    public function validatePdf(UploadedFile $file): ValidatedUpload
    {
        $this->checkTransfer($file);

        $ext = FilenameSanitizer::extension($file->clientName);
        if ($ext !== 'pdf') {
            throw new ValidationException('Extension not allowed: ' . $ext, 'upload.unsupported_type', ['types' => 'PDF']);
        }

        $head = $this->readHead($file->tmpPath, 1024);
        if (!str_contains($head, '%PDF-')) {
            throw new ValidationException('Missing PDF signature', 'upload.invalid_pdf');
        }

        $mime = $this->detectMime($file->tmpPath);
        if ($mime !== null && !in_array($mime, self::PDF_MIMES, true) && $mime !== 'application/octet-stream') {
            throw new ValidationException('MIME mismatch: ' . $mime, 'upload.invalid_pdf');
        }

        $info = $this->inspector->inspect($file->tmpPath, $this->maxPages);

        return new ValidatedUpload(
            $file,
            FilenameSanitizer::clean($file->clientName, 'document.pdf'),
            'pdf',
            'pdf',
            'application/pdf',
            $file->size,
            $info,
        );
    }

    public function validateOffice(UploadedFile $file): ValidatedUpload
    {
        $this->checkTransfer($file);

        $ext = FilenameSanitizer::extension($file->clientName);
        if (!in_array($ext, self::OFFICE_EXTENSIONS, true)) {
            throw new ValidationException('Extension not allowed: ' . $ext, 'upload.unsupported_type', [
                'types' => strtoupper(implode(', ', self::OFFICE_EXTENSIONS)),
            ]);
        }

        $head = $this->readHead($file->tmpPath, 8);
        $isOoxml = isset(self::OOXML_PARTS[$ext]);

        if ($isOoxml) {
            if (!str_starts_with($head, self::ZIP_SIGNATURE) || !$this->ooxmlMatches($file->tmpPath, self::OOXML_PARTS[$ext])) {
                throw new ValidationException('Content does not match ' . $ext, 'upload.invalid_file');
            }
        } elseif (!str_starts_with($head, self::OLE_SIGNATURE) || !$this->oleMatches($file->tmpPath, self::OLE_STREAMS[$ext])) {
            throw new ValidationException('Content does not match ' . $ext, 'upload.invalid_file');
        }

        return new ValidatedUpload(
            $file,
            FilenameSanitizer::clean($file->clientName, 'document.' . $ext),
            'office',
            $ext,
            self::officeMime($ext),
            $file->size,
            null,
        );
    }

    public static function officeMime(string $ext): string
    {
        return match ($ext) {
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/octet-stream',
        };
    }

    private function checkTransfer(UploadedFile $file): void
    {
        match ($file->error) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE => throw new ValidationException('Upload exceeds upload_max_filesize', 'upload.server_limit'),
            UPLOAD_ERR_FORM_SIZE => throw new ValidationException('Upload exceeds MAX_FILE_SIZE', 'upload.file_too_large', ['max' => Size::format($this->effectiveMaxSize())]),
            UPLOAD_ERR_PARTIAL => throw new ValidationException('Partial upload', 'upload.partial'),
            UPLOAD_ERR_NO_FILE => throw new ValidationException('No file', 'upload.no_file'),
            default => throw new ValidationException('Upload error code ' . $file->error, 'upload.invalid_file'),
        };

        if (!$file->isValidUpload()) {
            throw new ValidationException('Not a valid uploaded file', 'upload.invalid_file');
        }

        $size = (int) @filesize($file->tmpPath);
        if ($size === 0) {
            throw new ValidationException('Empty file', 'upload.empty_file');
        }
        if ($size > $this->effectiveMaxSize()) {
            throw new ValidationException('File too large: ' . $size, 'upload.file_too_large', ['max' => Size::format($this->effectiveMaxSize())]);
        }
    }

    private function readHead(string $path, int $length): string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new ValidationException('Cannot read upload', 'upload.invalid_file');
        }
        $data = (string) fread($handle, $length);
        fclose($handle);

        return $data;
    }

    private function detectMime(string $path): ?string
    {
        if (!function_exists('finfo_open')) {
            return null; // fileinfo yoksa imza ve yapı kontrolleri yine uygulanır
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime === false ? null : $mime;
    }

    /**
     * OOXML: ZIP merkezi dizininde [Content_Types].xml ve beklenen ana bölüm (word/, xl/, ppt/) olmalı.
     * ZipArchive gerekmeden, dosya sonundaki merkezi dizin taranır.
     */
    private function ooxmlMatches(string $path, string $part): bool
    {
        $tail = $this->readTail($path, 1024 * 1024);

        return str_contains($tail, '[Content_Types].xml')
            && str_contains($tail, $part)
            // Makro içeren paketler (docm/xlsm/pptm farklı uzantıyla gönderilmiş) reddedilir
            && !str_contains($tail, 'vbaProject.bin');
    }

    /**
     * @param list<string> $streams
     */
    private function oleMatches(string $path, array $streams): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $needles = array_map(static fn (string $s): string => (string) mb_convert_encoding($s, 'UTF-16LE', 'UTF-8'), $streams);
        $carry = '';
        $found = false;
        while (!$found && !feof($handle)) {
            $chunk = $carry . (string) fread($handle, 262144);
            foreach ($needles as $needle) {
                if (str_contains($chunk, $needle)) {
                    $found = true;
                    break;
                }
            }
            $carry = substr($chunk, -64);
        }
        fclose($handle);

        return $found;
    }

    private function readTail(string $path, int $length): string
    {
        $size = (int) filesize($path);
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        fseek($handle, max(0, $size - $length));
        $data = (string) fread($handle, $length);
        fclose($handle);

        return $data;
    }
}
