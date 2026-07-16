<?php
require_once __DIR__ . '/GatewayInterface.php';

class RazorpayGateway implements PaymentGatewayInterface
{
    public function isConfigured(): bool
    {
        return true; // checked by caller via config presence
    }

    public function createCheckout(array $order, array $config): array
    {
        if (empty($config['key_id']) || empty($config['key_secret'])) {
            throw new RuntimeException('Razorpay is not configured.');
        }
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $config['key_id'] . ':' . $config['key_secret'],
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'amount' => $order['amount_cents'],
                'currency' => $order['currency'],
                'receipt' => 'order_' . $order['id'],
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $response, true);
        if ($status !== 200 || empty($data['id'])) {
            throw new RuntimeException('Razorpay order creation failed: ' . ($data['error']['description'] ?? 'unknown error'));
        }
        // Client-side checkout: the browser opens Razorpay's Checkout.js with this config.
        return ['client_config' => [
            'gateway' => 'razorpay',
            'key' => $config['key_id'],
            'order_id' => $data['id'],
            'amount' => $order['amount_cents'],
            'currency' => $order['currency'],
        ]];
    }

    public function verifyWebhook(string $rawBody, array $headers, array $config): bool
    {
        $secret = $config['webhook_secret'] ?? '';
        $sig = $headers['x-razorpay-signature'] ?? '';
        if (!$secret || !$sig) return false;
        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $sig);
    }
}
