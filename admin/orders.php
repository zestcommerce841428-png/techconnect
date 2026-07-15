<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Orders — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'refund') {
        // Marks the order refunded locally; issuing the actual refund with the gateway
        // is a manual step in that gateway's dashboard until a refund API call is wired up.
        $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([$orderId]);
        audit_log($admin['id'], 'order_marked_refunded', 'order', $orderId);
        flash_set('success', 'Order marked refunded.');
    }
    redirect('/admin/orders');
}

$orders = $pdo->query(
    "SELECT o.*, u.username FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 100"
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Orders</h1>
<div class="bg-white border rounded-lg overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-left"><tr>
      <th class="p-3">User</th><th class="p-3">Item</th><th class="p-3">Gateway</th>
      <th class="p-3">Amount</th><th class="p-3">Status</th><th class="p-3">Date</th><th class="p-3"></th>
    </tr></thead>
    <tbody class="divide-y">
      <?php foreach ($orders as $o): ?>
      <tr>
        <td class="p-3"><?= e($o['username']) ?></td>
        <td class="p-3"><?= e($o['item_type']) ?><?= $o['item_id'] ? ' #' . (int) $o['item_id'] : '' ?></td>
        <td class="p-3 capitalize"><?= e($o['gateway']) ?></td>
        <td class="p-3"><?= e(number_format($o['amount_cents'] / 100, 2)) ?> <?= e($o['currency']) ?></td>
        <td class="p-3"><span class="px-2 py-0.5 rounded text-xs
          <?= $o['status'] === 'paid' ? 'bg-green-100 text-green-700' : ($o['status'] === 'refunded' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-700') ?>">
          <?= e($o['status']) ?></span></td>
        <td class="p-3 text-slate-500"><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
        <td class="p-3">
          <?php if ($o['status'] === 'paid'): ?>
            <form method="post" onsubmit="return confirm('Mark this order refunded? This does not call the gateway automatically.');">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
              <button type="submit" name="action" value="refund" class="text-xs text-red-600 hover:underline">Mark refunded</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$orders): ?><div class="p-4 text-sm text-slate-500">No orders yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
