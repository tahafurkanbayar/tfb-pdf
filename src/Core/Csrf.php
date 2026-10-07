<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Oturum başına CSRF token (synchronizer token pattern).
 * Formlarda gizli "_token" alanı, fetch isteklerinde "X-CSRF-Token" başlığı.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function validate(?string $submitted): bool
    {
        $token = $this->session->get(self::SESSION_KEY);

        return is_string($token) && is_string($submitted) && $submitted !== '' && hash_equals($token, $submitted);
    }
}
