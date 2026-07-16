<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$orderId = (int) ($_GET['order'] ?? 0);
$stmt = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

$pageTitle = 'Payment received — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-6 text-center">
  <h1 class="text-xl font-semibold mb-2">Thanks!</h1>
  <?php if (!$order): ?>
    <p class="text-sm text-slate-600">We couldn't find that order.</p>
  <?php elseif ($order['status'] === 'paid'): ?>
    <p class="text-sm text-slate-600">Your payment was confirmed and your order is now active.</p>
  <?php else: ?>
    <p class="text-sm text-slate-600">We've received your checkout — your order will activate as soon as the payment provider confirms it (usually within a minute).</p>
  <?php endif; ?>
  <a href="/" class="inline-block mt-4 text-indigo-600 hover:underline text-sm">Back to home</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
