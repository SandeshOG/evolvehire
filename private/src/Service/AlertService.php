<?php
declare(strict_types=1);

namespace Evolve\Service;

use Evolve\Core\Http;
use Evolve\Core\ValidationError;
use Evolve\Repository\AlertRepo;

/** Job alert subscribe/unsubscribe (signed token links, no login). */
final class AlertService
{
    public static function subscribe(array $in): void
    {
        if (\Evolve\Core\Request::honeypotTripped()) {
            throw new ValidationError('The preferences could not be saved.');
        }
        Http::rateLimit('form', 10, 600);
        $email = require_email((string) ($in['email'] ?? ''));
        $experience = (string) ($in['experience'] ?? '');
        if ($experience !== '' && !in_array($experience, experience_bands(), true)) {
            $experience = '';
        }
        $workMode = (string) ($in['work_mode'] ?? '');
        if (!array_key_exists($workMode, work_modes())) {
            $workMode = '';
        }
        $empType = (string) ($in['employment_type'] ?? '');
        if (!array_key_exists($empType, employment_types())) {
            $empType = '';
        }
        AlertRepo::subscribe([
            'email' => $email,
            'industry' => substr(trim((string) ($in['industry'] ?? '')), 0, 180),
            'function_name' => substr(trim((string) ($in['function'] ?? '')), 0, 180),
            'location' => substr(trim((string) ($in['location'] ?? '')), 0, 120),
            'experience' => $experience,
            'work_mode' => $workMode,
            'employment_type' => $empType,
        ]);
    }

    /** Cron/on-request digest: queue a mail per alert that matches a job. */
    public static function matchAndNotify(): int
    {
        $driver = \Evolve\Core\Db::isSqlite();
        $today = $driver ? "date('now', '-1 day')" : 'DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
        $stmt = db()->prepare("SELECT * FROM jobs WHERE status = 'published' AND published_at >= $today");
        $stmt->execute();
        $new = $stmt->fetchAll();
        $sent = 0;
        foreach ($new as $job) {
            foreach (AlertRepo::matchesForJob($job) as $email) {
                MailService::queue(
                    $email,
                    'New role — ' . $job['title'],
                    $job['title'] . ' at ' . $job['company'] . ' (' . $job['location'] . ").\n" . firm()['url'] . '/jobs/' . $job['slug']
                );
                $sent++;
            }
        }
        return $sent;
    }
}
