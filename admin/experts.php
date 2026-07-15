<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Experts — Admin';
require __DIR__ . '/includes/admin_header.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission($admin, 'manage_experts');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        $pdo->prepare('UPDATE expert_profiles SET is_approved = 1 WHERE user_id = ?')->execute([$userId]);
        audit_log($admin['id'], 'expert_approved', 'user', $userId);
    } elseif ($action === 'reject') {
        $pdo->prepare('DELETE FROM expert_profiles WHERE user_id = ?')->execute([$userId]);
        audit_log($admin['id'], 'expert_rejected', 'user', $userId);
    }
    flash_set('success', 'Updated.');
    redirect('/admin/experts');
}

$rows = $pdo->query(
    "SELECT ep.*, u.username FROM expert_profiles ep JOIN users u ON u.id = ep.user_id ORDER BY ep.is_approved ASC, ep.created_at DESC"
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Expert marketplace applications</h1>
<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($rows as $r): ?>
    <div class="p-4 flex items-center justify-between gap-4">
      <div>
        <div class="font-medium"><?= e($r['username']) ?> <?= $r['is_approved'] ? '<span class="text-xs text-green-700">approved</span>' : '<span class="text-xs text-amber-700">pending</span>' ?></div>
        <div class="text-sm text-slate-600"><?= e($r['headline'] ?? '') ?></div>
      </div>
      <form method="post" class="flex gap-2 shrink-0">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>">
        <?php if (!$r['is_approved']): ?>
          <button type="submit" name="action" value="approve" class="text-sm bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded">Approve</button>
        <?php endif; ?>
        <button type="submit" name="action" value="reject" class="text-sm bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded">Remove</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="p-4 text-sm text-slate-500">No applications yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
