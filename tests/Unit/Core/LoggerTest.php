<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Database;
use App\Core\Logger;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDirectory;

/**
 * Loglarda şifre, token, SMTP bilgisi ve PDF içeriği bulunmamalı (spec §41).
 */
final class LoggerTest extends TestCase
{
    public function testSensitiveKeysAreRedactedAndRecordsHaveRequestId(): void
    {
        $dir = TempDirectory::create('logs');
        try {
            $logger = new Logger($dir, 'debug');
            $logger->error('İşlem başarısız', [
                'operation' => 'merge',
                'document_id' => 'abc',
                'password' => 'S3cret!',
                'smtp_password' => 'mailpass',
                'csrf_token' => 'tok',
                'api_key' => 'k-123',
                'content' => '%PDF-1.7 gizli içerik',
                'nested' => ['authorization' => 'Bearer x', 'status' => 'failed'],
            ]);

            $line = (string) file_get_contents((string) glob($dir . '/app-*.log')[0]);
            $record = json_decode($line, true);

            self::assertSame($logger->requestId(), $record['request_id']);
            self::assertSame('error', $record['level']);
            self::assertSame('merge', $record['context']['operation']);
            self::assertSame('failed', $record['context']['nested']['status']);
            foreach (['S3cret!', 'mailpass', '"tok"', 'k-123', '%PDF', 'Bearer'] as $secret) {
                self::assertStringNotContainsString($secret, $line);
            }
        } finally {
            TempDirectory::remove($dir);
        }
    }

    public function testExceptionTracesContainNoArguments(): void
    {
        $exception = null;
        try {
            (static function (string $password): void {
                // DB bağlantısı kurulamaz; şifre parametresi istisna izine girmemeli
                (new Database(['host' => '127.0.0.1', 'port' => 1, 'socket' => '', 'database' => 'x', 'username' => 'u', 'password' => $password, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']))->pdo();
            })('ÇokGizliŞifre');
        } catch (\Throwable $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        $described = json_encode(Logger::describeThrowable($exception), JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('ÇokGizliŞifre', (string) $described);
    }
}
