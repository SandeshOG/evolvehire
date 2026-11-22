<?php
declare(strict_types=1);

namespace Evolve\Core;

/** Session with HttpOnly + SameSite=Lax (Secure when HTTPS). */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION['user'] ?? null;
    }
}
