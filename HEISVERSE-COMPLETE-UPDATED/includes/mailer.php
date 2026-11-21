<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/autoload.php';

function heisverse_send_password_reset_email(
    string $toEmail,
    string $username,
    string $resetUrl
): bool {
    $host = getenv('SMTP_HOST') ?: '';
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $secure = strtolower(getenv('SMTP_SECURE') ?: 'tls');
    $user = getenv('SMTP_USERNAME') ?: '';
    $pass = getenv('SMTP_PASSWORD') ?: '';
    $from = getenv('MAIL_FROM_ADDRESS') ?: $user;
    $fromName = getenv('MAIL_FROM_NAME') ?: 'HEISVERSE';

    if (!$host || !$user || !$pass || !$from) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->Port = $port;
        $mail->Timeout = 15;

        if ($secure === 'ssl' || $port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($toEmail, $username);
        $mail->isHTML(true);
        $mail->Subject = 'HEISVERSE password reset';

        $safeName = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        $mail->Body =
            '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;">' .
            '<h2>HEISVERSE password recovery</h2>' .
            '<p>Hello ' . $safeName . ',</p>' .
            '<p>We received a request to reset your HEISVERSE password.</p>' .
            '<p><a href="' . $safeUrl . '">Reset your password</a></p>' .
            '<p>This link expires in one hour and can only be used once.</p>' .
            '<p>If you did not request this, you can ignore this email.</p>' .
            '</div>';

        $mail->AltBody =
            "Hello {$username},\n\n" .
            "Reset your HEISVERSE password using this link:\n{$resetUrl}\n\n" .
            "This link expires in one hour and can only be used once.";

        $mail->send();
        return true;
    } catch (\Throwable $exception) {
        error_log('HEISVERSE password reset email could not be sent.');
        return false;
    }
}