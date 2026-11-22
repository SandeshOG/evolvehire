<?php
declare(strict_types=1);

namespace Evolve\Core;

/** CSRF token for every POST form. */
final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public static function check(): void
    {
        Session::start();
        $sent = (string) ($_POST['csrf'] ?? '');
        $real = (string) ($_SESSION['csrf'] ?? '');
        if ($real === '' || !hash_equals($real, $sent)) {
            http_response_code(400);
            exit('Form expired. Go back and submit again.');
        }
    }
}
