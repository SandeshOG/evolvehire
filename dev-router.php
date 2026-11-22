<?php
declare(strict_types=1);

/**
 * Router for `php -S` only. Apache on cPanel uses public/.htaccess.
 * Static files in public/ are served as-is; everything else hits index.php.
 */
$root = __DIR__ . '/public';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rawurldecode($uri);
$rootReal = realpath($root);
$full = realpath($root . $uri);
if ($uri !== '/' && $full && $rootReal && str_starts_with($full, $rootReal) && is_file($full)) {
    return false;
}
require $root . '/index.php';
