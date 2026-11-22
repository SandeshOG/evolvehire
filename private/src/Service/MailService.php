<?php
declare(strict_types=1);

namespace Evolve\Service;

/**
 * Mail queue. PHP mail() is the transport (cPanel provides it); messages go
 * to the outbox table first so the site never blocks on email. cron.php
 * drains the queue; without cron the desk still works.
 */
final class MailService
{
    public static function queue(string $to, string $subject, string $body): void
    {
        try {
            db()->prepare('INSERT INTO outbox (to_email, subject, body) VALUES (?, ?, ?)')
                ->execute([$to, substr($subject, 0, 180), $body]);
        } catch (\Throwable $e) {
            \Evolve\Core\Log::exception($e);
        }
    }

    public static function notifyOwner(string $subject, string $body): void
    {
        $to = cfg()['notify_email'] ?? firm()['email'];
        self::queue($to, $subject, $body);
    }

    /** Drain the queue (called from cron.php or CLI). Returns processed count. */
    public static function drain(int $limit = 20): int
    {
        $rows = db()->query("SELECT id, to_email, subject, body, attempts FROM outbox WHERE status = 'pending' AND attempts < 5 ORDER BY id LIMIT " . (int) $limit)->fetchAll();
        $mark = db()->prepare('UPDATE outbox SET status = ?, attempts = ? WHERE id = ?');
        $from = cfg()['notify_email'] ?? 'ritikkumbhare760@gmail.com';
        foreach ($rows as $row) {
            $headers = 'From: Evolve Hire <' . $from . ">\r\nContent-Type: text/plain; charset=UTF-8";
            $sent = @mail($row['to_email'], $row['subject'], $row['body'], $headers);
            $attempts = (int) $row['attempts'] + 1;
            $mark->execute([$sent ? 'sent' : ($attempts >= 5 ? 'failed' : 'pending'), $attempts, (int) $row['id']]);
        }
        return count($rows);
    }
}
