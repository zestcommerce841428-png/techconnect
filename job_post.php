<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $isRemote = isset($_POST['is_remote']) ? 1 : 0;

    if (mb_strlen($title) < 5 || mb_strlen($title) > 150) {
        $errors[] = 'Title must be between 5 and 150 characters.';
    }
    if (mb_strlen($description) < 30) {
        $errors[] = 'Please provide a fuller description (30+ characters).';
    } elseif (!rate_limit('post_job', 5, 86400)) {
        $errors[] = 'You have reached the daily job-posting limit.';
    }

    if (!$errors) {
        $slug = unique_slug('jobs', $title);
        // New listings start pending and are reviewed by an admin before going live.
        db()->prepare(
            'INSERT INTO jobs (posted_by, title, slug, description, company, location, is_remote, status) VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
        )->execute([$user['id'], $title, $slug, $description, $company ?: null, $location ?: null, $isRemote]);
        flash_set('success', 'Your listing was submitted and is pending review.');
        redirect('/jobs.php');
    }
}

$pageTitle = 'Post a job — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto bg-white border rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-4">Post a job or gig</h1>
  <?php foreach ($errors as $err): ?>
    <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post" class="space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium mb-1">Title</label>
      <input type="text" name="title" required maxlength="150" class="w-full border rounded px-3 py-2" value="<?= e($_POST['title'] ?? '') ?>">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Description</label>
      <textarea name="description" required rows="6" class="w-full border rounded px-3 py-2"><?= e($_POST['description'] ?? '') ?></textarea>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium mb-1">Company (optional)</label>
        <input type="text" name="company" class="w-full border rounded px-3 py-2" value="<?= e($_POST['company'] ?? '') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Location (optional)</label>
        <input type="text" name="location" class="w-full border rounded px-3 py-2" value="<?= e($_POST['location'] ?? '') ?>">
      </div>
    </div>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="is_remote">
      Remote position
    </label>
    <p class="text-xs text-slate-500">Listings are reviewed before appearing publicly.</p>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Submit listing</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
