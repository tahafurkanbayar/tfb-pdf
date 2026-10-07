<?php

declare(strict_types=1);

namespace App\Services\Upload;

use App\Http\UploadedFile;
use App\Pdf\PdfInfo;

/**
 * Doğrulamadan geçmiş yükleme. Tür, kullanıcının verdiği uzantıdan değil içerikten belirlenir.
 */
final class ValidatedUpload
{
    public function __construct(
        public readonly UploadedFile $file,
        public readonly string $originalName,
        public readonly string $kind,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly ?PdfInfo $pdf,
    ) {
    }

    public function isPdf(): bool
    {
        return $this->kind === 'pdf';
    }
}
