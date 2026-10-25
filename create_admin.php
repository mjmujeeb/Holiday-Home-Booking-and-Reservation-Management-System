<?php
/*
 * Command-line admin tool. It refuses to run in a web request.
 *
 *   php database_schema/create_admin.php create
 *   php database_schema/create_admin.php reset-password you@example.com
 *   php database_schema/create_admin.php disable-2fa     you@example.com
 *
 * XAMPP on Windows:  C:\xampp\php\php.exe database_schema\create_admin.php create
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('HHH_ROOT', dirname(__DIR__));
if (!is_file(HHH_ROOT . '/inc/config.php')) {
    fwrite(STDERR, "Copy inc/config.sample.php to inc/config.php first.\n");
    exit(1);
}
$GLOBALS['hhh_config'] = require HHH_ROOT . '/inc/config.php';
date_default_timezone_set((string) ($GLOBALS['hhh_config']['timezone'] ?? 'UTC'));
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require HHH_ROOT . '/inc/helpers.php';
require HHH_ROOT . '/inc/db.php';
require HHH_ROOT . '/inc/security.php';

function ask(string $label, bool $secret = false): string
{
    echo $label . ': ';
    $hide = $secret && DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')
          && function_exists('posix_isatty') && posix_isatty(STDIN);   // only hide typing in a real terminal
    if ($hide) {
        shell_exec('stty -echo');
    }
    $v = trim((string) fgets(STDIN));
    if ($hide) {
        shell_exec('stty echo');
        echo "\n";
    }
    return $v;
}

$cmd   = $argv[1] ?? '';
$email = mb_strtolower($argv[2] ?? '');

switch ($cmd) {
    case 'create':
        $name  = ask('Full name');
        $email = mb_strtolower(ask('Email'));
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fwrite(STDERR, "A name and a valid email are required.\n");
            exit(1);
        }
        $pw = ask('Password (12+ characters)', true);
        if ($pw !== ask('Repeat password', true)) {
            fwrite(STDERR, "Passwords do not match.\n");
            exit(1);
        }
        $problems = password_problems($pw, [$name, strstr($email, '@', true) ?: ''], 12);
        if ($problems) {
            fwrite(STDERR, implode("\n", $problems) . "\n");
            exit(1);
        }
        if (db_one('SELECT admin_id FROM admins WHERE email = ?', 's', [$email])) {
            fwrite(STDERR, "An admin with that email already exists.\n");
            exit(1);
        }
        db_run('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)', 'sss', [$name, $email, password_make($pw)]);
        audit('system', null, 'admin_created_cli', $email);
        echo "Admin created. Sign in at /admin/admin_login.php and turn on two-step sign-in under Security.\n";
        break;

    case 'reset-password':
        if (!db_one('SELECT admin_id FROM admins WHERE email = ?', 's', [$email])) {
            fwrite(STDERR, "No admin with that email.\n");
            exit(1);
        }
        $pw = ask('New password (12+ characters)', true);
        $problems = password_problems($pw, [strstr($email, '@', true) ?: ''], 12);
        if ($problems) {
            fwrite(STDERR, implode("\n", $problems) . "\n");
            exit(1);
        }
        db_run('UPDATE admins SET password_hash = ? WHERE email = ?', 'ss', [password_make($pw), $email]);
        audit('system', null, 'admin_password_reset_cli', $email);
        echo "Password updated.\n";
        break;

    case 'disable-2fa':
        $n = db_run('UPDATE admins SET totp_enabled = 0, totp_secret = NULL, totp_last_step = NULL WHERE email = ?', 's', [$email]);
        audit('system', null, 'admin_2fa_reset_cli', $email);
        echo $n ? "Two-step sign-in switched off for $email.\n" : "No admin with that email.\n";
        break;

    default:
        echo "Usage:\n  php create_admin.php create\n  php create_admin.php reset-password <email>\n  php create_admin.php disable-2fa <email>\n";
}
