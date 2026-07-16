<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    $pdo->prepare("DELETE FROM questions WHERE id = ? AND user_id = ? AND status = 'draft'")
        ->execute([(int) $_POST['id'], $user['id']]);
    flash_set('success', 'Draft deleted.');
    redirect('/drafts');
}

$stmt = $pdo->prepare("SELECT id, title, created_at FROM questions WHERE user_id = ? AND status = 'draft' ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$drafts = $stmt->fetchAll();

$pageTitle = 'Your drafts — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-1">Your drafts</h1>
<p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Questions you started but haven't posted yet. Only you can see these.</p>

<div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y max-w-2xl">
  <?php foreach ($drafts as $d): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <div>
        <a href="/ask?draft_id=<?= (int) $d['id'] ?>" class="text-indigo-600 hover:underline font-medium"><?= e($d['title'] ?: '(untitled draft)') ?></a>
        <div class="text-xs text-slate-500">Saved <?= time_ago($d['created_at']) ?></div>
      </div>
      <form method="post" onsubmit="return confirm('Delete this draft?');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
        <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$drafts): ?><div class="p-4 text-sm text-slate-500">No drafts yet. <a href="/ask" class="text-indigo-600 hover:underline">Start a question</a>.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
