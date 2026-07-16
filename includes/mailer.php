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

/** Readable plain-text fallback from an HTML email body. */
function html_to_plain_text(string $html): string
{
    // Drop invisible scaffolding (preheader spacer, head) before flattening.
    $text = preg_replace('#<(head|style|script)\b[^>]*>.*?</\1>#si', '', $html) ?? $html;
    $text = preg_replace('#<div style="display:none.*?</div>#si', '', $text) ?? $text;
    // Keep link targets visible, since the text part has no clickable anchors.
    $text = preg_replace('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#si', '$2 ($1)', $text) ?? $text;
    $text = preg_replace('#<(br|/p|/h[1-6]|/tr|/div)\s*/?>#i', "\n", $text) ?? $text;
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    return trim(implode("\n", array_map('trim', explode("\n", $text))));
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
        // HTML-only mail scores badly with spam filters and breaks plain-text
        // readers; derive a text part from the HTML rather than requiring every
        // caller to write one.
        $mail->AltBody = html_to_plain_text($htmlBody);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}
