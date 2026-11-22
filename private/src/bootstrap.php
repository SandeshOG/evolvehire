<?php
declare(strict_types=1);

/**
 * Evolve Hire application bootstrap.
 * Registers the PSR-4-style autoloader, loads core helpers and sets up
 * error handling. Every entry point (index.php, install.php, migrate.php,
 * cron.php, CLI tools) requires exactly this one file.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Kolkata');
mb_internal_encoding('UTF-8');

define('APP_ROOT', dirname(__DIR__, 2)); // deploy/webspacekit/
define('SRC_ROOT', __DIR__);
define('PRIVATE_ROOT', dirname(__DIR__)); // private/

spl_autoload_register(static function (string $class): void {
    $prefix = 'Evolve\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = SRC_ROOT . '/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require SRC_ROOT . '/Core/helpers.php';
require SRC_ROOT . '/Core/ValidationError.php';
require SRC_ROOT . '/Core/globals.php';
require SRC_ROOT . '/Core/Db.php';
require SRC_ROOT . '/Core/Log.php';
require SRC_ROOT . '/Core/Http.php';
require SRC_ROOT . '/Core/Request.php';
require SRC_ROOT . '/Core/Response.php';
require SRC_ROOT . '/Core/Router.php';
require SRC_ROOT . '/Core/Session.php';
require SRC_ROOT . '/Core/Csrf.php';
require SRC_ROOT . '/Core/View.php';

require SRC_ROOT . '/Repository/JobRepo.php';
require SRC_ROOT . '/Repository/ApplicationRepo.php';
require SRC_ROOT . '/Repository/CandidateRepo.php';
require SRC_ROOT . '/Repository/LeadRepo.php';
require SRC_ROOT . '/Repository/ContentRepo.php';
require SRC_ROOT . '/Repository/AlertRepo.php';
require SRC_ROOT . '/Repository/UserRepo.php';
require SRC_ROOT . '/Repository/SecurityRepo.php';

require SRC_ROOT . '/Service/UploadService.php';
require SRC_ROOT . '/Service/MailService.php';
require SRC_ROOT . '/Service/AuthService.php';
require SRC_ROOT . '/Service/ApplyService.php';
require SRC_ROOT . '/Service/IntakeService.php';
require SRC_ROOT . '/Service/JobService.php';
require SRC_ROOT . '/Service/AlertService.php';
