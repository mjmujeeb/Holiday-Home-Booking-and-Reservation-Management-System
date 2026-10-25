<?php
/* Loaded first by every page. Sets up configuration, error handling, the database
 * helpers, a hardened session and the HTTP security headers. */

define('HHH_ROOT', dirname(__DIR__));

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('HappyHolidayHome needs PHP 8.1 or newer.');
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Setup needed: copy inc/config.sample.php to inc/config.php and fill it in (README.md, step 3).');
}
$GLOBALS['hhh_config'] = require $configFile;
require_once __DIR__ . '/helpers.php';

date_default_timezone_set((string) config('timezone', 'UTC'));
mb_internal_encoding('UTF-8');

// Errors are logged, never shown to visitors.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', HHH_ROOT . '/storage/logs/php-error.log');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/uploads.php';

set_exception_handler(function (Throwable $e): void {
    error_log(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
       . '<body style="font-family:system-ui;max-width:32rem;margin:15vh auto;padding:0 1rem">'
       . '<h1>Something went wrong</h1><p>We have logged the problem. Please try again in a moment.</p>';
    exit;
});

// HTTPS enforcement (opt-in from config).
if (config('force_https') && !is_https() && PHP_SAPI !== 'cli') {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
secure_session_start();
send_security_headers();
