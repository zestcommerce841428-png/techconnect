<?php
require_once __DIR__ . '/includes/auth.php';
$user = current_user();
$pdo = db();

$username = $_GET['username'] ?? '';
$slug = $_GET['slug'] ?? '';

$stmt = $pdo->prepare(
    "SELECT c.*, u.username FROM collections c JOIN users u ON u.id = c.user_id
     WHERE u.username = ? AND c.slug = ?"
);
$stmt->execute([$username, $slug]);
$collection = $stmt->fetch();

$isOwner = $user && $collection && (int) $collection['user_id'] === (int) $user['id'];

if (!$collection || (!$collection['is_public'] && !$isOwner)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    verify_csrf();
    if (!$isOwner) {
        http_response_code(403);
        exit('Forbidden');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $qId = (int) ($_POST['question_id'] ?? 0);
        $exists = $pdo->prepare('SELECT id FROM questions WHERE id = ?');
        $exists->execute([$qId]);
        if ($exists->fetchColumn()) {
            $pdo->prepare('INSERT IGNORE INTO collection_items (collection_id, question_id) VALUES (?, ?)')
                ->execute([$collection['id'], $qId]);
            flash_set('success', 'Added to collection.');
        }
    } elseif ($action === 'remove') {
        $qId = (int) ($_POST['question_id'] ?? 0);
        $pdo->prepare('DELETE FROM collection_items WHERE collection_id = ? AND question_id = ?')
            ->execute([$collection['id'], $qId]);
    } elseif ($action === 'toggle_visibility') {
        $pdo->prepare('UPDATE collections SET is_public = 1 - is_public WHERE id = ?')->execute([$collection['id']]);
    }
    redirect('/u/' . rawurlencode($username) . '/' . rawurlencode($slug));
}

$items = $pdo->prepare(
    "SELECT q.* FROM collection_items ci JOIN questions q ON q.id = ci.question_id
     WHERE ci.collection_id = ? AND q.status != 'draft' ORDER BY ci.added_at DESC"
);
$items->execute([$collection['id']]);
$questions = $items->fetchAll();

$pageTitle = e($collection['name']) . ' — Collection by ' . e($username) . ' — ' . SITE_NAME;
$pageDescription = $collection['description'] ?: ('A curated collection of questions by ' . $username . '.');
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-start justify-between gap-4 mb-1">
    <h1 class="text-2xl font-bold"><?= e($collection['name']) ?></h1>
    <?php if ($isOwner): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_visibility">
        <button type="submit" class="text-xs px-2 py-1 rounded border <?= $collection['is_public'] ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-slate-600 border-slate-200' ?>">
          <?= $collection['is_public'] ? 'Public — click to make private' : 'Private — click to make public' ?>
        </button>
      </form>
    <?php endif; ?>
  </div>
  <p class="text-sm text-slate-500 mb-1">by <a href="/u/<?= rawurlencode($username) ?>" class="text-indigo-600 hover:underline"><?= e($username) ?></a> · <?= count($questions) ?> question<?= count($questions) == 1 ? '' : 's' ?></p>
  <?php if ($collection['description']): ?><p class="text-sm text-slate-600 dark:text-slate-400 mb-6"><?= e($collection['description']) ?></p><?php else: ?><div class="mb-6"></div><?php endif; ?>

  <div class="space-y-3">
    <?php foreach ($questions as $q): ?>
      <div class="relative">
        <?php require __DIR__ . '/includes/question_card.php'; ?>
        <?php if ($isOwner): ?>
          <form method="post" class="absolute top-3 right-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="question_id" value="<?= (int) $q['id'] ?>">
            <button type="submit" class="text-xs bg-white/90 dark:bg-slate-800/90 border rounded px-2 py-1 text-red-600 hover:underline">Remove</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$questions): ?><p class="text-sm text-slate-500">No questions in this collection yet.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
