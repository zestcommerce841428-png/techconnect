<?php
require_once __DIR__ . '/includes/auth.php';

$token = $_GET['token'] ?? '';
$done = false;
if ($token) {
    $stmt = db()->prepare('UPDATE newsletter_subscribers SET unsubscribed_at = NOW() WHERE unsubscribe_token = ?');
    $stmt->execute([$token]);
    $done = $stmt->rowCount() > 0;
}

$pageTitle = 'Unsubscribe — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-sm mx-auto card p-6 text-center">
  <?php if ($done): ?>
    <h1 class="text-xl font-semibold mb-2">You're unsubscribed</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400">You won't receive any more newsletter emails from us.</p>
  <?php else: ?>
    <h1 class="text-xl font-semibold mb-2">Link not found</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400">This unsubscribe link is invalid or already used.</p>
  <?php endif; ?>
  <a href="/" class="inline-block mt-4 text-indigo-600 hover:underline text-sm">Back to home</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
