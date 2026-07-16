<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Jobs — Admin';
require __DIR__ . '/includes/admin_header.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission($admin, 'manage_jobs');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $jobId = (int) $_POST['job_id'];
    $status = in_array($_POST['status'], ['pending', 'active', 'closed'], true) ? $_POST['status'] : 'pending';
    $pdo->prepare('UPDATE jobs SET status = ? WHERE id = ?')->execute([$status, $jobId]);
    flash_set('success', 'Job updated.');
    redirect('/admin/jobs.php');
}

$jobs = $pdo->query(
    'SELECT j.*, u.username FROM jobs j JOIN users u ON u.id = j.posted_by ORDER BY j.created_at DESC LIMIT 50'
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Job listings</h1>
<div class="space-y-3">
  <?php foreach ($jobs as $j): ?>
    <div class="bg-white border rounded-lg p-4 flex items-center justify-between gap-4">
      <div>
        <div class="font-medium"><?= e($j['title']) ?> <span class="text-xs text-slate-500">by <?= e($j['username']) ?></span></div>
        <div class="text-xs text-slate-500"><?= e($j['company'] ?? '') ?> &middot; <?= e($j['location'] ?? '') ?> &middot; status: <?= e($j['status']) ?></div>
      </div>
      <form method="post" class="flex gap-1">
        <?= csrf_field() ?>
        <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
        <select name="status" class="border rounded text-xs">
          <?php foreach (['pending', 'active', 'closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $j['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <button class="text-xs text-indigo-600 hover:underline">Save</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
