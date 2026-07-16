<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payments/payments.php';

$user = require_login();

$itemType = $_GET['item_type'] ?? '';
$itemId = isset($_GET['item_id']) ? (int) $_GET['item_id'] : null;
$currency = setting('currency', 'USD');

$catalog = [
    'job_listing' => ['label' => 'Featured job listing', 'amount' => (int) setting('job_listing_price_cents', '2000')],
    'pro_membership' => ['label' => 'Pro membership (monthly)', 'amount' => (int) setting('pro_membership_price_cents', '500')],
];

// Ownership + dynamic pricing check for item-scoped purchases.
if ($itemType === 'job_listing') {
    if (!isset($catalog[$itemType])) { http_response_code(404); require __DIR__ . '/404.php'; exit; }
    $stmt = db()->prepare('SELECT posted_by FROM jobs WHERE id = ?');
    $stmt->execute([$itemId]);
    $owner = $stmt->fetchColumn();
    if ($owner === false || (int) $owner !== $user['id']) {
        http_response_code(403);
        exit('Forbidden');
    }
} elseif ($itemType === 'consultation') {
    $stmt = db()->prepare("SELECT c.*, u.username AS expert_username FROM consultations c JOIN users u ON u.id = c.expert_id WHERE c.id = ?");
    $stmt->execute([$itemId]);
    $consultation = $stmt->fetch();
    if (!$consultation || (int) $consultation['requester_id'] !== $user['id'] || !$consultation['quoted_amount_cents'] || $consultation['status'] !== 'requested') {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
    $catalog['consultation'] = ['label' => 'Consultation with ' . $consultation['expert_username'], 'amount' => (int) $consultation['quoted_amount_cents']];
} elseif (!isset($catalog[$itemType])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$gateways = payment_available_gateways();
$errors = [];
$checkoutResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $gateway = $_POST['gateway'] ?? '';
    if (!in_array($gateway, $gateways, true)) {
        $errors[] = 'Please choose a valid payment method.';
    } else {
        $orderId = create_order($user['id'], $itemType, $itemId, $gateway, $catalog[$itemType]['amount'], $currency);
        if ($itemType === 'consultation') {
            db()->prepare('UPDATE consultations SET order_id = ? WHERE id = ?')->execute([$orderId, $itemId]);
        }
        $order = ['id' => $orderId, 'amount_cents' => $catalog[$itemType]['amount'], 'currency' => $currency,
                  'item_type' => $itemType, 'description' => $catalog[$itemType]['label']];
        try {
            $instance = payment_gateway_instance($gateway);
            $checkoutResult = $instance->createCheckout($order, payment_gateway_config($gateway));
            if (!empty($checkoutResult['redirect_url'])) {
                redirect($checkoutResult['redirect_url']);
            }
        } catch (Throwable $e) {
            $errors[] = 'Could not start checkout: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-1"><?= e($catalog[$itemType]['label']) ?></h1>
  <p class="text-2xl font-bold mb-1"><?= e(number_format($catalog[$itemType]['amount'] / 100, 2)) ?> <?= e($currency) ?></p>
  <?php if (display_currency() !== $currency): ?>
    <p class="text-xs text-slate-500 mb-4">≈ <?= e(format_money($catalog[$itemType]['amount'])) ?> — you'll be charged in <?= e($currency) ?>, the only currency <?= e(SITE_NAME) ?> settles in.</p>
  <?php else: ?>
    <div class="mb-4"></div>
  <?php endif; ?>

  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>

  <?php if (!$gateways): ?>
    <p class="text-sm text-slate-600">No payment methods are configured yet. Please check back soon.</p>
  <?php elseif ($checkoutResult && !empty($checkoutResult['client_config'])): ?>
    <div id="razorpay-checkout" class="text-sm text-slate-600">Preparing secure checkout…</div>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
      var cfg = <?= json_encode($checkoutResult['client_config']) ?>;
      var rzp = new Razorpay({
        key: cfg.key, order_id: cfg.order_id, amount: cfg.amount, currency: cfg.currency,
        name: <?= json_encode(SITE_NAME) ?>,
        handler: function () { window.location = '/checkout/success?order=<?= (int) $orderId ?>'; },
        modal: { ondismiss: function () { window.location = '/checkout/cancel?order=<?= (int) $orderId ?>'; } }
      });
      rzp.open();
    </script>
  <?php else: ?>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <p class="text-sm font-medium">Choose a payment method:</p>
      <?php foreach ($gateways as $gw): ?>
        <button type="submit" name="gateway" value="<?= e($gw) ?>" class="w-full border rounded px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 text-left capitalize"><?= e($gw) ?></button>
      <?php endforeach; ?>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
