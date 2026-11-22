<?php
declare(strict_types=1);

namespace Evolve\Core;

/** Security headers + rate limiter + audit log (MySQL-backed, no Redis on shared hosting). */
final class Http
{
    public static function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /** Fixed-window limiter in SQL. Throws ValidationError when exceeded. */
    public static function rateLimit(string $bucket, int $max, int $windowSec): void
    {
        $sqlite = Db::isSqlite();
        $key = substr($bucket . ':' . self::clientIp(), 0, 120);
        $now = time();
        $pdo = db();
        if ($sqlite) {
            $pdo->prepare('INSERT INTO rate_limits (rkey, hits, window_start) VALUES (?, 1, ?)
                ON CONFLICT(rkey) DO UPDATE SET
                  hits = CASE WHEN window_start < ? THEN 1 ELSE hits + 1 END,
                  window_start = CASE WHEN window_start < ? THEN ? ELSE window_start END')
                ->execute([$key, $now, $now - $windowSec, $now - $windowSec, $now]);
        } else {
            $pdo->prepare('INSERT INTO rate_limits (rkey, hits, window_start) VALUES (?, 1, ?)
                ON DUPLICATE KEY UPDATE
                  hits = IF(window_start < ?, 1, hits + 1),
                  window_start = IF(window_start < ?, ?, window_start)')
                ->execute([$key, $now, $now - $windowSec, $now - $windowSec, $now]);
        }
        $stmt = $pdo->prepare('SELECT hits FROM rate_limits WHERE rkey = ?');
        $stmt->execute([$key]);
        if ((int) $stmt->fetchColumn() > $max) {
            throw new ValidationError('Too many attempts. Please wait a few minutes and try again.');
        }
        if (random_int(1, 50) === 1) {
            $pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$now - 86400]);
        }
    }

    public static function audit(string $event, string $actor = ''): void
    {
        try {
            db()->prepare('INSERT INTO audit_log (event, actor, ip) VALUES (?, ?, ?)')
                ->execute([substr($event, 0, 60), substr($actor, 0, 190), self::clientIp()]);
        } catch (\Throwable $e) {
            Log::exception($e);
        }
    }
}
