<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Terms of Service — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto bg-white border rounded-lg p-6 prose prose-slate">
  <h1 class="text-2xl font-bold mb-4">Terms of Service</h1>
  <p>By using <?= e(SITE_NAME) ?> you agree to post respectful, on-topic content, not to
  spam or abuse other members, and that content you post may be publicly visible and
  indexed by search engines. We may remove content or accounts that violate these terms.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
