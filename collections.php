<?php
require_once __DIR__ . '/includes/auth.php';
$user = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        rate_limit('collection_create', 20, 3600);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPublic = !empty($_POST['is_public']) ? 1 : 0;
        if (mb_strlen($name) < 2) {
            flash_set('error', 'Please give the collection a name.');
            redirect('/collections');
        }
        $base = slugify($name);
        $slug = $base;
        $i = 1;
        $check = $pdo->prepare('SELECT COUNT(*) FROM collections WHERE user_id = ? AND slug = ?');
        while (true) {
            $check->execute([$user['id'], $slug]);
            if ((int) $check->fetchColumn() === 0) break;
            $i++;
            $slug = $base . '-' . $i;
        }
        $pdo->prepare('INSERT INTO collections (user_id, name, slug, description, is_public) VALUES (?, ?, ?, ?, ?)')
            ->execute([$user['id'], $name, $slug, $description ?: null, $isPublic]);
        flash_set('success', 'Collection created.');
        redirect('/u/' . rawurlencode($user['username']) . '/' . $slug);
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM collections WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
        flash_set('success', 'Collection deleted.');
    }
    redirect('/collections');
}

$mine = [];
if ($user) {
    $stmt = $pdo->prepare(
        "SELECT c.*, (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id = c.id) AS item_count
         FROM collections c WHERE c.user_id = ? ORDER BY c.created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $mine = $stmt->fetchAll();
}

$public = $pdo->query(
    "SELECT c.*, u.username, (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id = c.id) AS item_count
     FROM collections c JOIN users u ON u.id = c.user_id
     WHERE c.is_public = 1 ORDER BY c.created_at DESC LIMIT 30"
)->fetchAll();

$pageTitle = 'Collections — ' . SITE_NAME;
$pageDescription = 'Organize and share curated lists of questions.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <h1 class="text-2xl font-bold mb-1">Collections</h1>
  <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Group related questions into curated, optionally public, reading lists.</p>

  <?php if ($user): ?>
    <details class="card p-4 mb-6">
      <summary class="cursor-pointer font-medium text-sm">+ New collection</summary>
      <form method="post" class="space-y-3 mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <input type="text" name="name" required minlength="2" maxlength="100" placeholder="Collection name" class="w-full border rounded px-3 py-2 text-sm">
        <textarea name="description" rows="2" maxlength="500" placeholder="Description (optional)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1"> Make this collection public</label>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Create</button>
      </form>
    </details>

    <h2 class="font-semibold text-sm mb-2">My collections</h2>
    <div class="space-y-2 mb-8">
      <?php foreach ($mine as $c): ?>
        <div class="card p-3 flex items-center justify-between gap-3">
          <a href="/u/<?= rawurlencode($user['username']) ?>/<?= e($c['slug']) ?>" class="min-w-0">
            <div class="font-medium truncate"><?= e($c['name']) ?> <?= $c['is_public'] ? '<span class="text-xs text-green-600">public</span>' : '<span class="text-xs text-slate-400">private</span>' ?></div>
            <div class="text-xs text-slate-500"><?= (int) $c['item_count'] ?> question<?= $c['item_count'] == 1 ? '' : 's' ?></div>
          </a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button type="submit" name="action" value="delete" onclick="return confirm('Delete this collection?')" class="text-xs text-red-600 hover:underline shrink-0">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$mine): ?><p class="text-sm text-slate-500">You haven't created any collections yet.</p><?php endif; ?>
    </div>
  <?php else: ?>
    <p class="text-sm text-slate-500 mb-8"><a href="/login" class="text-indigo-600 hover:underline">Log in</a> to create your own collections.</p>
  <?php endif; ?>

  <h2 class="font-semibold text-sm mb-2">Public collections</h2>
  <div class="space-y-2">
    <?php foreach ($public as $c): ?>
      <a href="/u/<?= rawurlencode($c['username']) ?>/<?= e($c['slug']) ?>" class="card p-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
          <div class="font-medium truncate"><?= e($c['name']) ?></div>
          <div class="text-xs text-slate-500">by <?= e($c['username']) ?> · <?= (int) $c['item_count'] ?> question<?= $c['item_count'] == 1 ? '' : 's' ?></div>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if (!$public): ?><p class="text-sm text-slate-500">No public collections yet.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
