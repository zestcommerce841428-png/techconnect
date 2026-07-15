<?php
require_once __DIR__ . '/GatewayInterface.php';

class StripeGateway implements PaymentGatewayInterface
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function createCheckout(array $order, array $config): array
    {
        if (empty($config['secret_key'])) {
            throw new RuntimeException('Stripe is not configured.');
        }
        $fields = [
            'mode' => $order['item_type'] === 'pro_membership' ? 'subscription' : 'payment',
            'success_url' => SITE_URL . '/checkout/success?order=' . $order['id'],
            'cancel_url' => SITE_URL . '/checkout/cancel?order=' . $order['id'],
            'line_items[0][price_data][currency]' => strtolower($order['currency']),
            'line_items[0][price_data][unit_amount]' => $order['amount_cents'],
            'line_items[0][price_data][product_data][name]' => $order['description'] ?? 'Order #' . $order['id'],
            'line_items[0][quantity]' => 1,
            'client_reference_id' => (string) $order['id'],
        ];
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $config['secret_key'] . ':',
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $response, true);
        if ($status !== 200 || empty($data['url'])) {
            throw new RuntimeException('Stripe checkout session failed: ' . ($data['error']['message'] ?? 'unknown error'));
        }
        return ['redirect_url' => $data['url']];
    }

    public function verifyWebhook(string $rawBody, array $headers, array $config): bool
    {
        $secret = $config['webhook_secret'] ?? '';
        $sigHeader = $headers['stripe-signature'] ?? '';
        if (!$secret || !$sigHeader) return false;
        parse_str(str_replace(',', '&', $sigHeader), $parts);
        $timestamp = $parts['t'] ?? '';
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        return hash_equals($expected, $parts['v1'] ?? '');
    }
}
