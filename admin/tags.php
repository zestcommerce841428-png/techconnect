<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Tags — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'rename') {
        $tagId = (int) ($_POST['tag_id'] ?? 0);
        $newName = strtolower(trim($_POST['new_name'] ?? ''));
        if (!preg_match('/^[a-z0-9][a-z0-9. +#-]{0,48}$/', $newName)) {
            flash_set('error', 'Invalid tag name.');
        } else {
            $pdo->prepare('UPDATE tags SET name = ?, slug = ? WHERE id = ?')->execute([$newName, slugify($newName), $tagId]);
            audit_log($admin['id'], 'tag_renamed', 'tag', $tagId, $newName);
            flash_set('success', 'Tag renamed.');
        }
    } elseif ($action === 'merge') {
        $fromId = (int) ($_POST['from_id'] ?? 0);
        $intoName = strtolower(trim($_POST['into_name'] ?? ''));
        $find = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
        $find->execute([$intoName]);
        $intoId = (int) $find->fetchColumn();
        if (!$intoId || $intoId === $fromId) {
            flash_set('error', 'Target tag not found (or same as source).');
        } else {
            // Repoint question links (ignore duplicates), move follower counts, delete the source tag.
            $pdo->prepare('UPDATE IGNORE question_tags SET tag_id = ? WHERE tag_id = ?')->execute([$intoId, $fromId]);
            $pdo->prepare('DELETE FROM question_tags WHERE tag_id = ?')->execute([$fromId]);
            $pdo->prepare("UPDATE IGNORE follows SET followable_id = ? WHERE followable_type = 'tag' AND followable_id = ?")->execute([$intoId, $fromId]);
            $pdo->prepare("DELETE FROM follows WHERE followable_type = 'tag' AND followable_id = ?")->execute([$fromId]);
            $pdo->prepare('UPDATE tags SET use_count = (SELECT COUNT(*) FROM question_tags WHERE tag_id = ?) WHERE id = ?')->execute([$intoId, $intoId]);
            $pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$fromId]);
            audit_log($admin['id'], 'tag_merged', 'tag', $fromId, 'into ' . $intoName);
            flash_set('success', 'Tag merged into "' . $intoName . '".');
        }
    } elseif ($action === 'delete') {
        $tagId = (int) ($_POST['tag_id'] ?? 0);
        $pdo->prepare('DELETE FROM question_tags WHERE tag_id = ?')->execute([$tagId]);
        $pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$tagId]);
        audit_log($admin['id'], 'tag_deleted', 'tag', $tagId);
        flash_set('success', 'Tag deleted.');
    }
    redirect('/admin/tags');
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare('SELECT * FROM tags WHERE name LIKE ? ORDER BY use_count DESC LIMIT 100');
    $stmt->execute(['%' . $q . '%']);
} else {
    $stmt = $pdo->query('SELECT * FROM tags ORDER BY use_count DESC LIMIT 100');
}
$tags = $stmt->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Tag management</h1>
<form method="get" class="mb-4">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search tags…" class="border rounded px-3 py-2 text-sm w-64">
</form>
<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($tags as $t): ?>
    <div class="p-3 flex flex-wrap items-center justify-between gap-3 text-sm">
      <div>
        <span class="font-mono bg-slate-100 px-2 py-0.5 rounded"><?= e($t['name']) ?></span>
        <span class="text-xs text-slate-500 ml-2"><?= (int) $t['use_count'] ?> uses</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <form method="post" class="flex gap-1">
          <?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="tag_id" value="<?= (int) $t['id'] ?>">
          <input type="text" name="new_name" placeholder="rename to…" class="border rounded px-2 py-1 text-xs w-28">
          <button type="submit" class="text-xs text-indigo-600 hover:underline">Rename</button>
        </form>
        <form method="post" class="flex gap-1">
          <?= csrf_field() ?><input type="hidden" name="action" value="merge"><input type="hidden" name="from_id" value="<?= (int) $t['id'] ?>">
          <input type="text" name="into_name" placeholder="merge into…" class="border rounded px-2 py-1 text-xs w-28">
          <button type="submit" class="text-xs text-amber-700 hover:underline">Merge</button>
        </form>
        <form method="post" onsubmit="return confirm('Delete this tag from all questions?');">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="tag_id" value="<?= (int) $t['id'] ?>">
          <button type="submit" class="text-xs text-red-600 hover:underline">Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$tags): ?><div class="p-4 text-sm text-slate-500">No tags found.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
