<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$jobs = $pdo->query(
    "SELECT j.*, u.username FROM jobs j JOIN users u ON u.id = j.posted_by
     WHERE j.status = 'active' ORDER BY j.is_paid_listing DESC, j.created_at DESC LIMIT 50"
)->fetchAll();

$pageTitle = 'Jobs & Gigs — ' . SITE_NAME;
$pageDescription = 'Tech jobs and freelance gigs posted by the ' . SITE_NAME . ' community.';
require __DIR__ . '/includes/header.php';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-bold">Jobs &amp; gigs</h1>
  <a href="/job_post" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Post a job</a>
</div>

<?php if (!$jobs): ?>
  <div class="border rounded-lg p-8 text-center bg-white text-slate-600">No open listings right now.</div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($jobs as $j): ?>
      <div class="bg-white border rounded-lg p-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="font-medium">
              <?= e($j['title']) ?>
              <?php if ($j['is_paid_listing']): ?><span class="ml-1 text-xs bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded">Featured</span><?php endif; ?>
            </h3>
            <div class="text-xs text-slate-500 mt-1">
              <?= e($j['company'] ?: 'Independent') ?>
              <?php if ($j['location'] || $j['is_remote']): ?> &middot; <?= e($j['is_remote'] ? 'Remote' : $j['location']) ?><?php endif; ?>
              &middot; posted by <?= e($j['username']) ?> &middot; <?= time_ago($j['created_at']) ?>
            </div>
            <p class="text-sm text-slate-700 mt-2"><?= nl2br(e(mb_substr($j['description'], 0, 300))) ?><?= mb_strlen($j['description']) > 300 ? '…' : '' ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
