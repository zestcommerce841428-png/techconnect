<?php
/** Sends a plain-text message to the admin-configured Telegram chat via the Bot API. No-op if unconfigured. */
function telegram_notify(string $message): void
{
    $token = setting('telegram_bot_token');
    $chatId = setting('telegram_notify_chat_id');
    if (!$token || !$chatId) return;

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML']),
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
