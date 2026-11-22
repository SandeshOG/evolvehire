<?php
declare(strict_types=1);

/**
 * Global-namespace shims. The Core classes live under Evolve\..., while the
 * page templates (views/) are plain functions in the global namespace and
 * call path(), csrf(), db(), h(), url() etc. directly. This file defines
 * those names in the global namespace once, after the classes are loaded.
 */

use Evolve\Core\Csrf;
use Evolve\Core\Http;
use Evolve\Core\Log;
use Evolve\Core\Request;
use Evolve\Core\Session;

function path(): string
{
    return Request::path();
}

function start_session(): void
{
    Session::start();
}

function user(): ?array
{
    return Session::user();
}

function csrf(): string
{
    return Csrf::token();
}

function check_csrf(): void
{
    Csrf::check();
}

function client_ip(): string
{
    return Http::clientIp();
}

function send_security_headers(): void
{
    Http::sendSecurityHeaders();
}

function rate_limit(string $bucket, int $max, int $windowSec): void
{
    Http::rateLimit($bucket, $max, $windowSec);
}

function audit(string $event, string $actor = ''): void
{
    Http::audit($event, $actor);
}

function setup_token_ok(): bool
{
    $real = (string) (cfg()['setup_token'] ?? '');
    $sent = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    return strlen($real) >= 24 && hash_equals($real, $sent);
}

function log_error(\Throwable $e): void
{
    Log::exception($e);
}

function layout(string $title, string $body, bool $desk = false): void
{
    \Evolve\Core\View::layout($title, $body, [], $desk);
}

function require_role(string ...$roles): array
{
    return \Evolve\Service\AuthService::requireRole(...$roles);
}

function queue_mail(string $to, string $subject, string $body): void
{
    \Evolve\Service\MailService::queue($to, $subject, $body);
}

function save_resume(array $file): string
{
    return \Evolve\Service\UploadService::save($file, 'resume');
}
