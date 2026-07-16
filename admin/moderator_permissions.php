<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Moderator Permissions — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$permKeys = permission_defs();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'moderator'");
    $check->execute([$userId]);
    if (!$check->fetchColumn()) {
        flash_set('error', 'That user is not a moderator.');
        redirect('/admin/moderator_permissions');
    }
    $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);
    $granted = array_intersect($_POST['permissions'] ?? [], array_keys($permKeys));
    $ins = $pdo->prepare('INSERT INTO user_permissions (user_id, permission_key, granted_by) VALUES (?, ?, ?)');
    foreach ($granted as $p) {
        $ins->execute([$userId, $p, $admin['id']]);
    }
    audit_log($admin['id'], 'moderator_permissions_updated', 'user', $userId, implode(',', $granted));
    flash_set('success', 'Permissions updated.');
    redirect('/admin/moderator_permissions');
}

$moderators = $pdo->query("SELECT id, username FROM users WHERE role = 'moderator' ORDER BY username")->fetchAll();
$grants = [];
foreach ($pdo->query('SELECT user_id, permission_key FROM user_permissions')->fetchAll() as $row) {
    $grants[$row['user_id']][] = $row['permission_key'];
}
?>
<h1 class="text-2xl font-bold mb-2">Moderator permissions</h1>
<p class="text-sm text-slate-600 mb-6">Give individual moderators extra abilities without making them full admins. Admins always have every permission.</p>

<?php if (!$moderators): ?>
  <p class="text-sm text-slate-500">No moderators yet — promote a user to moderator in <a href="/admin/users" class="text-indigo-600 hover:underline">Users</a> first.</p>
<?php endif; ?>

<div class="space-y-4 max-w-xl">
  <?php foreach ($moderators as $m): $userGrants = $grants[$m['id']] ?? []; ?>
    <div class="bg-white border rounded-lg p-4">
      <h2 class="font-semibold text-sm mb-2"><?= e($m['username']) ?></h2>
      <form method="post" class="space-y-2">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
        <?php foreach ($permKeys as $key => $label): ?>
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="permissions[]" value="<?= e($key) ?>" <?= in_array($key, $userGrants, true) ? 'checked' : '' ?>>
            <?= e($label) ?>
          </label>
        <?php endforeach; ?>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded text-sm">Save</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
