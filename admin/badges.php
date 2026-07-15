<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/badges.php';
$pageTitle = 'Badges — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['code'] ?? '')));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '') ?: null;
        $tier = in_array($_POST['tier'] ?? '', ['bronze', 'silver', 'gold'], true) ? $_POST['tier'] : 'bronze';
        if ($code === '' || mb_strlen($name) < 2) {
            flash_set('error', 'Code and name are required.');
        } else {
            $pdo->prepare('INSERT INTO badges (code, name, description, icon, tier) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), icon = VALUES(icon), tier = VALUES(tier)')
                ->execute([$code, $name, $description, $icon, $tier]);
            flash_set('success', 'Badge saved.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM badges WHERE id = ?')->execute([(int) $_POST['id']]);
        audit_log($admin['id'], 'badge_deleted', 'badge', (int) $_POST['id']);
    } elseif ($action === 'award') {
        $username = trim($_POST['username'] ?? '');
        $code = trim($_POST['badge_code'] ?? '');
        $u = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $u->execute([$username]);
        $userId = $u->fetchColumn();
        if (!$userId) {
            flash_set('error', 'No user with that username.');
        } else {
            award_badge((int) $userId, $code);
            audit_log($admin['id'], 'badge_awarded', 'user', (int) $userId, $code);
            flash_set('success', 'Badge awarded to ' . $username . '.');
        }
    }
    redirect('/admin/badges');
}

$badges = $pdo->query('SELECT b.*, (SELECT COUNT(*) FROM user_badges ub WHERE ub.badge_id = b.id) AS holder_count FROM badges b ORDER BY b.tier, b.name')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Badges</h1>

<div class="grid md:grid-cols-2 gap-6 mb-6">
  <div class="bg-white border rounded-lg p-6">
    <h2 class="font-semibold mb-3 text-sm">Create / edit badge</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <input type="text" name="code" required placeholder="code (e.g. helpful_hero)" class="w-full border rounded px-3 py-2 text-sm">
      <input type="text" name="name" required placeholder="Display name" class="w-full border rounded px-3 py-2 text-sm">
      <textarea name="description" rows="2" placeholder="Description" class="w-full border rounded px-3 py-2 text-sm"></textarea>
      <div class="flex gap-2">
        <input type="text" name="icon" placeholder="Emoji icon" maxlength="10" class="w-24 border rounded px-3 py-2 text-sm">
        <select name="tier" class="border rounded px-2 py-2 text-sm">
          <option value="bronze">Bronze</option>
          <option value="silver">Silver</option>
          <option value="gold">Gold</option>
        </select>
      </div>
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save badge</button>
      <p class="text-xs text-slate-500">Saving an existing code updates that badge instead of duplicating it.</p>
    </form>
  </div>

  <div class="bg-white border rounded-lg p-6">
    <h2 class="font-semibold mb-3 text-sm">Manually award a badge</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="award">
      <input type="text" name="username" required placeholder="Username" class="w-full border rounded px-3 py-2 text-sm">
      <select name="badge_code" required class="w-full border rounded px-3 py-2 text-sm">
        <?php foreach ($badges as $b): ?>
          <option value="<?= e($b['code']) ?>"><?= e($b['icon'] ? $b['icon'] . ' ' : '') ?><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded text-sm">Award badge</button>
    </form>
  </div>
</div>

<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($badges as $b): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <div>
        <div class="font-medium"><?= e($b['icon'] ? $b['icon'] . ' ' : '') ?><?= e($b['name']) ?>
          <span class="text-xs px-1.5 py-0.5 rounded bg-slate-100 uppercase ml-1"><?= e($b['tier']) ?></span>
        </div>
        <div class="text-xs text-slate-500"><?= e($b['description']) ?> · code: <?= e($b['code']) ?> · <?= (int) $b['holder_count'] ?> holders</div>
      </div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$badges): ?><div class="p-4 text-sm text-slate-500">No badges defined yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
