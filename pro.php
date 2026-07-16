<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payments/payments.php';

$user = current_user();
$priceCents = (int) setting('pro_membership_price_cents', '500');
$currency = setting('currency', 'USD');
$gatewaysAvailable = payment_available_gateways();

$pageTitle = 'Go Pro — ' . SITE_NAME;
$pageDescription = 'Unlock Pro features on ' . SITE_NAME . ': ad-free browsing, priority support, and a Pro badge.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-lg mx-auto bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-8 text-center">
  <h1 class="text-2xl font-bold mb-2">Go Pro</h1>
  <p class="text-3xl font-extrabold mb-1"><?= e(format_money($priceCents)) ?><span class="text-sm font-normal text-slate-500">/month</span></p>
  <?php if (display_currency() !== $currency): ?><p class="text-xs text-slate-500 mb-1">Charged as <?= e(number_format($priceCents / 100, 2)) ?> <?= e($currency) ?> — shown converted to <?= e(display_currency()) ?></p><?php endif; ?>
  <ul class="text-sm text-slate-600 dark:text-slate-300 my-6 space-y-2 text-left max-w-xs mx-auto">
    <li>✓ Ad-free browsing</li>
    <li>✓ Pro badge on your profile</li>
    <li>✓ Priority support in Inbox</li>
    <li>✓ Early access to new features</li>
  </ul>
  <?php if (!$user): ?>
    <a href="/login" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-2 rounded">Log in to subscribe</a>
  <?php elseif (!empty($user['is_pro'])): ?>
    <p class="text-green-700 dark:text-green-400 font-medium">You're already a Pro member.</p>
  <?php elseif (!$gatewaysAvailable): ?>
    <p class="text-sm text-slate-500">Pro membership isn't available for purchase yet — check back soon.</p>
  <?php else: ?>
    <a href="/checkout?item_type=pro_membership" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-2 rounded">Subscribe now</a>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
