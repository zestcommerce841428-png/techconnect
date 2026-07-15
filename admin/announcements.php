<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Announcements — Admin';
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
        $message = trim($_POST['message'] ?? '');
        $link = trim($_POST['link_url'] ?? '');
        $style = in_array($_POST['style'] ?? '', ['info', 'success', 'warning'], true) ? $_POST['style'] : 'info';
        if (mb_strlen($message) < 3) {
            flash_set('error', 'Message is too short.');
        } else {
            // Only one live announcement at a time keeps the banner meaningful.
            $pdo->exec('UPDATE announcements SET enabled = 0');
            $pdo->prepare('INSERT INTO announcements (message, link_url, style, enabled) VALUES (?, ?, ?, 1)')
                ->execute([$message, $link ?: null, $style]);
            flash_set('success', 'Announcement published.');
        }
    } elseif ($action === 'disable') {
        $pdo->prepare('UPDATE announcements SET enabled = 0 WHERE id = ?')->execute([(int) $_POST['id']]);
        flash_set('success', 'Announcement disabled.');
    } elseif ($action === 'enable') {
        $pdo->exec('UPDATE announcements SET enabled = 0');
        $pdo->prepare('UPDATE announcements SET enabled = 1 WHERE id = ?')->execute([(int) $_POST['id']]);
        flash_set('success', 'Announcement enabled.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([(int) $_POST['id']]);
        flash_set('success', 'Announcement deleted.');
    }
    redirect('/admin/announcements');
}

$rows = $pdo->query('SELECT * FROM announcements ORDER BY created_at DESC LIMIT 50')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Site announcements</h1>
<div class="bg-white border rounded-lg p-6 max-w-xl mb-6">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="text" name="message" required maxlength="300" placeholder="Announcement text (shown in a banner site-wide)" class="w-full border rounded px-3 py-2 text-sm">
    <input type="url" name="link_url" placeholder="Optional link URL" class="w-full border rounded px-3 py-2 text-sm">
    <select name="style" class="border rounded px-2 py-1.5 text-sm">
      <option value="info">Info (blue)</option>
      <option value="success">Success (green)</option>
      <option value="warning">Warning (amber)</option>
    </select>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Publish (replaces any live banner)</button>
  </form>
</div>
<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($rows as $r): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <div>
        <span class="<?= $r['enabled'] ? 'font-semibold' : 'text-slate-500' ?>"><?= e($r['message']) ?></span>
        <span class="text-xs px-1.5 py-0.5 rounded ml-1 <?= $r['enabled'] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?>"><?= $r['enabled'] ? 'live' : 'off' ?></span>
      </div>
      <div class="flex gap-2 shrink-0">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button type="submit" name="action" value="<?= $r['enabled'] ? 'disable' : 'enable' ?>" class="text-xs text-indigo-600 hover:underline"><?= $r['enabled'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="p-4 text-sm text-slate-500">No announcements yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
