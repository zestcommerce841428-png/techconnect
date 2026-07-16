<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** True unless the user has opted out of this email kind (answer|mention|message|tag_digest). */
function user_email_pref(int $userId, string $kind): bool
{
    $column = $kind === 'tag_digest' ? 'email_tag_digest' : 'email_on_' . $kind;
    if (!in_array($column, ['email_on_answer', 'email_on_mention', 'email_on_message', 'email_tag_digest'], true)) {
        return true;
    }
    $stmt = db()->prepare("SELECT {$column}, muted_until FROM notification_prefs WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row === false) return true; // no row = defaults, all on
    if (!empty($row['muted_until']) && strtotime($row['muted_until']) > time()) return false;
    return (bool) $row[$column];
}

function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $replyTo = null): bool
{
    $mail = new PHPMailer(true);
    try {
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }
        if (SMTP_HOST !== '') {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port = SMTP_PORT;
        }
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}
