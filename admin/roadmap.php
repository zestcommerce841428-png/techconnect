<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Roadmap — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();
$statuses = ['under_review', 'planned', 'in_progress', 'shipped', 'declined'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (mb_strlen($title) >= 3) {
            $pdo->prepare('INSERT INTO roadmap_items (title, description, submitted_by, status) VALUES (?, ?, ?, "planned")')
                ->execute([$title, $description, $admin['id']]);
            flash_set('success', 'Item created.');
        }
    } elseif ($action === 'status' && in_array($_POST['status'] ?? '', $statuses, true)) {
        $pdo->prepare('UPDATE roadmap_items SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
        audit_log($admin['id'], 'roadmap_status_changed', 'roadmap_item', $id, $_POST['status']);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM roadmap_items WHERE id = ?')->execute([$id]);
        audit_log($admin['id'], 'roadmap_item_deleted', 'roadmap_item', $id);
    }
    redirect('/admin/roadmap');
}

$rows = $pdo->query('SELECT ri.*, u.username FROM roadmap_items ri LEFT JOIN users u ON u.id = ri.submitted_by ORDER BY ri.created_at DESC')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Roadmap items</h1>

<div class="bg-white border rounded-lg p-6 max-w-lg mb-6">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="text" name="title" required placeholder="Title" class="w-full border rounded px-3 py-2 text-sm">
    <textarea name="description" rows="2" placeholder="Description (optional)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Add planned item</button>
  </form>
</div>

<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($rows as $r): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <div class="min-w-0">
        <div class="font-medium"><?= e($r['title']) ?> <span class="text-xs text-slate-400">(<?= (int) $r['vote_count'] ?> votes)</span></div>
        <div class="text-xs text-slate-500">by <?= e($r['username'] ?? 'admin') ?> · <?= time_ago($r['created_at']) ?></div>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <form method="post" class="flex items-center gap-1">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <select name="status" onchange="this.form.submit()" class="border rounded px-2 py-1 text-xs">
            <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="p-4 text-sm text-slate-500">No roadmap items yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
