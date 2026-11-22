<?php
declare(strict_types=1);

/**
 * Applies private/migrations/NNN_name.sql in order, once each (tracked in
 * schema_migrations). The SQL files are written for MySQL; when the config
 * selects the SQLite dev driver, statements are translated with a strict,
 * token-based translator (no overlapping regex passes).
 */

function sqlite_translate(string $sql): string
{
    // Strip full-line comments first so they never glue onto a statement.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $out = [];
    // Statements in these files never contain a semicolon inside strings,
    // so a plain split on ; at end of line is safe.
    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\n|$)/', $sql) ?: []));
    foreach ($statements as $stmt) {
        if ($stmt === '') {
            continue;
        }
        $out[] = sqlite_translate_statement($stmt);
    }
    return implode(";\n\n", $out) . ";\n";
}

function sqlite_translate_statement(string $stmt): string
{
    $s = $stmt;

    // Engine/charset suffix.
    $s = preg_replace('/\s*ENGINE=InnoDB\s+DEFAULT\s+CHARSET=utf8mb4/i', '', $s);

    // INT AUTO_INCREMENT PRIMARY KEY -> INTEGER PRIMARY KEY AUTOINCREMENT
    $s = str_replace('INT AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $s);

    // Types: one pass, exact token replacement. DECIMAL variants first.
    $s = str_replace(['DECIMAL(10,2)', 'DECIMAL(12,2)', 'DECIMAL(10,0)', 'DECIMAL(12,0)'], 'REAL', $s);
    $types = [
        'TINYINT' => 'INTEGER',
        'INT' => 'INTEGER',
        'VARCHAR' => 'TEXT',
        'TIMESTAMP' => 'TEXT',
        'DATE' => 'TEXT',
    ];
    $s = preg_replace_callback(
        '/\b(TINYINT|INT|VARCHAR|TIMESTAMP|DATE)\b(\(\d+(?:,\d+)?\))?/',
        static function (array $m) use ($types): string {
            return $types[$m[1]];
        },
        $s
    );

    // ENUM(...) -> TEXT.
    $s = preg_replace('/ENUM\([^)]*\)/', 'TEXT', $s);

    // ON UPDATE CURRENT_TIMESTAMP -> gone.
    $s = str_replace(' ON UPDATE CURRENT_TIMESTAMP', '', $s);

    // Key/constraint lines.
    $lines = preg_split('/\n/', $s);
    $kept = [];
    foreach ($lines as $line) {
        $t = ltrim($line);
        if (preg_match('/^(KEY|INDEX)\s+[a-z_]+\s*\([^)]*\),?\s*$/i', $t)) {
            continue; // secondary indexes: SQLite wants CREATE INDEX, not inline KEY
        }
        if (preg_match('/^UNIQUE KEY\s+[a-z_]+\s*\(([^)]+)\),?\s*$/i', $t, $m)) {
            $kept[] = '  UNIQUE(' . $m[1] . '),';
            continue;
        }
        if (preg_match('/^CONSTRAINT\s+([a-z_]+)\s+FOREIGN KEY\s+\(([^)]+)\)\s+REFERENCES\s+([a-z_]+)\s*\(([^)]+)\)(\s+ON DELETE\s+(CASCADE|SET NULL|RESTRICT))?/i', $t, $m)) {
            $fk = '  FOREIGN KEY (' . $m[2] . ') REFERENCES ' . $m[3] . '(' . $m[4] . ')';
            if (!empty($m[6])) {
                $fk .= ' ON DELETE ' . strtoupper($m[6]);
            }
            $kept[] = $fk . ',';
            continue;
        }
        $kept[] = $line;
    }
    $s = implode("\n", $kept);

    // Trailing comma before the table's closing brace.
    $s = preg_replace('/,(\s*\))/', '$1', $s);

    return $s;
}

function run_migrations(): array
{
    $pdo = db();
    $sqlite = \Evolve\Core\Db::isSqlite();
    $tableSql = $sqlite
        ? 'CREATE TABLE IF NOT EXISTS schema_migrations (name TEXT NOT NULL PRIMARY KEY, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)'
        : 'CREATE TABLE IF NOT EXISTS schema_migrations (
        name VARCHAR(120) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $pdo->exec($tableSql);

    $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
    $files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
    sort($files);
    $applied = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $done, true)) {
            continue;
        }
        $raw = (string) file_get_contents($file);
        // Strip full-line comments so they never swallow a statement.
        $raw = preg_replace('/^\s*--.*$/m', '', $raw);
        $sql = $sqlite ? sqlite_translate($raw) : $raw;
        $parts = array_filter(array_map('trim', preg_split('/;\s*(?:\n|$)/', $sql) ?: []));
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $pdo->exec($part);
        }
        $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
        $applied[] = $name;
    }
    return $applied;
}
