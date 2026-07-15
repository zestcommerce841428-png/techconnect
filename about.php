<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'About — ' . SITE_NAME;
$pageDescription = 'Learn about ' . SITE_NAME . ', a global and local community for solving tech problems together.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto bg-white border rounded-lg p-6">
  <h1 class="text-2xl font-bold mb-4">About <?= e(SITE_NAME) ?></h1>
  <p class="text-slate-700 leading-relaxed">
    <?= e(SITE_NAME) ?> connects people worldwide — and nearby — to ask, answer, and solve
    real tech problems together. Whether you're debugging code, fixing hardware, or
    navigating a tricky setup, post your question and get help from people who've been there.
  </p>
  <p class="text-slate-700 leading-relaxed mt-3">
    Filter by tag, by your country, or by your city to find answers relevant to you, or
    help someone near you directly.
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
