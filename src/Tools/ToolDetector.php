<?php

declare(strict_types=1);

namespace App\Tools;

/**
 * Opsiyonel sunucu araçlarının tespiti (spec §10). Araç yoksa ilgili özellik kapanır, uygulama çalışmaya devam eder.
 *
 * Sıra: yapılandırma (.env) → PATH ve bilinen kurulum yerleri → "--version" ile gerçekten çalıştığının doğrulanması.
 * Sonuç storage/cache/tools.json içinde önbelleğe alınır (her istekte program çalıştırılmaz).
 */
class ToolDetector
{
    public const GHOSTSCRIPT = 'ghostscript';
    public const LIBREOFFICE = 'libreoffice';
    public const TESSERACT = 'tesseract';
    public const PDFTOPPM = 'pdftoppm';

    /** @var array<string, list<string>> */
    private const CANDIDATES = [
        self::GHOSTSCRIPT => ['gs', 'gswin64c', 'gswin32c', '/usr/bin/gs', '/usr/local/bin/gs'],
        self::LIBREOFFICE => [
            'soffice', 'libreoffice', '/usr/bin/soffice', '/usr/bin/libreoffice', '/usr/lib/libreoffice/program/soffice',
            '/opt/libreoffice/program/soffice', 'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
        ],
        self::TESSERACT => ['tesseract', '/usr/bin/tesseract', '/usr/local/bin/tesseract', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'],
        self::PDFTOPPM => ['pdftoppm', '/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm'],
    ];

    /** @var array<string, list<string>> */
    private const VERSION_ARGS = [
        self::GHOSTSCRIPT => ['--version'],
        self::LIBREOFFICE => ['--version'],
        self::TESSERACT => ['--version'],
        self::PDFTOPPM => ['-v'],
    ];

    /** @var array<string, array{path: ?string, version: ?string}>|null */
    private ?array $results = null;

    /**
     * @param array<string, string> $configured Araç => .env değeri ('' otomatik, 'disabled', mutlak yol)
     */
    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly array $configured,
        private readonly string $cacheFile,
        private readonly int $cacheTtl = 3600,
    ) {
    }

    public function path(string $tool): ?string
    {
        return $this->all()[$tool]['path'] ?? null;
    }

    public function version(string $tool): ?string
    {
        return $this->all()[$tool]['version'] ?? null;
    }

    public function has(string $tool): bool
    {
        return $this->path($tool) !== null;
    }

    /**
     * @return array<string, array{path: ?string, version: ?string}>
     */
    public function all(bool $refresh = false): array
    {
        if ($this->results !== null && !$refresh) {
            return $this->results;
        }

        if (!$refresh && is_file($this->cacheFile) && filemtime($this->cacheFile) > time() - $this->cacheTtl) {
            $cached = json_decode((string) @file_get_contents($this->cacheFile), true);
            if (is_array($cached) && ($cached['config'] ?? null) === $this->configHash()) {
                return $this->results = $cached['tools'];
            }
        }

        $results = [];
        foreach (array_keys(self::CANDIDATES) as $tool) {
            $results[$tool] = $this->detect($tool);
        }

        @file_put_contents($this->cacheFile, (string) json_encode(['config' => $this->configHash(), 'tools' => $results]), LOCK_EX);

        return $this->results = $results;
    }

    /**
     * @return array{path: ?string, version: ?string}
     */
    private function detect(string $tool): array
    {
        $none = ['path' => null, 'version' => null];
        if (!ProcessRunner::available()) {
            return $none;
        }

        $setting = trim($this->configured[$tool] ?? '');
        if (strtolower($setting) === 'disabled') {
            return $none;
        }

        $candidates = $setting !== '' ? [$setting] : self::CANDIDATES[$tool];
        foreach ($candidates as $candidate) {
            $path = $this->resolveExecutable($candidate);
            if ($path === null) {
                continue;
            }
            try {
                $result = $this->runner->run([$path, ...self::VERSION_ARGS[$tool]], 20);
            } catch (\Throwable) {
                continue;
            }
            $output = trim($result->stdout . "\n" . $result->stderr);
            if ($result->successful() || ($tool === self::PDFTOPPM && $output !== '')) {
                return ['path' => $path, 'version' => self::firstVersion($output)];
            }
        }

        return $none;
    }

    private function resolveExecutable(string $candidate): ?string
    {
        // Mutlak yol
        if (str_contains($candidate, '/') || str_contains($candidate, '\\')) {
            return @is_file($candidate) ? $candidate : null;
        }

        // PATH araması (which/where kullanmadan)
        $extensions = PHP_OS_FAMILY === 'Windows' ? ['.exe', '.com', ''] : [''];
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            $dir = trim($dir, " \"");
            if ($dir === '') {
                continue;
            }
            foreach ($extensions as $ext) {
                $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $candidate . $ext;
                // open_basedir kısıtlı sunucularda uyarı vermesin
                if (@is_file($file) && (PHP_OS_FAMILY === 'Windows' || @is_executable($file))) {
                    return $file;
                }
            }
        }

        return null;
    }

    private static function firstVersion(string $output): ?string
    {
        return preg_match('/(\d+\.\d+(?:\.\d+)*)/', $output, $m) ? $m[1] : null;
    }

    private function configHash(): string
    {
        $configured = $this->configured;
        ksort($configured);

        return hash('sha256', (string) json_encode([$configured, PHP_OS_FAMILY, getenv('PATH')]));
    }
}
