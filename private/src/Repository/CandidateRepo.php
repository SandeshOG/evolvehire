<?php
declare(strict_types=1);

namespace Evolve\Repository;

/** CV bank: upsert by email, search, status moves. */
final class CandidateRepo
{
    public static function upsert(array $c): array
    {
        $existing = self::byEmail($c['email']);
        if ($existing) {
            db()->prepare('UPDATE candidates SET name = ?, mobile = ?, current_location = ?, experience = ?,
                designation = ?, preferred_location = ?, preferred_industry = ?, preferred_function = ?,
                expected_salary = ?, notice_period = ?, resume_path = CASE WHEN ? != \'\' THEN ? ELSE resume_path END
                WHERE id = ?')
                ->execute([
                    $c['name'], $c['mobile'], $c['current_location'], $c['experience'],
                    $c['designation'], $c['preferred_location'], $c['preferred_industry'], $c['preferred_function'],
                    $c['expected_salary'], $c['notice_period'],
                    $c['resume_path'], $c['resume_path'],
                    (int) $existing['id'],
                ]);
            return ['id' => (int) $existing['id'], 'ref_code' => (string) $existing['ref_code'], 'existing' => true];
        }
        $ref = ref_code();
        db()->prepare('INSERT INTO candidates (ref_code, name, mobile, email, current_location, experience,
            designation, preferred_location, preferred_industry, preferred_function, expected_salary, notice_period, resume_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $ref, $c['name'], $c['mobile'], $c['email'], $c['current_location'], $c['experience'],
                $c['designation'], $c['preferred_location'], $c['preferred_industry'], $c['preferred_function'],
                $c['expected_salary'], $c['notice_period'], $c['resume_path'],
            ]);
        return ['id' => (int) db()->lastInsertId(), 'ref_code' => $ref, 'existing' => false];
    }

    public static function byEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM candidates WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function byRef(string $ref): ?array
    {
        $stmt = db()->prepare('SELECT * FROM candidates WHERE ref_code = ?');
        $stmt->execute([$ref]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function byEmailLimit(string $email, int $limit = 5): array
    {
        $stmt = db()->prepare('SELECT ref_code, name, status, created_at, updated_at FROM candidates WHERE email = ? ORDER BY id DESC LIMIT ' . (int) $limit);
        $stmt->execute([$email]);
        return $stmt->fetchAll();
    }

    public static function deskList(string $search = ''): array
    {
        $like = '%' . $search . '%';
        if ($search !== '') {
            $driver = \Evolve\Core\Db::isSqlite();
            $op = $driver ? 'LIKE' : 'LIKE';
            $stmt = db()->prepare("SELECT id, ref_code, name, email, mobile, current_location, designation,
                preferred_function, expected_salary, status, created_at, resume_path FROM candidates
                WHERE name $op ? OR email $op ? OR preferred_location $op ? OR preferred_function $op ? OR designation $op ?
                ORDER BY id DESC LIMIT 200");
            $stmt->execute([$like, $like, $like, $like, $like]);
        } else {
            $stmt = db()->query('SELECT id, ref_code, name, email, mobile, current_location, designation,
                preferred_function, expected_salary, status, created_at, resume_path FROM candidates ORDER BY id DESC LIMIT 200');
        }
        return $stmt->fetchAll();
    }

    public static function setStatus(int $id, string $status, string $actor = ''): void
    {
        db()->prepare('UPDATE candidates SET status = ?, updated_by = ? WHERE id = ?')
            ->execute([$status, substr($actor, 0, 190), $id]);
    }

    public static function countAll(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM candidates')->fetchColumn();
    }
}
