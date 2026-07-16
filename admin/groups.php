<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Groups — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $groupId = (int) ($_POST['group_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM groups_tbl WHERE id = ?')->execute([$groupId]);
        audit_log($admin['id'], 'group_deleted', 'group', $groupId);
        flash_set('success', 'Group deleted. Its questions were kept and returned to the public site.');
    }
    redirect('/admin/groups');
}

$groups = $pdo->query(
    "SELECT g.*, u.username AS owner_name,
            (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS member_count,
            (SELECT COUNT(*) FROM questions q WHERE q.group_id = g.id) AS question_count
     FROM groups_tbl g JOIN users u ON u.id = g.created_by ORDER BY g.created_at DESC LIMIT 200"
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Groups</h1>
<div class="bg-white border rounded-lg overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-left"><tr>
      <th class="p-3">Name</th><th class="p-3">Owner</th><th class="p-3">Privacy</th>
      <th class="p-3">Members</th><th class="p-3">Questions</th><th class="p-3">Created</th><th class="p-3"></th>
    </tr></thead>
    <tbody class="divide-y">
      <?php foreach ($groups as $g): ?>
      <tr>
        <td class="p-3"><a href="/g/<?= e($g['slug']) ?>" class="text-indigo-600 hover:underline"><?= e($g['name']) ?></a></td>
        <td class="p-3"><?= e($g['owner_name']) ?></td>
        <td class="p-3"><?= $g['is_private'] ? '🔒 Private' : 'Public' ?></td>
        <td class="p-3"><?= (int) $g['member_count'] ?></td>
        <td class="p-3"><?= (int) $g['question_count'] ?></td>
        <td class="p-3 text-slate-500"><?= e(date('M j, Y', strtotime($g['created_at']))) ?></td>
        <td class="p-3">
          <form method="post" onsubmit="return confirm('Delete this group? Members and group scoping are removed; questions become public.');">
            <?= csrf_field() ?>
            <input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>">
            <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$groups): ?><div class="p-4 text-sm text-slate-500">No groups yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
