<?php
/** Grants whatever an order paid for. Called only from verified webhook handlers. */
function fulfill_order(int $orderId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || $order['status'] === 'paid') return;

    mark_order_paid($orderId);

    switch ($order['item_type']) {
        case 'job_listing':
            $pdo->prepare("UPDATE jobs SET status = 'active', is_paid_listing = 1 WHERE id = ?")->execute([$order['item_id']]);
            break;
        case 'pro_membership':
            $pdo->prepare('UPDATE users SET is_pro = 1, pro_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?')->execute([$order['user_id']]);
            $pdo->prepare('INSERT INTO subscriptions (user_id, plan, gateway, status, current_period_end) VALUES (?, "pro_monthly", ?, "active", DATE_ADD(NOW(), INTERVAL 30 DAY))')
                ->execute([$order['user_id'], $order['gateway']]);
            break;
        case 'consultation':
            $pdo->prepare("UPDATE consultations SET status = 'confirmed' WHERE order_id = ?")->execute([$orderId]);
            $c = $pdo->prepare('SELECT id, expert_id FROM consultations WHERE order_id = ?');
            $c->execute([$orderId]);
            $consultation = $c->fetch();
            if ($consultation) {
                $feePct = (float) setting('expert_platform_fee_pct', '15');
                $gross = (int) $order['amount_cents'];
                $fee = (int) round($gross * $feePct / 100);
                $net = $gross - $fee;
                $pdo->prepare(
                    'INSERT IGNORE INTO expert_payouts (expert_id, consultation_id, gross_amount_cents, platform_fee_cents, net_amount_cents, currency) VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$consultation['expert_id'], $consultation['id'], $gross, $fee, $net, $order['currency']]);
            }
            break;
    }

    require_once __DIR__ . '/../webhooks.php';
    fire_webhook('order.paid', ['order_id' => $orderId, 'item_type' => $order['item_type'], 'amount_cents' => (int) $order['amount_cents'], 'currency' => $order['currency']]);
}
