<?php
declare(strict_types=1);

namespace Evolve\Repository;

/** Jobs board: search, filters, facets, CRUD. All SQL parameterised. */
final class JobRepo
{
    /** Build the WHERE for public job search. Returns [sql, params, whereClauses]. */
    private static function searchWhere(array $f): array
    {
        $where = ["status = 'published' AND internal = 0 AND (expires_at IS NULL OR expires_at >= CURDATE())"];
        $params = [];
        $driver = \Evolve\Core\Db::isSqlite();
        if ($driver) {
            $where = ["status = 'published' AND internal = 0 AND (expires_at IS NULL OR expires_at >= date('now'))"];
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(title LIKE ? OR company LIKE ? OR location LIKE ?)';
            $like = '%' . $f['q'] . '%';
            $params = [$like, $like, $like];
        }
        if (($f['industry'] ?? '') !== '') {
            $where[] = 'industry = ?';
            $params[] = $f['industry'];
        }
        if (($f['function'] ?? '') !== '') {
            $where[] = 'function_name = ?';
            $params[] = $f['function'];
        }
        if (($f['location'] ?? '') !== '') {
            $where[] = 'location = ?';
            $params[] = $f['location'];
        }
        if (($f['experience'] ?? '') !== '') {
            $where[] = 'experience_label = ?';
            $params[] = $f['experience'];
        }
        if (($f['work_mode'] ?? '') !== '') {
            $where[] = 'work_mode = ?';
            $params[] = $f['work_mode'];
        }
        if (($f['employment_type'] ?? '') !== '') {
            $where[] = 'employment_type = ?';
            $params[] = $f['employment_type'];
        }
        if (($f['internal'] ?? '') === '1') {
            $where = ["status = 'published' AND internal = 1"];
        }
        return [implode(' AND ', $where), $params];
    }

    /** @return array{rows:array, total:int, pages:int} */
    public static function search(array $f, int $perPage = 10): array
    {
        [$whereSql, $params] = self::searchWhere($f);
        $driver = \Evolve\Core\Db::isSqlite();
        $countStmt = db()->prepare("SELECT COUNT(*) FROM jobs WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $orderBy = match ($f['sort'] ?? '') {
            'salary' => 'COALESCE(salary_max, salary_min, 0) DESC',
            'expiry' => 'expires_at IS NULL, expires_at ASC',
            default => 'published_at DESC',
        };

        $page = max(1, (int) ($f['page'] ?? 1));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT id, title, slug, company, industry, function_name, location, experience_label,
                salary_label, employment_type, work_mode, featured, urgent, expires_at
                FROM jobs WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public static function latest(int $limit = 6): array
    {
        $driver = \Evolve\Core\Db::isSqlite();
        $today = $driver ? "date('now')" : 'CURDATE()';
        $stmt = db()->prepare("SELECT id, title, slug, company, location, salary_label, work_mode, industry, function_name, experience_label, employment_type, expires_at
            FROM jobs WHERE status = 'published' AND internal = 0 AND (expires_at IS NULL OR expires_at >= $today)
            ORDER BY featured DESC, urgent DESC, published_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function careers(): array
    {
        $stmt = db()->query("SELECT id, title, slug, company, location, salary_label, work_mode, description
            FROM jobs WHERE status = 'published' AND internal = 1 ORDER BY published_at DESC LIMIT 20");
        return $stmt->fetchAll();
    }

    public static function bySlug(string $slug): ?array
    {
        $driver = \Evolve\Core\Db::isSqlite();
        $today = $driver ? "date('now')" : 'CURDATE()';
        $stmt = db()->prepare("SELECT * FROM jobs
            WHERE slug = ? AND status = 'published' AND (expires_at IS NULL OR expires_at >= $today)");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function adminList(string $status = ''): array
    {
        if ($status !== '') {
            $stmt = db()->prepare('SELECT id, title, slug, company, location, status, featured, urgent, internal, expires_at, published_at FROM jobs WHERE status = ? ORDER BY id DESC LIMIT 200');
            $stmt->execute([$status]);
        } else {
            $stmt = db()->query('SELECT id, title, slug, company, location, status, featured, urgent, internal, expires_at, published_at FROM jobs ORDER BY id DESC LIMIT 200');
        }
        return $stmt->fetchAll();
    }

    public static function byId(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM jobs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function insert(array $j): int
    {
        $stmt = db()->prepare('INSERT INTO jobs (title, slug, company, industry, function_name, location,
            experience_label, experience_min, experience_max, salary_label, salary_min, salary_max,
            employment_type, work_mode, eligibility, description, responsibilities, requirements,
            interview_process, application_instructions, status, featured, urgent, internal, published_at, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $j['title'], $j['slug'], $j['company'], $j['industry'], $j['function_name'], $j['location'],
            $j['experience_label'], $j['experience_min'], $j['experience_max'], $j['salary_label'], $j['salary_min'], $j['salary_max'],
            $j['employment_type'], $j['work_mode'], $j['eligibility'], $j['description'], $j['responsibilities'], $j['requirements'],
            $j['interview_process'], $j['application_instructions'], $j['status'],
            $j['featured'] ? 1 : 0, $j['urgent'] ? 1 : 0, $j['internal'] ? 1 : 0,
            $j['published_at'], $j['expires_at'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $j): void
    {
        $stmt = db()->prepare('UPDATE jobs SET title = ?, slug = ?, company = ?, industry = ?, function_name = ?, location = ?,
            experience_label = ?, experience_min = ?, experience_max = ?, salary_label = ?, salary_min = ?, salary_max = ?,
            employment_type = ?, work_mode = ?, eligibility = ?, description = ?, responsibilities = ?, requirements = ?,
            interview_process = ?, application_instructions = ?, status = ?, featured = ?, urgent = ?, internal = ?, expires_at = ?
            WHERE id = ?');
        $stmt->execute([
            $j['title'], $j['slug'], $j['company'], $j['industry'], $j['function_name'], $j['location'],
            $j['experience_label'], $j['experience_min'], $j['experience_max'], $j['salary_label'], $j['salary_min'], $j['salary_max'],
            $j['employment_type'], $j['work_mode'], $j['eligibility'], $j['description'], $j['responsibilities'], $j['requirements'],
            $j['interview_process'], $j['application_instructions'], $j['status'],
            $j['featured'] ? 1 : 0, $j['urgent'] ? 1 : 0, $j['internal'] ? 1 : 0, $j['expires_at'],
            $id,
        ]);
        if ($j['status'] === 'published' && $j['published_at'] === null) {
            db()->prepare('UPDATE jobs SET published_at = ? WHERE id = ? AND published_at IS NULL')
                ->execute([date('Y-m-d H:i:s'), $id]);
        }
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM jobs WHERE id = ?')->execute([$id]);
    }

    public static function countApplications(int $jobId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE job_id = ?');
        $stmt->execute([$jobId]);
        return (int) $stmt->fetchColumn();
    }

    public static function slugExists(string $slug, int $ignoreId = 0): bool
    {
        $stmt = db()->prepare('SELECT id FROM jobs WHERE slug = ? AND id != ?');
        $stmt->execute([$slug, $ignoreId]);
        return (bool) $stmt->fetch();
    }

    public static function distinct(string $column): array
    {
        $allowed = ['industry', 'function_name', 'location'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }
        return db()->query("SELECT DISTINCT $column AS v FROM jobs WHERE $column != '' AND status = 'published' ORDER BY v")->fetchAll(\PDO::FETCH_COLUMN);
    }
}
