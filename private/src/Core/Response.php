<?php
declare(strict_types=1);

namespace Evolve\Core;

/** HTML page/response helpers, including noindex and JSON-LD emission. */
final class Response
{
    public static function noindex(): void
    {
        header('X-Robots-Tag: noindex, nofollow');
    }

    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function jsonld(array $data): string
    {
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}
