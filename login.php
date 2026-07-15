<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) redirect('/index.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!rate_limit('login', 8, 300)) {
        $errors[] = 'Too many login attempts. Please wait a few minutes.';
    } else {
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$identity, $identity]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password_hash'])) {
            login_user((int) $row['id']);
            redirect('/index.php');
        }
        $errors[] = 'Incorrect username/email or password.';
    }
}

$pageTitle = 'Log in — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-sm mx-auto bg-white border rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-4">Log in</h1>
  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium mb-1">Username or email</label>
      <input type="text" name="identity" required class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Password</label>
      <input type="password" name="password" required class="w-full border rounded px-3 py-2">
    </div>
    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded px-3 py-2">Log in</button>
  </form>
  <p class="text-sm mt-4 flex justify-between">
    <a href="/register.php" class="text-indigo-600 hover:underline">Create account</a>
    <a href="/forgot_password.php" class="text-indigo-600 hover:underline">Forgot password?</a>
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
