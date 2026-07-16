<?php
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/GatewayInterface.php';
require_once __DIR__ . '/RazorpayGateway.php';
require_once __DIR__ . '/StripeGateway.php';
require_once __DIR__ . '/PaypalGateway.php';

/** Loaded, decrypted config for one gateway, or null if not enabled/configured. */
function payment_gateway_config(string $gateway): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT enabled, mode, config_encrypted FROM payment_settings WHERE gateway = ?');
    $stmt->execute([$gateway]);
    $row = $stmt->fetch();
    if (!$row || !$row['enabled']) return null;
    $json = payments_decrypt($row['config_encrypted']);
    $config = $json ? json_decode($json, true) : [];
    if (!is_array($config)) $config = [];
    $config['mode'] = $row['mode'];
    return $config;
}

function payment_gateway_instance(string $gateway): PaymentGatewayInterface
{
    return match ($gateway) {
        'razorpay' => new RazorpayGateway(),
        'stripe' => new StripeGateway(),
        'paypal' => new PaypalGateway(),
        default => throw new InvalidArgumentException('Unknown gateway: ' . $gateway),
    };
}

/** Gateways an admin has turned on and supplied config for — what checkout pages should offer. */
function payment_available_gateways(): array
{
    $available = [];
    foreach (['razorpay', 'stripe', 'paypal'] as $gw) {
        if (payment_gateway_config($gw) !== null) $available[] = $gw;
    }
    return $available;
}

function create_order(int $userId, string $itemType, ?int $itemId, string $gateway, int $amountCents, string $currency = 'USD'): int
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO orders (user_id, item_type, item_id, gateway, amount_cents, currency) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $itemType, $itemId, $gateway, $amountCents, $currency]);
    return (int) $pdo->lastInsertId();
}

function mark_order_paid(int $orderId): void
{
    $pdo = db();
    $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$orderId]);
    $stmt = $pdo->prepare('SELECT invoice_number FROM invoices WHERE order_id = ?');
    $stmt->execute([$orderId]);
    if (!$stmt->fetch()) {
        $number = 'INV-' . date('Ymd') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO invoices (order_id, invoice_number) VALUES (?, ?)')->execute([$orderId, $number]);
    }
}
