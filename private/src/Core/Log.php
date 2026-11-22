<?php
declare(strict_types=1);

namespace Evolve\Core;

/** File logger under private/logs. Never show stack traces to users. */
final class Log
{
    private static string $file = '';

    private static function file(): string
    {
        if (self::$file === '') {
            $dir = private_dir() . '/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            self::$file = $dir . '/app.log';
        }
        return self::$file;
    }

    public static function line(string $level, string $message): void
    {
        $rid = $_SERVER['HTTP_X_REQUEST_ID'] ?? substr(bin2hex(random_bytes(4)), 0, 8);
        $entry = sprintf(
            "%s %s [%s] %s %s %s\n",
            date('c'),
            $level,
            $rid,
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            $_SERVER['REQUEST_URI'] ?? '-',
            str_replace("\n", ' ', $message)
        );
        @file_put_contents(self::file(), $entry, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message): void
    {
        self::line('ERROR', $message);
    }

    public static function warning(string $message): void
    {
        self::line('WARN', $message);
    }

    public static function info(string $message): void
    {
        self::line('INFO', $message);
    }

    public static function exception(\Throwable $e): void
    {
        self::line('ERROR', get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}
