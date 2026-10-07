<?php

declare(strict_types=1);

namespace App\Services\Install;

use App\Core\Session;

/**
 * Web kurulum sayfasının erişim koruması (SSH'siz cPanel kurulumu).
 *
 * - .env içindeki INSTALL_KEY boşsa sayfa tamamen kapalıdır (404).
 * - Anahtar en az 16 karakter olmalıdır; kısa anahtarla giriş yapılamaz.
 * - Yanlış denemeler istemci başına dosyada sayılır (veritabanı henüz hazır olmayabilir):
 *   15 dakikada 5 hatalı deneme sonrası giriş geçici olarak kilitlenir.
 * - Oturumda anahtarın kendisi değil özeti tutulur; anahtar değişince oturum geçersizleşir.
 */
final class InstallGuard
{
    public const MIN_KEY_LENGTH = 16;
    public const MAX_FAILURES = 5;
    public const LOCK_SECONDS = 900;

    private const SESSION_KEY = 'install_auth';

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $installKey,
        private readonly string $attemptsFile,
    ) {
    }

    public function enabled(): bool
    {
        return $this->installKey !== '';
    }

    public function keyTooShort(): bool
    {
        return strlen($this->installKey) < self::MIN_KEY_LENGTH;
    }

    public function authorized(Session $session): bool
    {
        $stored = $session->get(self::SESSION_KEY);

        return $this->enabled() && !$this->keyTooShort() && is_string($stored) && hash_equals($this->fingerprint(), $stored);
    }

    /**
     * @return 'ok'|'invalid'|'locked'|'weak'
     */
    public function attempt(Session $session, string $client, #[\SensitiveParameter] string $submitted, ?int $now = null): string
    {
        $now ??= time();
        if (!$this->enabled() || $this->keyTooShort()) {
            return 'weak';
        }
        if ($this->failures($client, $now) >= self::MAX_FAILURES) {
            return 'locked';
        }
        if (!hash_equals($this->installKey, $submitted)) {
            $this->recordFailure($client, $now);

            return 'invalid';
        }

        $this->clearFailures($client);
        $session->regenerate();
        $session->set(self::SESSION_KEY, $this->fingerprint());

        return 'ok';
    }

    public function logout(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
        $session->regenerate();
    }

    private function fingerprint(): string
    {
        return hash('sha256', 'tfb-install|' . $this->installKey);
    }

    private function bucket(string $client): string
    {
        // İstemci adresi düz metin saklanmaz
        return hash('sha256', 'tfb-install-client|' . $this->installKey . '|' . $client);
    }

    public function failures(string $client, ?int $now = null): int
    {
        $now ??= time();
        $entry = $this->read()[$this->bucket($client)] ?? null;
        if (!is_array($entry) || ($entry['since'] ?? 0) + self::LOCK_SECONDS <= $now) {
            return 0;
        }

        return (int) ($entry['count'] ?? 0);
    }

    private function recordFailure(string $client, int $now): void
    {
        $this->update(function (array $data) use ($client, $now): array {
            // Süresi geçmiş kayıtlar atılır, dosya büyümez
            $data = array_filter($data, static fn (mixed $e): bool => is_array($e) && ($e['since'] ?? 0) + self::LOCK_SECONDS > $now);
            $bucket = $this->bucket($client);
            $entry = $data[$bucket] ?? ['count' => 0, 'since' => $now];
            $entry['count']++;
            $data[$bucket] = $entry;

            return $data;
        });
    }

    private function clearFailures(string $client): void
    {
        $this->update(function (array $data) use ($client): array {
            unset($data[$this->bucket($client)]);

            return $data;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $raw = is_file($this->attemptsFile) ? @file_get_contents($this->attemptsFile) : false;
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : [];
    }

    /**
     * Kilitli oku-değiştir-yaz. Dizin yazılamıyorsa sayaç tutulamaz; giriş yine yalnızca doğru anahtarla olur.
     *
     * @param \Closure(array<string, mixed>): array<string, mixed> $change
     */
    private function update(\Closure $change): void
    {
        $dir = dirname($this->attemptsFile);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return;
        }
        $handle = @fopen($this->attemptsFile, 'c+');
        if ($handle === false) {
            return;
        }
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $data = $change(is_array($data) ? $data : []);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($data));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
