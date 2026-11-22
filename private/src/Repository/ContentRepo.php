<?php
declare(strict_types=1);

namespace Evolve\Repository;

/** Content catalogues: industries, functions, services, insights, case studies, companies, settings. */
final class ContentRepo
{
    public static function published(string $table, int $limit = 0): array
    {
        $allowed = ['industries', 'functions', 'services', 'insights', 'case_studies', 'companies'];
        if (!in_array($table, $allowed, true)) {
            return [];
        }
        $order = in_array($table, ['industries', 'functions', 'services'], true)
            ? 'sort_order, id'
            : ($table === 'companies' ? 'name' : 'published_at DESC, id DESC');
        $sql = "SELECT * FROM $table WHERE published = 1 ORDER BY $order";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return db()->query($sql)->fetchAll();
    }

    public static function bySlug(string $table, string $slug): ?array
    {
        $allowed = ['industries', 'functions', 'services', 'insights', 'case_studies', 'companies'];
        if (!in_array($table, $allowed, true)) {
            return null;
        }
        $stmt = db()->prepare("SELECT * FROM $table WHERE slug = ? AND published = 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function adminList(string $table): array
    {
        $allowed = ['industries', 'functions', 'services', 'insights', 'case_studies', 'companies'];
        if (!in_array($table, $allowed, true)) {
            return [];
        }
        $order = in_array($table, ['industries', 'functions', 'services'], true)
            ? 'sort_order, id'
            : 'id DESC';
        return db()->query("SELECT * FROM $table ORDER BY $order")->fetchAll();
    }

    public static function save(string $table, array $row): void
    {
        $fields = match ($table) {
            'industries', 'functions' => ['name', 'slug', 'summary', 'sort_order', 'published'],
            'services' => ['name', 'slug', 'summary', 'body', 'sort_order', 'published'],
            'insights', 'case_studies' => ['title', 'slug', 'excerpt', 'body', 'industry', 'published', 'published_at'],
            'companies' => ['name', 'slug', 'summary', 'website', 'published'],
            default => throw new \InvalidArgumentException('Unknown collection.'),
        };
        if (in_array('title', $fields, true)) {
            $fields = array_diff($fields, ['title']);
            array_unshift($fields, 'title');
        }
        $id = (int) ($row['id'] ?? 0);
        $published = ($row['published'] ?? '') === '1' ? 1 : 0;
        $values = [];
        $cols = [];
        $marks = [];
        foreach ($fields as $f) {
            if ($f === 'published') {
                $values[] = $published;
            } elseif ($f === 'published_at') {
                $values[] = $published ? ($row['published_at'] ?: date('Y-m-d H:i:s')) : null;
            } elseif ($f === 'sort_order') {
                $values[] = (int) ($row['sort_order'] ?? 0);
            } else {
                $values[] = (string) ($row[$f] ?? '');
            }
            $cols[] = $f;
            $marks[] = '?';
        }
        if ($id > 0) {
            $set = implode(', ', array_map(fn ($c) => "$c = ?", $cols));
            $stmt = db()->prepare("UPDATE $table SET $set WHERE id = ?");
            $stmt->execute([...$values, $id]);
        } else {
            $stmt = db()->prepare("INSERT INTO $table (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $marks) . ')');
            $stmt->execute($values);
        }
    }

    public static function delete(string $table, int $id): void
    {
        $allowed = ['industries', 'functions', 'services', 'insights', 'case_studies', 'companies'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Unknown collection.');
        }
        db()->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    }

    public static function setting(string $key, string $default = ''): string
    {
        try {
            $stmt = db()->prepare('SELECT svalue FROM settings WHERE skey = ?');
            $stmt->execute([$key]);
            $v = $stmt->fetchColumn();
            return $v === false || $v === null ? $default : (string) $v;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function saveSetting(string $key, string $value): void
    {
        if (!preg_match('/^[a-z0-9_]{2,40}$/', $key)) {
            throw new \Evolve\Core\ValidationError('Setting names use lower-case letters, numbers and underscores.');
        }
        if (\Evolve\Core\Db::isSqlite()) {
            db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)
                ON CONFLICT(skey) DO UPDATE SET svalue = excluded.svalue')
                ->execute([$key, $value]);
        } else {
            db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
                ->execute([$key, $value]);
        }
    }
}
