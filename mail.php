<?php
/* Outgoing email through PHPMailer. In 'development' mode nothing leaves the machine:
 * messages are appended to storage/logs/mail.log so you can copy the sign-up code from there. */

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function send_mail(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) {
        return false;   // blocks header injection
    }
    $cfg = config('mail', []);
    $dev = config('env') === 'development' || empty($cfg['host']) || empty($cfg['user']);

    if ($dev) {
        $line = str_repeat('-', 60) . "\n" . date('c') . "\nTo: $to\nSubject: $subject\n\n$body\n";
        return @file_put_contents(HHH_ROOT . '/storage/logs/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }

    require_once HHH_ROOT . '/PHPMailer/src/Exception.php';
    require_once HHH_ROOT . '/PHPMailer/src/PHPMailer.php';
    require_once HHH_ROOT . '/PHPMailer/src/SMTP.php';

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->Port       = (int) ($cfg['port'] ?? 465);
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['user'];
        $mail->Password   = $cfg['pass'];
        $mail->SMTPSecure = ($cfg['encryption'] ?? 'ssl') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Timeout    = 10;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($cfg['from'] ?: $cfg['user'], $cfg['from_name'] ?? 'HappyHolidayHome');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);
        $mail->send();
        return true;
    } catch (MailException $e) {
        error_log('Mail failed: ' . $e->getMessage());
        return false;
    }
}
