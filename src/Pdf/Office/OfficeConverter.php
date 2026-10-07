<?php

declare(strict_types=1);

namespace App\Pdf\Office;

use App\Exceptions\ProcessingException;
use App\Exceptions\ToolUnavailableException;
use App\Pdf\PdfInspector;
use App\Tools\ProcessRunner;

/**
 * Office → PDF (DOC, DOCX, XLS, XLSX, PPT, PPTX) — LibreOffice headless (spec §12).
 *
 * Her dönüşüm kendi geçici profil dizinini kullanır (paylaşımlı hostingde ev dizini yazılamayabilir,
 * eşzamanlı dönüşümler profil kilidine takılmasın). Makrolar çalıştırılmaz: yeni profilde makro güvenliği
 * varsayılan olarak yüksektir; ayrıca yükleme doğrulaması makro içeren OOXML paketlerini reddeder.
 */
final class OfficeConverter
{
    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly ?string $soffice,
        private readonly int $timeout,
    ) {
    }

    public function available(): bool
    {
        return $this->soffice !== null && ProcessRunner::available();
    }

    /**
     * @return list<string>
     */
    public function command(string $input, string $outDir, string $profileDir): array
    {
        $profileUrl = 'file:///' . ltrim(str_replace('\\', '/', $profileDir), '/');

        return [
            (string) $this->soffice,
            '-env:UserInstallation=' . $profileUrl,
            '--headless', '--invisible', '--norestore', '--nologo', '--nodefault', '--nolockcheck',
            '--convert-to', 'pdf',
            '--outdir', $outDir,
            $input,
        ];
    }

    /**
     * @return int Çıktı sayfa sayısı
     */
    public function convert(string $source, string $extension, string $output, string $workDir): int
    {
        if (!$this->available()) {
            throw new ToolUnavailableException('LibreOffice not available', 'errors.tool_unavailable');
        }

        // LibreOffice türü uzantıdan da belirler: depodaki "original.<ext>" sabit adlı kopya ile çalışılır
        $input = $workDir . '/document.' . $extension;
        $outDir = $workDir . '/out';
        $profile = $workDir . '/profile';
        if (!@copy($source, $input) || !@mkdir($outDir) || !@mkdir($profile)) {
            throw new ProcessingException('Cannot prepare conversion directory');
        }

        $env = ['HOME' => $workDir, 'TMPDIR' => $workDir, 'PATH' => (string) getenv('PATH')];
        if (PHP_OS_FAMILY === 'Windows') {
            $env += ['SystemRoot' => (string) getenv('SystemRoot'), 'TEMP' => $workDir, 'TMP' => $workDir];
        }

        $result = $this->runner->run($this->command($input, $outDir, $profile), $this->timeout, $workDir, $env);
        $produced = $outDir . '/document.pdf';
        if (!$result->successful() || !is_file($produced) || filesize($produced) === 0) {
            throw new ProcessingException('LibreOffice conversion failed (exit ' . $result->exitCode . ($result->timedOut ? ', timeout' : '') . ')', 'office.conversion_failed');
        }

        // Çıktı gerçekten geçerli bir PDF mi
        $pages = (new PdfInspector())->pageCount($produced);
        if (!@rename($produced, $output)) {
            throw new ProcessingException('Cannot move converted file');
        }

        return $pages;
    }
}
