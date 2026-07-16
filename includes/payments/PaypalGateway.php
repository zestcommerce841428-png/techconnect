<?php
require_once __DIR__ . '/GatewayInterface.php';

class PaypalGateway implements PaymentGatewayInterface
{
    public function isConfigured(): bool
    {
        return true;
    }

    private function apiBase(array $config): string
    {
        return ($config['mode'] ?? 'test') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function getAccessToken(array $config): string
    {
        $ch = curl_init($this->apiBase($config) . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $config['client_id'] . ':' . $config['client_secret'],
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $response, true);
        if ($status !== 200 || empty($data['access_token'])) {
            throw new RuntimeException('PayPal auth failed.');
        }
        return $data['access_token'];
    }

    public function createCheckout(array $order, array $config): array
    {
        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new RuntimeException('PayPal is not configured.');
        }
        $token = $this->getAccessToken($config);
        $amount = number_format($order['amount_cents'] / 100, 2, '.', '');
        $ch = curl_init($this->apiBase($config) . '/v2/checkout/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_POSTFIELDS => json_encode([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $order['id'],
                    'amount' => ['currency_code' => $order['currency'], 'value' => $amount],
                ]],
                'application_context' => [
                    'return_url' => SITE_URL . '/checkout/success?order=' . $order['id'],
                    'cancel_url' => SITE_URL . '/checkout/cancel?order=' . $order['id'],
                ],
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $response, true);
        if ($status >= 300 || empty($data['links'])) {
            throw new RuntimeException('PayPal order creation failed.');
        }
        $approveUrl = null;
        foreach ($data['links'] as $link) {
            if ($link['rel'] === 'approve') { $approveUrl = $link['href']; break; }
        }
        if (!$approveUrl) {
            throw new RuntimeException('PayPal approval link missing.');
        }
        return ['redirect_url' => $approveUrl];
    }

    public function verifyWebhook(string $rawBody, array $headers, array $config): bool
    {
        if (empty($config['webhook_id']) || empty($config['client_id']) || empty($config['client_secret'])) {
            return false;
        }
        try {
            $token = $this->getAccessToken($config);
        } catch (Throwable $e) {
            return false;
        }
        $ch = curl_init($this->apiBase($config) . '/v1/notifications/verify-webhook-signature');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_POSTFIELDS => json_encode([
                'transmission_id' => $headers['paypal-transmission-id'] ?? '',
                'transmission_time' => $headers['paypal-transmission-time'] ?? '',
                'cert_url' => $headers['paypal-cert-url'] ?? '',
                'auth_algo' => $headers['paypal-auth-algo'] ?? '',
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
                'webhook_id' => $config['webhook_id'],
                'webhook_event' => json_decode($rawBody, true),
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $response, true);
        return ($data['verification_status'] ?? '') === 'SUCCESS';
    }
}
