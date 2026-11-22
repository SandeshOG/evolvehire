<?php
declare(strict_types=1);

namespace Evolve\Repository;

/** Applications pipeline: intake, tracking, desk list, status history. */
final class ApplicationRepo
{
    public static function insert(array $a): int
    {
        $stmt = db()->prepare('INSERT INTO applications (job_id, ref_code, name, mobile, email,
            current_location, experience, notice_period, expected_salary, note, resume_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $a['job_id'], $a['ref_code'], $a['name'], $a['mobile'], $a['email'],
            $a['current_location'], $a['experience'], $a['notice_period'], $a['expected_salary'],
            $a['note'], $a['resume_path'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function addEvent(int $applicationId, string $status, string $note = '', string $actor = ''): void
    {
        db()->prepare('INSERT INTO application_events (application_id, status, note, actor) VALUES (?, ?, ?, ?)')
            ->execute([$applicationId, $status, substr($note, 0, 500), substr($actor, 0, 190)]);
    }

    public static function events(int $applicationId): array
    {
        $stmt = db()->prepare('SELECT status, note, actor, created_at FROM application_events WHERE application_id = ? ORDER BY id DESC LIMIT 50');
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }

    public static function byRef(string $ref): ?array
    {
        $stmt = db()->prepare('SELECT a.*, j.title AS job_title, j.slug AS job_slug FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.ref_code = ?');
        $stmt->execute([$ref]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function byEmail(string $email, int $limit = 20): array
    {
        $stmt = db()->prepare('SELECT a.*, j.title AS job_title, j.slug AS job_slug FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.email = ? ORDER BY a.id DESC LIMIT ' . (int) $limit);
        $stmt->execute([$email]);
        return $stmt->fetchAll();
    }

    public static function deskList(string $status = ''): array
    {
        if ($status !== '') {
            $stmt = db()->prepare('SELECT a.id, a.ref_code, a.name, a.email, a.mobile, a.current_location, a.experience,
                a.expected_salary, a.status, a.created_at, a.resume_path, j.title AS job_title
                FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.status = ? ORDER BY a.id DESC LIMIT 200');
            $stmt->execute([$status]);
        } else {
            $stmt = db()->query('SELECT a.id, a.ref_code, a.name, a.email, a.mobile, a.current_location, a.experience,
                a.expected_salary, a.status, a.created_at, a.resume_path, j.title AS job_title
                FROM applications a LEFT JOIN jobs j ON j.id = a.job_id ORDER BY a.id DESC LIMIT 200');
        }
        return $stmt->fetchAll();
    }

    public static function byId(int $id): ?array
    {
        $stmt = db()->prepare('SELECT a.*, j.title AS job_title FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function setStatus(int $id, string $status, string $note = '', string $actor = ''): void
    {
        db()->prepare('UPDATE applications SET status = ?, updated_by = ? WHERE id = ?')
            ->execute([$status, substr($actor, 0, 190), $id]);
        self::addEvent($id, $status, $note, $actor);
    }

    public static function existingForJob(int $jobId, string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM applications WHERE job_id = ? AND email = ? LIMIT 1');
        $stmt->execute([$jobId, $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function pipelineCounts(): array
    {
        $rows = db()->query('SELECT status, COUNT(*) AS c FROM applications GROUP BY status')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    public static function countAll(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM applications')->fetchColumn();
    }
}
