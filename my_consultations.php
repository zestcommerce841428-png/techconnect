<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

$rows = $pdo->prepare(
    "SELECT c.*, u.username AS expert_name FROM consultations c JOIN users u ON u.id = c.expert_id
     WHERE c.requester_id = ? ORDER BY FIELD(c.status,'requested','confirmed','completed','cancelled'), c.created_at DESC"
);
$rows->execute([$user['id']]);
$consultations = $rows->fetchAll();

$statusColor = ['requested' => 'bg-amber-100 text-amber-700', 'confirmed' => 'bg-blue-100 text-blue-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-slate-100 text-slate-600'];

$pageTitle = 'My consultation requests — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <h1 class="text-2xl font-bold mb-1">My consultation requests</h1>
  <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Track requests you've sent to experts and pay once they've quoted a price.</p>

  <div class="space-y-3">
    <?php foreach ($consultations as $c): ?>
      <div class="card p-4 text-sm">
        <div class="flex items-center justify-between gap-2 mb-1">
          <div class="font-medium">With <a href="/u/<?= rawurlencode($c['expert_name']) ?>" class="text-indigo-600 hover:underline"><?= e($c['expert_name']) ?></a></div>
          <span class="text-xs px-2 py-0.5 rounded-full <?= $statusColor[$c['status']] ?>"><?= ucfirst($c['status']) ?></span>
        </div>
        <?php if ($c['message']): ?><p class="text-slate-600 dark:text-slate-400 mb-2"><?= e($c['message']) ?></p><?php endif; ?>
        <?php if ($c['status'] === 'requested' && $c['quoted_amount_cents']): ?>
          <p class="text-sm mb-2">Quoted price: <strong><?= e(number_format($c['quoted_amount_cents'] / 100, 2)) ?></strong><?= $c['scheduled_at'] ? ' · proposed for ' . e(date('M j, Y g:i A', strtotime($c['scheduled_at']))) : '' ?></p>
          <a href="/checkout?item_type=consultation&item_id=<?= (int) $c['id'] ?>" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded text-xs">Pay & confirm</a>
        <?php elseif ($c['status'] === 'requested'): ?>
          <p class="text-xs text-slate-500">Waiting on the expert to send a price quote.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$consultations): ?><p class="text-sm text-slate-500">You haven't requested any consultations yet. Browse <a href="/experts" class="text-indigo-600 hover:underline">experts</a>.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
