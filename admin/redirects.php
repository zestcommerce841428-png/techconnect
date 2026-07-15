<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Redirects — Admin';
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
        $from = '/' . ltrim(trim($_POST['from_path'] ?? ''), '/');
        $to = trim($_POST['to_path'] ?? '');
        $status = (int) ($_POST['status_code'] ?? 301);
        if ($from === '/' || $to === '') {
            flash_set('error', 'Please provide both paths.');
        } else {
            $pdo->prepare('INSERT INTO redirects (from_path, to_path, status_code) VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE to_path = VALUES(to_path), status_code = VALUES(status_code)')
                ->execute([$from, $to, in_array($status, [301, 302], true) ? $status : 301]);
            flash_set('success', 'Redirect saved.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM redirects WHERE id = ?')->execute([(int) $_POST['id']]);
    }
    redirect('/admin/redirects');
}

$rows = $pdo->query('SELECT * FROM redirects ORDER BY created_at DESC LIMIT 200')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-2">Redirects</h1>
<p class="text-sm text-slate-600 mb-6">For URLs that moved (e.g. after renaming a page). These are checked on every request that doesn't match a real page.</p>
<div class="bg-white border rounded-lg p-6 max-w-xl mb-6">
  <form method="post" class="flex flex-wrap gap-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="text" name="from_path" required placeholder="/old-path" class="flex-1 min-w-40 border rounded px-3 py-2 text-sm">
    <input type="text" name="to_path" required placeholder="/new-path or https://..." class="flex-1 min-w-40 border rounded px-3 py-2 text-sm">
    <select name="status_code" class="border rounded px-2 py-1.5 text-sm">
      <option value="301">301 (permanent)</option>
      <option value="302">302 (temporary)</option>
    </select>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save</button>
  </form>
</div>
<div class="bg-white border rounded-lg overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-left"><tr><th class="p-3">From</th><th class="p-3">To</th><th class="p-3">Status</th><th class="p-3">Hits</th><th class="p-3"></th></tr></thead>
    <tbody class="divide-y">
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="p-3 font-mono text-xs"><?= e($r['from_path']) ?></td>
        <td class="p-3 font-mono text-xs"><?= e($r['to_path']) ?></td>
        <td class="p-3"><?= (int) $r['status_code'] ?></td>
        <td class="p-3"><?= (int) $r['hits'] ?></td>
        <td class="p-3"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$rows): ?><div class="p-4 text-sm text-slate-500">No redirects yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
