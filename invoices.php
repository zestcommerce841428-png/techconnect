<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$stmt = db()->prepare(
    "SELECT o.id, o.item_type, o.amount_cents, o.currency, o.gateway, o.paid_at, i.invoice_number
     FROM orders o LEFT JOIN invoices i ON i.order_id = o.id
     WHERE o.user_id = ? AND o.status = 'paid' ORDER BY o.paid_at DESC"
);
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$labels = ['job_listing' => 'Featured job listing', 'pro_membership' => 'Pro membership', 'consultation' => 'Consultation'];

$pageTitle = 'Invoices — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-4">Your invoices</h1>
<div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y">
  <?php foreach ($orders as $o): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <div>
        <div class="font-medium"><?= e($labels[$o['item_type']] ?? $o['item_type']) ?></div>
        <div class="text-slate-500"><?= e($o['invoice_number'] ?? ('order-' . $o['id'])) ?> · <?= e(ucfirst($o['gateway'])) ?> · <?= e(date('M j, Y', strtotime($o['paid_at']))) ?></div>
      </div>
      <div class="font-semibold"><?= e(number_format($o['amount_cents'] / 100, 2)) ?> <?= e($o['currency']) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$orders): ?><div class="p-4 text-sm text-slate-500">No paid orders yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
