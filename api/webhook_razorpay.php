<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments/payments.php';
require_once __DIR__ . '/../includes/payments/fulfill.php';

$config = payment_gateway_config('razorpay');
$rawBody = file_get_contents('php://input');
$headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);

if (!$config || !(new RazorpayGateway())->verifyWebhook($rawBody, $headers, $config)) {
    http_response_code(400);
    exit('invalid signature');
}

$payload = json_decode($rawBody, true);
$receipt = $payload['payload']['order']['entity']['receipt'] ?? '';
if (preg_match('/^order_(\d+)$/', (string) $receipt, $m)) {
    fulfill_order((int) $m[1]);
}
http_response_code(200);
echo 'ok';
