<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Changelog — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $version = trim($_POST['version'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $type = in_array($_POST['entry_type'] ?? '', ['added', 'changed', 'fixed', 'removed'], true) ? $_POST['entry_type'] : 'added';
        if (mb_strlen($title) < 3) {
            flash_set('error', 'Please provide a title.');
        } else {
            $pdo->prepare('INSERT INTO changelog_entries (version, title, body, entry_type, is_published, published_at) VALUES (?, ?, ?, ?, 1, NOW())')
                ->execute([$version ?: null, $title, $body, $type]);
            flash_set('success', 'Changelog entry published.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM changelog_entries WHERE id = ?')->execute([(int) $_POST['id']]);
    }
    redirect('/admin/changelog');
}

$rows = $pdo->query('SELECT * FROM changelog_entries ORDER BY created_at DESC LIMIT 50')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Changelog</h1>
<div class="bg-white border rounded-lg p-6 max-w-lg mb-6">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="flex gap-2">
      <input type="text" name="version" placeholder="v1.2.0 (optional)" class="w-32 border rounded px-3 py-2 text-sm">
      <select name="entry_type" class="border rounded px-2 py-1.5 text-sm">
        <option value="added">Added</option>
        <option value="changed">Changed</option>
        <option value="fixed">Fixed</option>
        <option value="removed">Removed</option>
      </select>
    </div>
    <input type="text" name="title" required placeholder="Title" class="w-full border rounded px-3 py-2 text-sm">
    <textarea name="body" rows="3" placeholder="Details (optional)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Publish entry</button>
  </form>
</div>
<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($rows as $c): ?>
    <div class="p-4 flex items-start justify-between gap-4 text-sm">
      <div>
        <span class="text-xs px-1.5 py-0.5 rounded bg-slate-100 uppercase font-medium"><?= e($c['entry_type']) ?></span>
        <?php if ($c['version']): ?><span class="text-xs text-slate-500 ml-1"><?= e($c['version']) ?></span><?php endif; ?>
        <div class="font-medium mt-1"><?= e($c['title']) ?></div>
      </div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="p-4 text-sm text-slate-500">No entries yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
