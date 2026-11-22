<?php
declare(strict_types=1);

/**
 * CLI cron entry: drains the mail outbox and runs the alert digest.
 * cPanel cron (every 5 min):
 *   /usr/local/bin/php /home/USERNAME/evolvehire/private/cron.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/migrate.php';
require __DIR__ . '/src/seeds.php';

try {
    run_migrations();
    seed_content();
} catch (Throwable $e) {
    log_error($e);
}

$mail = Evolve\Service\MailService::drain(20);
$alerts = Evolve\Service\AlertService::matchAndNotify();
echo date('c') . " cron: $mail mails sent, $alerts alert mails queued\n";
