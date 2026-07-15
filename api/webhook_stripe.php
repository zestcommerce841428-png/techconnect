<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments/payments.php';
require_once __DIR__ . '/../includes/payments/fulfill.php';

$config = payment_gateway_config('stripe');
$rawBody = file_get_contents('php://input');
$headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);

if (!$config || !(new StripeGateway())->verifyWebhook($rawBody, $headers, $config)) {
    http_response_code(400);
    exit('invalid signature');
}

$event = json_decode($rawBody, true);
if (($event['type'] ?? '') === 'checkout.session.completed') {
    $orderId = (int) ($event['data']['object']['client_reference_id'] ?? 0);
    if ($orderId) fulfill_order($orderId);
}
http_response_code(200);
echo 'ok';
