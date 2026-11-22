<?php
declare(strict_types=1);

/**
 * Application front controller. Replaces the old single-file router:
 * loads bootstrap, applies security headers, dispatches via the route table.
 */

require __DIR__ . '/src/bootstrap.php';

// Page functions live in views/, loaded once.
require private_dir() . '/views/components.php';
require private_dir() . '/views/pages_jobs.php';
require private_dir() . '/views/pages_flow.php';
require private_dir() . '/views/pages_content.php';
require private_dir() . '/views/pages_desk.php';
require private_dir() . '/views/pages_admin.php';
require private_dir() . '/views/pages_misc.php';

set_exception_handler(static function (Throwable $e): void {
    log_error($e);
    if (str_starts_with(path(), '/healthz')) {
        http_response_code(500);
        exit('{"ok":false}');
    }
    http_response_code(500);
    Evolve\Core\View::layout('Error | Evolve Hire', '<section class="section"><div class="wrap"><h1>The desk could not load</h1><p class="muted">Please try again in a moment. The problem is recorded in the desk log.</p></div></section>');
    exit;
});

send_security_headers();

// Health check: DB reachable + app alive.
if (path() === '/healthz') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    db()->query('SELECT 1');
    echo '{"ok":true,"version":"' . h(trim((string) @file_get_contents(APP_ROOT . '/VERSION'))) . '"}';
    exit;
}

$router = new Evolve\Core\Router();
$register = require private_dir() . '/src/Controller/routes.php';
$register($router);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$p = path();

// Only GET/POST are allowed through.
if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

if (!$router->dispatch($method, $p)) {
    http_response_code(404);
    Evolve\Core\View::layout('Page not found | Evolve Hire', '<section class="section"><div class="wrap"><h1>That page is not on this site.</h1><p class="muted">Check the address, or go to the <a href="' . h(url('/')) . '">front page</a>.</p></div></section>');
}
