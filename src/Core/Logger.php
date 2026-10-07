<?php

declare(strict_types=1);

namespace App\Core;

/**
 * JSON satırları halinde günlük dosyası: storage/logs/app-YYYY-MM-DD.log
 *
 * Asla loglanmaz (spec §41): PDF içeriği, şifreler, API anahtarları, SMTP bilgileri,
 * kimlik doğrulama token'ları. Hassas görünen anahtarlar otomatik olarak maskelenir.
 */
final class Logger
{
    private const LEVELS = ['debug' => 100, 'info' => 200, 'warning' => 300, 'error' => 400, 'critical' => 500];

    private const SENSITIVE_KEY_PATTERN = '/pass|secret|token|key|auth|cookie|credential|smtp|signature_data|content|body/i';

    private string $requestId;

    public function __construct(
        private readonly string $directory,
        private readonly string $minLevel = 'info',
    ) {
        $this->requestId = bin2hex(random_bytes(6));
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->minLevel] ?? 200)) {
            return;
        }

        $record = [
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'request_id' => $this->requestId,
            'message' => self::truncate($message, 2000),
        ] + ($context === [] ? [] : ['context' => self::sanitize($context)]);

        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false) {
            return;
        }

        $file = $this->directory . '/app-' . gmdate('Y-m-d') . '.log';
        // Log yazılamazsa uygulama durmamalı
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<mixed> $context
     * @return array<mixed>
     */
    public static function sanitize(array $context, int $depth = 0): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) && !in_array($key, ['error_key', 'message_key'], true)) {
                $clean[$key] = '[redacted]';
                continue;
            }

            $clean[$key] = match (true) {
                $value instanceof \Throwable => self::describeThrowable($value),
                is_array($value) => $depth < 3 ? self::sanitize($value, $depth + 1) : '[array]',
                is_object($value) => '[' . $value::class . ']',
                is_string($value) => self::truncate($value, 500),
                default => $value,
            };
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    public static function describeThrowable(\Throwable $e): array
    {
        $info = [
            'class' => $e::class,
            'message' => self::truncate($e->getMessage(), 1000),
            'file' => basename($e->getFile()) . ':' . $e->getLine(),
            // Argümansız iz: parametre değerleri (şifre vb.) loga girmez
            'trace' => array_slice(array_map(
                static fn (array $f): string => (isset($f['class']) ? $f['class'] . $f['type'] : '') . $f['function'] . (isset($f['file']) ? ' @' . basename($f['file']) . ':' . ($f['line'] ?? 0) : ''),
                $e->getTrace()
            ), 0, 15),
        ];
        if ($e->getPrevious() !== null) {
            $info['previous'] = $e->getPrevious()::class . ': ' . self::truncate($e->getPrevious()->getMessage(), 500);
        }

        return $info;
    }

    private static function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) . '…' : $value;
    }
}
