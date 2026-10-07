<?php

declare(strict_types=1);

namespace Tests\Unit\Tools;

use App\Tools\ProcessRunner;
use App\Tools\ToolDetector;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

final class ProcessRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!ProcessRunner::available()) {
            self::markTestSkipped('proc_open kapalı');
        }
    }

    public function testRunsCommandAndCapturesOutput(): void
    {
        $result = (new ProcessRunner())->run([PHP_BINARY, '-r', 'echo "çıktı"; fwrite(STDERR, "hata"); exit(3);']);

        self::assertSame('çıktı', $result->stdout);
        self::assertSame('hata', $result->stderr);
        self::assertSame(3, $result->exitCode);
        self::assertFalse($result->successful());
    }

    public function testArgumentsAreNotInterpretedByAShell(): void
    {
        $payload = 'a; echo HACKED & echo HACKED | x "$(whoami)" `id`';
        $result = (new ProcessRunner())->run([PHP_BINARY, '-r', 'echo $argv[1];', $payload]);

        self::assertTrue($result->successful());
        self::assertSame($payload, $result->stdout);
    }

    public function testTimeoutTerminatesProcess(): void
    {
        $started = microtime(true);
        $result = (new ProcessRunner())->run([PHP_BINARY, '-r', 'sleep(10);'], 1);

        self::assertTrue($result->timedOut);
        self::assertFalse($result->successful());
        self::assertLessThan(5, microtime(true) - $started);
    }

    public function testDetectorUsesConfiguredPathVerifiesItAndCaches(): void
    {
        $dir = TempDirectory::create('detect');
        try {
            // Gerçek bir çalıştırılabilir dosya (php) "ghostscript" olarak yapılandırılırsa sürüm tespit edilir;
            // tespit mekanizmasını gerçek araç olmadan doğrular.
            $detector = new ToolDetector(new ProcessRunner(), [
                ToolDetector::GHOSTSCRIPT => PHP_BINARY,
                ToolDetector::LIBREOFFICE => 'disabled',
                ToolDetector::TESSERACT => $dir . '/yok/tesseract',
            ], $dir . '/tools.json');

            self::assertSame(PHP_BINARY, $detector->path(ToolDetector::GHOSTSCRIPT));
            self::assertSame(PHP_VERSION, $detector->version(ToolDetector::GHOSTSCRIPT));
            self::assertNull($detector->path(ToolDetector::LIBREOFFICE));
            self::assertNull($detector->path(ToolDetector::TESSERACT));
            self::assertFileExists($dir . '/tools.json');

            // Önbellekten okuma: yeni nesne program çalıştırmadan aynı sonucu verir
            $cached = new ToolDetector(new class () extends ProcessRunner {
                public function run(array $command, int $timeoutSeconds = 60, ?string $cwd = null, ?array $env = null): \App\Tools\CommandResult
                {
                    throw new \LogicException('Önbellek kullanılmalıydı');
                }
            }, [
                ToolDetector::GHOSTSCRIPT => PHP_BINARY,
                ToolDetector::LIBREOFFICE => 'disabled',
                ToolDetector::TESSERACT => $dir . '/yok/tesseract',
            ], $dir . '/tools.json');
            self::assertSame(PHP_BINARY, $cached->path(ToolDetector::GHOSTSCRIPT));
        } finally {
            TempDirectory::remove($dir);
        }
    }
}
