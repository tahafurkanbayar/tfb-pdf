<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\Response;

/**
 * Hesap sistemi olmayan ilk sürümde belge sahipliği (bkz. docs/DECISIONS.md).
 *
 * Tarayıcıya uzun ömürlü, HttpOnly bir rastgele token verilir; veritabanında yalnızca
 * HMAC'i (owner_hash) tutulur. İleride kullanıcı hesapları eklendiğinde bu sınıf
 * oturum açmış kullanıcıyı da temsil edecek şekilde genişletilebilir.
 */
final class OwnerContext
{
    public const COOKIE = 'tfb_owner';

    private const LIFETIME_DAYS = 365;

    private ?string $token;

    private bool $issued = false;

    public function __construct(
        private readonly Hmac $hmac,
        ?string $cookieValue,
        private readonly bool $secureCookie,
        private readonly string $cookiePath,
    ) {
        $this->token = ($cookieValue !== null && preg_match('/^[a-f0-9]{64}$/', $cookieValue)) ? $cookieValue : null;
    }

    public function exists(): bool
    {
        return $this->token !== null;
    }

    /**
     * Mevcut sahip kimliği; tarayıcı henüz kimlik almadıysa null.
     */
    public function hash(): ?string
    {
        return $this->token === null ? null : $this->hmac->hash('owner', $this->token);
    }

    /**
     * Kimlik yoksa oluşturur (ilk yüklemede).
     */
    public function ensure(): string
    {
        if ($this->token === null) {
            $this->token = Hmac::randomToken(32);
            $this->issued = true;
        }

        return (string) $this->hash();
    }

    /**
     * Yeni kimlik verildiyse veya sayfa ziyaretinde süre uzatılacaksa cookie'yi yanıta ekler.
     */
    public function applyTo(Response $response, bool $refresh = false): void
    {
        if ($this->token === null || (!$this->issued && !$refresh)) {
            return;
        }

        $response->withCookie(self::COOKIE, $this->token, [
            'expires' => time() + self::LIFETIME_DAYS * 86400,
            'path' => $this->cookiePath,
            'secure' => $this->secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
