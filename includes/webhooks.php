<?php
/**
 * Fires all enabled webhooks subscribed to $event, POSTing the JSON payload
 * with an HMAC-SHA256 signature (X-Webhook-Signature) so receivers can verify
 * authenticity, the same pattern Stripe/GitHub webhooks use.
 */
function fire_webhook(string $event, array $payload): void
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, url, secret FROM webhooks WHERE enabled = 1 AND FIND_IN_SET(?, events)");
    $stmt->execute([$event]);
    $webhooks = $stmt->fetchAll();
    if (!$webhooks) return;

    $body = json_encode(['event' => $event, 'data' => $payload, 'timestamp' => time()], JSON_UNESCAPED_SLASHES);

    foreach ($webhooks as $wh) {
        $signature = hash_hmac('sha256', $body, $wh['secret']);
        $ch = curl_init($wh['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Webhook-Signature: ' . $signature, 'X-Webhook-Event: ' . $event],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $pdo->prepare('INSERT INTO webhook_deliveries (webhook_id, event, response_code, success) VALUES (?, ?, ?, ?)')
            ->execute([$wh['id'], $event, $code ?: null, $code >= 200 && $code < 300 ? 1 : 0]);
    }
}
