<?php
declare(strict_types=1);

namespace Evolve\Repository;

/** Employer briefs and contact messages. */
final class LeadRepo
{
    public static function insert(array $l): int
    {
        $stmt = db()->prepare('INSERT INTO leads (company, contact, email, phone, industry, role_title,
            openings, location, experience, salary, employment_type, work_mode, detail, jd_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $l['company'], $l['contact'], $l['email'], $l['phone'], $l['industry'], $l['role_title'],
            $l['openings'], $l['location'], $l['experience'], $l['salary'],
            $l['employment_type'], $l['work_mode'], $l['detail'], $l['jd_path'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function deskList(): array
    {
        return db()->query('SELECT * FROM leads ORDER BY id DESC LIMIT 200')->fetchAll();
    }

    public static function byId(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM leads WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function countAll(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM leads')->fetchColumn();
    }
}
