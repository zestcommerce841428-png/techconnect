<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Checkout cancelled — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-md mx-auto bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-6 text-center">
  <h1 class="text-xl font-semibold mb-2">Checkout cancelled</h1>
  <p class="text-sm text-slate-600">No charge was made. You can try again any time.</p>
  <a href="/" class="inline-block mt-4 text-indigo-600 hover:underline text-sm">Back to home</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
