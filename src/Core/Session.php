<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PHP oturumu üzerinde güvenli ayarlar ve küçük bir API.
 * Testlerde ve CLI'de gerçek oturum başlatılmaz; değerler bellekte tutulur.
 */
final class Session
{
    public const COOKIE_NAME = 'tfb_session';

    /** @var array<string, mixed> */
    private array $memory = [];

    private bool $native = false;

    public function __construct(
        private readonly string $savePath,
        private readonly bool $secure,
        private readonly string $cookiePath = '/',
    ) {
    }

    public function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            $this->native = session_status() === PHP_SESSION_ACTIVE;

            return;
        }

        if (is_dir($this->savePath) && is_writable($this->savePath)) {
            session_save_path($this->savePath);
            // Debian/cPanel varsayılanı GC'yi kapatabilir; kendi dizinimiz için açık tut
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', '7200');

        session_name(self::COOKIE_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->cookiePath,
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        $this->native = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->native ? ($_SESSION[$key] ?? $default) : ($this->memory[$key] ?? $default);
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->native) {
            $_SESSION[$key] = $value;
        } else {
            $this->memory[$key] = $value;
        }
    }

    public function forget(string $key): void
    {
        if ($this->native) {
            unset($_SESSION[$key]);
        } else {
            unset($this->memory[$key]);
        }
    }

    /**
     * Tek seferlik mesaj (bir sonraki istekte okunup silinir).
     */
    public function flash(string $key, mixed $value): void
    {
        $flash = (array) $this->get('_flash', []);
        $flash[$key] = $value;
        $this->set('_flash', $flash);
    }

    public function pullFlash(string $key): mixed
    {
        $flash = (array) $this->get('_flash', []);
        $value = $flash[$key] ?? null;
        unset($flash[$key]);
        $this->set('_flash', $flash);

        return $value;
    }

    public function regenerate(): void
    {
        if ($this->native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
