<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $label = trim($_POST['label'] ?? '');
        $query = trim($_POST['query_string'] ?? '', '?');
        if (mb_strlen($label) < 2 || $query === '') {
            flash_set('error', 'Please provide a label and a search query.');
        } else {
            $pdo->prepare('INSERT INTO saved_searches (user_id, label, query_string) VALUES (?, ?, ?)')
                ->execute([$user['id'], mb_substr($label, 0, 120), mb_substr($query, 0, 500)]);
            flash_set('success', 'Search saved.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM saved_searches WHERE id = ? AND user_id = ?')->execute([(int) $_POST['id'], $user['id']]);
    }
    redirect('/saved_searches');
}

$stmt = $pdo->prepare('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$searches = $stmt->fetchAll();

$pageTitle = 'Saved searches — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-1">Saved searches</h1>
<p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Bookmark a <a href="/search" class="text-indigo-600 hover:underline">search</a> query to revisit it quickly — copy the URL's query string (the part after <code>?</code>) here.</p>

<div class="card p-6 mb-6 max-w-lg">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="text" name="label" required placeholder="Label (e.g. Unanswered PHP questions)" class="w-full border rounded-lg px-3 py-2 text-sm">
    <input type="text" name="query_string" required placeholder="q=php&answered=unanswered" class="w-full border rounded-lg px-3 py-2 text-sm font-mono">
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm">Save search</button>
  </form>
</div>

<div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y max-w-lg">
  <?php foreach ($searches as $s): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <a href="/search?<?= e($s['query_string']) ?>" class="text-indigo-600 hover:underline font-medium"><?= e($s['label']) ?></a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$searches): ?><div class="p-4 text-sm text-slate-500">No saved searches yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
