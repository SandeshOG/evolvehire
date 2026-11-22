<?php
declare(strict_types=1);

namespace Evolve\Repository;

/** Notification feed (in-app notices instead of email on the critical path). */
final class SecurityRepo
{
    public static function notify(string $kind, string $subject, string $body): void
    {
        db()->prepare('INSERT INTO notifications (kind, subject, body) VALUES (?, ?, ?)')
            ->execute([substr($kind, 0, 20), substr($subject, 0, 180), $body]);
    }

    public static function notifications(int $limit = 8): array
    {
        return db()->query('SELECT kind, subject, body, created_at FROM notifications ORDER BY id DESC LIMIT ' . (int) $limit)->fetchAll();
    }

    public static function auditTrail(int $limit = 100): array
    {
        return db()->query('SELECT event, actor, ip, created_at FROM audit_log ORDER BY id DESC LIMIT ' . (int) $limit)->fetchAll();
    }
}
