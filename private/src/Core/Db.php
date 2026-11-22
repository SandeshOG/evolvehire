<?php
declare(strict_types=1);

namespace Evolve\Core;

/**
 * Single PDO connection shared per request.
 * Production: MySQL (utf8mb4, prepared statements, no emulation).
 * Sandbox dev: SQLite (same schema, converted from the MySQL migrations)
 * when config 'db_driver' = 'sqlite'.
 */
final class Db
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }
        $c = cfg();
        if (($c['db_driver'] ?? '') === 'sqlite') {
            $file = $c['db_sqlite_path'] ?? (private_dir() . '/storage/dev.sqlite');
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            $pdo = new \PDO('sqlite:' . $file, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec("PRAGMA busy_timeout = 5000");
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $c['db_host'],
                (int) ($c['db_port'] ?? 3306),
                $c['db_name']
            );
            $pdo = new \PDO($dsn, $c['db_user'], $c['db_pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        self::$pdo = $pdo;
        return $pdo;
    }

    public static function isSqlite(): bool
    {
        return (cfg()['db_driver'] ?? '') === 'sqlite';
    }

    /** Run a closure inside a transaction; commit on success, roll back on error. */
    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
