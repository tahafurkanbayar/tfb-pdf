<?php

declare(strict_types=1);

namespace App\Tools;

use App\Exceptions\ToolUnavailableException;

/**
 * Harici program çalıştırma (Ghostscript, LibreOffice, Tesseract).
 *
 * - Komut DİZİ olarak verilir ve kabuk (shell) kullanılmaz: argümanlar yorumlanmaz, enjeksiyon yok.
 * - Çıktılar boru (pipe) yerine geçici dosyalara yazılır: Windows'ta stream_select boru desteklemez;
 *   bu yöntem hem Linux (cPanel) hem Windows (yerel geliştirme) üzerinde aynı çalışır.
 * - Zaman aşımında süreç sonlandırılır.
 */
class ProcessRunner
{
    public static function available(): bool
    {
        if (!function_exists('proc_open') || !function_exists('proc_get_status')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('proc_open', $disabled, true);
    }

    /**
     * @param list<string> $command
     * @param array<string, string>|null $env
     */
    public function run(array $command, int $timeoutSeconds = 60, ?string $cwd = null, ?array $env = null): CommandResult
    {
        if (!self::available()) {
            throw new ToolUnavailableException('proc_open is disabled');
        }

        $stdoutFile = tempnam(sys_get_temp_dir(), 'tfbo');
        $stderrFile = tempnam(sys_get_temp_dir(), 'tfbe');
        if ($stdoutFile === false || $stderrFile === false) {
            throw new ToolUnavailableException('Cannot create temporary files for process output');
        }

        $descriptors = [
            0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
            1 => ['file', $stdoutFile, 'w'],
            2 => ['file', $stderrFile, 'w'],
        ];

        $started = microtime(true);
        $process = @proc_open($command, $descriptors, $pipes, $cwd, $env, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            @unlink($stdoutFile);
            @unlink($stderrFile);
            throw new ToolUnavailableException('Cannot start process: ' . basename($command[0] ?? ''));
        }

        $timedOut = false;
        $exitCode = -1;
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = (int) $status['exitcode'];
                break;
            }
            if (microtime(true) - $started > $timeoutSeconds) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
            usleep(50_000);
        }
        $closeCode = proc_close($process);
        if ($exitCode === -1 && !$timedOut) {
            $exitCode = $closeCode;
        }

        $stdout = (string) @file_get_contents($stdoutFile, false, null, 0, 65536);
        $stderr = (string) @file_get_contents($stderrFile, false, null, 0, 65536);
        @unlink($stdoutFile);
        @unlink($stderrFile);

        return new CommandResult($exitCode, $stdout, $stderr, $timedOut, (int) round((microtime(true) - $started) * 1000));
    }
}
