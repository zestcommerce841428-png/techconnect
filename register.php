<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) redirect('/index.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!rate_limit('register', 5, 600)) {
        $errors[] = 'Too many attempts. Please try again later.';
    }
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3-30 characters (letters, numbers, underscore).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'That username or email is already registered.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        login_user((int) db()->lastInsertId());
        flash_set('success', 'Welcome to ' . SITE_NAME . '!');
        redirect('/index.php');
    }
}

$pageTitle = 'Join ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-sm mx-auto bg-white border rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-4">Create your account</h1>
  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium mb-1">Username</label>
      <input type="text" name="username" required class="w-full border rounded px-3 py-2" value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Email</label>
      <input type="email" name="email" required class="w-full border rounded px-3 py-2" value="<?= e($_POST['email'] ?? '') ?>">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Password</label>
      <input type="password" name="password" required minlength="8" class="w-full border rounded px-3 py-2">
    </div>
    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded px-3 py-2">Create account</button>
  </form>
  <p class="text-sm mt-4">Already have an account? <a href="/login.php" class="text-indigo-600 hover:underline">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
