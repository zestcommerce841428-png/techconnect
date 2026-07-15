<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

if (isset($_GET['export'])) {
    $data = [
        'user' => (function () use ($pdo, $user) {
            $s = $pdo->prepare('SELECT username, email, bio, location_city, location_country, reputation, created_at FROM users WHERE id = ?');
            $s->execute([$user['id']]);
            return $s->fetch();
        })(),
        'questions' => (function () use ($pdo, $user) {
            $s = $pdo->prepare('SELECT title, body, created_at FROM questions WHERE user_id = ?');
            $s->execute([$user['id']]);
            return $s->fetchAll();
        })(),
        'answers' => (function () use ($pdo, $user) {
            $s = $pdo->prepare('SELECT body, created_at FROM answers WHERE user_id = ?');
            $s->execute([$user['id']]);
            return $s->fetchAll();
        })(),
        'comments' => (function () use ($pdo, $user) {
            $s = $pdo->prepare('SELECT body, created_at FROM comments WHERE user_id = ?');
            $s->execute([$user['id']]);
            return $s->fetchAll();
        })(),
    ];
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="my-data-export.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_account') {
    verify_csrf();
    if (!password_verify($_POST['password'] ?? '', (function () use ($pdo, $user) {
        $s = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $s->execute([$user['id']]);
        return $s->fetchColumn();
    })())) {
        $errors[] = 'Incorrect password.';
    } else {
        // Anonymize instead of hard-deleting so existing Q&A content (which
        // others may be relying on) stays intact, matching Stack-Overflow-style
        // "deleted user" behavior rather than cascading destructive deletes.
        $anonUsername = 'deleted_user_' . $user['id'];
        $anonEmail = 'deleted+' . $user['id'] . '@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'example.com');
        $pdo->prepare(
            'UPDATE users SET username = ?, email = ?, password_hash = ?, bio = NULL,
             avatar = NULL, location_city = NULL, location_country = NULL, totp_secret = NULL, totp_enabled = 0 WHERE id = ?'
        )->execute([$anonUsername, $anonEmail, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $user['id']]);
        logout_user();
        flash_set('success', 'Your account has been deleted.');
        redirect('/');
    }
}

$pageTitle = 'Your data — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-lg mx-auto space-y-6">
  <div class="card p-6">
    <h1 class="text-xl font-semibold mb-1">Export your data</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Download a JSON file with your profile, questions, answers, and comments.</p>
    <a href="/account_data?export=1" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-medium">Download my data</a>
  </div>

  <div class="card p-6 border-red-200 dark:border-red-900">
    <h2 class="text-lg font-semibold mb-1 text-red-700 dark:text-red-400">Delete account</h2>
    <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Your username and personal info are removed. Questions and answers you've posted stay (attributed to "deleted user") so other people's threads aren't broken.</p>
    <?php foreach ($errors as $err): ?>
      <div class="mb-3 rounded-lg border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" onsubmit="return confirm('This cannot be undone. Continue?');" class="flex gap-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_account">
      <input type="password" name="password" required placeholder="Confirm password" class="flex-1 border rounded-lg px-3 py-2 text-sm">
      <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-medium">Delete</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
