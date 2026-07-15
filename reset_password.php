<?php
require_once __DIR__ . '/includes/auth.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$stmt = db()->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
$stmt->execute([$token]);
$row = $stmt->fetch();

$errors = [];
if (!$row) {
    $errors[] = 'This reset link is invalid or has expired.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } else {
        $upd = db()->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
        $upd->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        flash_set('success', 'Password updated. Please log in.');
        redirect('/login.php');
    }
}

$pageTitle = 'Set new password — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-sm mx-auto bg-white border rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-4">Set a new password</h1>
  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>
  <?php if ($row): ?>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div>
        <label class="block text-sm font-medium mb-1">New password</label>
        <input type="password" name="password" required minlength="8" class="w-full border rounded px-3 py-2">
      </div>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded px-3 py-2">Update password</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
