<?php
declare(strict_types=1);

/**
 * Post-upload migration step: https://yourdomain/migrate.php?token=SETUP_TOKEN
 * Applies pending migrations, refreshes seeds (never overwrites edits).
 */

require dirname(__DIR__) . '/private/src/bootstrap.php';
require dirname(__DIR__) . '/private/src/migrate.php';
require dirname(__DIR__) . '/private/src/seeds.php';

send_security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if (!setup_token_ok()) {
        http_response_code(404);
        exit('{"ok":false}');
    }
    $applied = run_migrations();
    seed_content();
    echo json_encode([
        'ok' => true,
        'applied' => $applied,
        'version' => trim((string) @file_get_contents(dirname(__DIR__) . '/VERSION')),
    ]);
} catch (Throwable $e) {
    log_error($e);
    http_response_code(500);
    echo '{"ok":false,"error":"see private/logs/app.log"}';
}
