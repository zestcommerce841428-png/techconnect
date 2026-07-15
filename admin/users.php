<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Users — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int) $_POST['user_id'];
    if ($admin['role'] === 'admin' && $_POST['action'] === 'set_role') {
        $role = in_array($_POST['role'], ['user', 'moderator', 'admin'], true) ? $_POST['role'] : 'user';
        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $userId]);
        audit_log($admin['id'], 'set_role', 'user', $userId, "role={$role}");
        flash_set('success', 'Role updated.');
    } elseif ($_POST['action'] === 'ban') {
        // Soft-ban by clearing password hash so login is impossible until reset by admin
        $pdo->prepare("UPDATE users SET password_hash = CONCAT('!banned:', password_hash) WHERE id = ?")->execute([$userId]);
        audit_log($admin['id'], 'ban_user', 'user', $userId);
        flash_set('success', 'User banned.');
    }
    redirect('/admin/users');
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT id, username, email, role, reputation, created_at FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT 50');
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query('SELECT id, username, email, role, reputation, created_at FROM users ORDER BY created_at DESC LIMIT 50');
}
$users = $stmt->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Users</h1>
<form method="get" class="mb-4">
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by username or email" class="border rounded px-3 py-2 w-full max-w-sm">
</form>
<div class="bg-white border rounded-lg overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-100 text-left">
      <tr><th class="p-3">Username</th><th class="p-3">Email</th><th class="p-3">Role</th><th class="p-3">Rep</th><th class="p-3">Joined</th><th class="p-3">Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr class="border-t">
          <td class="p-3"><a href="/profile?u=<?= $u['id'] ?>" class="text-indigo-600 hover:underline"><?= e($u['username']) ?></a></td>
          <td class="p-3"><?= e($u['email']) ?></td>
          <td class="p-3"><?= e($u['role']) ?></td>
          <td class="p-3"><?= (int) $u['reputation'] ?></td>
          <td class="p-3"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
          <td class="p-3 flex gap-2">
            <?php if ($admin['role'] === 'admin'): ?>
              <form method="post" class="flex gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_role">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <select name="role" class="border rounded text-xs">
                  <?php foreach (['user', 'moderator', 'admin'] as $r): ?>
                    <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="text-xs text-indigo-600 hover:underline">Save</button>
              </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Ban this user?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="ban">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="text-xs text-red-600 hover:underline">Ban</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
