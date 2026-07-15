<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

$expertId = (int) ($_GET['expert'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT u.id, u.username, ep.headline, ep.hourly_rate_cents FROM expert_profiles ep
     JOIN users u ON u.id = ep.user_id WHERE ep.user_id = ? AND ep.is_approved = 1"
);
$stmt->execute([$expertId]);
$expert = $stmt->fetch();

if (!$expert) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
if ($expertId === $user['id']) {
    flash_set('error', "You can't book a consultation with yourself.");
    redirect('/experts');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $message = trim($_POST['message'] ?? '');
    $pdo->prepare('INSERT INTO consultations (expert_id, requester_id, message, status) VALUES (?, ?, ?, "requested")')
        ->execute([$expertId, $user['id'], $message ?: null]);
    flash_set('success', 'Your request was sent. The expert will confirm availability and pricing before any payment is taken.');
    redirect('/experts');
}

$pageTitle = 'Request a consultation — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-lg mx-auto bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-1">Request a consultation with <?= e($expert['username']) ?></h1>
  <p class="text-sm text-slate-500 mb-4"><?= e($expert['headline']) ?><?= $expert['hourly_rate_cents'] ? ' — ' . e(number_format($expert['hourly_rate_cents'] / 100, 2)) . '/hr' : '' ?></p>
  <form method="post" class="space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium mb-1">What do you need help with?</label>
      <textarea name="message" rows="5" required class="w-full border rounded px-3 py-2"></textarea>
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Send request</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
