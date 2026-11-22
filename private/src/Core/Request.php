<?php
declare(strict_types=1);

namespace Evolve\Core;

/** Request path/method helpers (subdirectory-aware). */
final class Request
{
    /** App base path ('' when installed at domain root, '/sub' otherwise). */
    public static function base(): string
    {
        // Under Apache/.htaccess SCRIPT_NAME is /index.php (or /sub/index.php)
        // and dirname() gives the install folder. Under php -S with a router
        // script SCRIPT_NAME mirrors the request path, so fall back to ''.
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!str_ends_with($script, '.php')) {
            return '';
        }
        $base = rtrim(dirname($script), '/');
        return $base === '/' ? '' : $base;
    }

    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::base();
        if ($base !== '' && strncmp($uri, $base, strlen($base)) === 0) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** Honeypot: a hidden field bots fill; real users leave it empty. */
    public static function honeypotTripped(): bool
    {
        return trim((string) ($_POST['companyUrl'] ?? '')) !== '';
    }
}
