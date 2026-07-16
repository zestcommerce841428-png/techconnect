<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Audit Log — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = paginate_offset($page, $perPage);

$logs = $pdo->prepare(
    "SELECT al.*, u.username FROM audit_log al JOIN users u ON u.id = al.admin_id
     ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset"
);
$logs->execute();
$logs = $logs->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Audit log</h1>
<div class="bg-white border rounded-lg overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-100 text-left">
      <tr><th class="p-3">When</th><th class="p-3">Admin</th><th class="p-3">Action</th><th class="p-3">Target</th><th class="p-3">Details</th><th class="p-3">IP</th></tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
        <tr class="border-t">
          <td class="p-3 text-slate-500"><?= time_ago($l['created_at']) ?></td>
          <td class="p-3"><?= e($l['username']) ?></td>
          <td class="p-3"><?= e($l['action']) ?></td>
          <td class="p-3"><?= e(($l['target_type'] ?? '') . ($l['target_id'] ? ' #' . $l['target_id'] : '')) ?></td>
          <td class="p-3 text-slate-500"><?= e($l['details'] ?? '') ?></td>
          <td class="p-3 text-slate-400"><?= e($l['ip_address'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if (!$logs): ?><p class="text-slate-500 mt-3">No actions logged yet.</p><?php endif; ?>
<div class="flex justify-between mt-4 text-sm">
  <?php if ($page > 1): ?><a class="text-indigo-600 hover:underline" href="?page=<?= $page - 1 ?>">&larr; Previous</a><?php else: ?><span></span><?php endif; ?>
  <?php if (count($logs) === $perPage): ?><a class="text-indigo-600 hover:underline" href="?page=<?= $page + 1 ?>">Next &rarr;</a><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
