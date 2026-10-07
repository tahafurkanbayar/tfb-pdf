<?php

declare(strict_types=1);

namespace App\Tools;

/**
 * Sunucunun destekleyebildiği özellikler (araç + PHP eklentisi birleşimi).
 */
class Capabilities
{
    public function __construct(private readonly ToolDetector $detector)
    {
    }

    public function ghostscript(): bool
    {
        return $this->detector->has(ToolDetector::GHOSTSCRIPT);
    }

    /** Office → PDF: LibreOffice headless */
    public function office(): bool
    {
        return $this->detector->has(ToolDetector::LIBREOFFICE);
    }

    /** OCR: Tesseract + sayfaları görüntüye çevirecek bir araç (Ghostscript veya pdftoppm) */
    public function ocr(): bool
    {
        return $this->detector->has(ToolDetector::TESSERACT)
            && ($this->ghostscript() || $this->detector->has(ToolDetector::PDFTOPPM));
    }

    public function zip(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    public function gd(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'ghostscript' => $this->ghostscript(),
            'office' => $this->office(),
            'ocr' => $this->ocr(),
            'zip' => $this->zip(),
            'gd' => $this->gd(),
            'process' => ProcessRunner::available(),
        ];
    }

    public function detector(): ToolDetector
    {
        return $this->detector;
    }
}
