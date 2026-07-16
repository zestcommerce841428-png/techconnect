<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM expert_profiles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$existing = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $headline = trim($_POST['headline'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $rate = max(0, (int) ($_POST['hourly_rate'] ?? 0)) * 100;

    if (mb_strlen($headline) < 5) {
        flash_set('error', 'Please provide a headline (5+ characters).');
    } else {
        $pdo->prepare(
            'INSERT INTO expert_profiles (user_id, headline, bio, hourly_rate_cents, is_approved) VALUES (?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE headline = VALUES(headline), bio = VALUES(bio), hourly_rate_cents = VALUES(hourly_rate_cents), is_approved = 0'
        )->execute([$user['id'], $headline, $bio ?: null, $rate ?: null]);
        flash_set('success', 'Your expert profile was submitted for admin approval.');
        redirect('/expert_apply');
    }
}

$pageTitle = 'Become an expert — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-lg mx-auto bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-1">Expert profile</h1>
  <?php if ($existing): ?>
    <p class="text-sm mb-4 <?= $existing['is_approved'] ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' ?>">
      <?= $existing['is_approved'] ? 'Your profile is live in the expert marketplace.' : 'Your profile is pending admin approval.' ?>
    </p>
  <?php endif; ?>
  <form method="post" class="space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium mb-1">Headline</label>
      <input type="text" name="headline" required maxlength="150" class="w-full border rounded px-3 py-2" value="<?= e($existing['headline'] ?? '') ?>">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Bio</label>
      <textarea name="bio" rows="5" class="w-full border rounded px-3 py-2"><?= e($existing['bio'] ?? '') ?></textarea>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Hourly rate (<?= e(setting('currency', 'USD')) ?>)</label>
      <input type="number" name="hourly_rate" min="0" class="w-32 border rounded px-3 py-2" value="<?= isset($existing['hourly_rate_cents']) ? (int) ($existing['hourly_rate_cents'] / 100) : '' ?>">
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded"><?= $existing ? 'Update profile' : 'Submit for approval' ?></button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
