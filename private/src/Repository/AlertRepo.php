<?php
declare(strict_types=1);

namespace Evolve\Repository;

/** Job alert subscriptions (email + filters, signed unsubscribe token). */
final class AlertRepo
{
    public static function subscribe(array $a): void
    {
        $token = bin2hex(random_bytes(24));
        if (\Evolve\Core\Db::isSqlite()) {
            db()->prepare('INSERT INTO job_alerts (email, industry, function_name, location, experience, work_mode, employment_type, unsubscribe_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT(email, industry, function_name, location) DO UPDATE SET
                  industry = excluded.industry, function_name = excluded.function_name,
                  location = excluded.location, experience = excluded.experience,
                  work_mode = excluded.work_mode, employment_type = excluded.employment_type,
                  unsubscribe_token = excluded.unsubscribe_token')
                ->execute([
                    $a['email'], $a['industry'], $a['function_name'], $a['location'],
                    $a['experience'], $a['work_mode'], $a['employment_type'], $token,
                ]);
        } else {
            db()->prepare('INSERT INTO job_alerts (email, industry, function_name, location, experience, work_mode, employment_type, unsubscribe_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE industry = VALUES(industry), function_name = VALUES(function_name),
                location = VALUES(location), experience = VALUES(experience), work_mode = VALUES(work_mode),
                employment_type = VALUES(employment_type), unsubscribe_token = VALUES(unsubscribe_token)')
                ->execute([
                    $a['email'], $a['industry'], $a['function_name'], $a['location'],
                    $a['experience'], $a['work_mode'], $a['employment_type'], $token,
                ]);
        }
    }

    public static function all(): array
    {
        return db()->query('SELECT * FROM job_alerts ORDER BY id DESC LIMIT 500')->fetchAll();
    }

    public static function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = db()->prepare('SELECT * FROM job_alerts WHERE unsubscribe_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function removeByToken(string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return false;
        }
        $stmt = db()->prepare('DELETE FROM job_alerts WHERE unsubscribe_token = ?');
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }

    public static function countAll(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM job_alerts')->fetchColumn();
    }

    /** Alerts matching one job (used by the desk and the cron digest). */
    public static function matchesForJob(array $job): array
    {
        $stmt = db()->prepare('SELECT email FROM job_alerts WHERE
            (industry = \'\' OR industry = ?) AND (function_name = \'\' OR function_name = ?)
            AND (location = \'\' OR location = ?) AND (experience = \'\' OR experience = ?)
            AND (work_mode = \'\' OR work_mode = ?) AND (employment_type = \'\' OR employment_type = ?)');
        $stmt->execute([
            $job['industry'], $job['function_name'], $job['location'],
            $job['experience_label'], $job['work_mode'], $job['employment_type'],
        ]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
