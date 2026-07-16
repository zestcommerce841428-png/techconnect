<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Merge Questions — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

function find_question(PDO $pdo, string $needle): ?array
{
    $needle = trim($needle);
    if ($needle === '') return null;
    if (ctype_digit($needle)) {
        $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ?');
        $stmt->execute([(int) $needle]);
    } else {
        $slug = ltrim(parse_url($needle, PHP_URL_PATH) ?? $needle, '/');
        $slug = preg_replace('#^q/#', '', $slug);
        $stmt = $pdo->prepare('SELECT * FROM questions WHERE slug = ?');
        $stmt->execute([$slug]);
    }
    return $stmt->fetch() ?: null;
}

$dupInput = $_POST['duplicate'] ?? $_GET['duplicate'] ?? '';
$canonInput = $_POST['canonical'] ?? $_GET['canonical'] ?? '';
$duplicate = $dupInput !== '' ? find_question($pdo, $dupInput) : null;
$canonical = $canonInput !== '' ? find_question($pdo, $canonInput) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'merge') {
    verify_csrf();
    if (!$duplicate || !$canonical) {
        flash_set('error', 'Both questions must be found first.');
    } elseif ((int) $duplicate['id'] === (int) $canonical['id']) {
        flash_set('error', "A question can't be merged into itself.");
    } elseif ($duplicate['merged_into_id']) {
        flash_set('error', 'That question is already merged.');
    } else {
        $pdo->beginTransaction();
        try {
            // Move answers over, then dedupe tags, then close/redirect the duplicate.
            $pdo->prepare('UPDATE answers SET question_id = ? WHERE question_id = ?')
                ->execute([$canonical['id'], $duplicate['id']]);

            $dupTags = $pdo->prepare('SELECT tag_id FROM question_tags WHERE question_id = ?');
            $dupTags->execute([$duplicate['id']]);
            $insTag = $pdo->prepare('INSERT IGNORE INTO question_tags (question_id, tag_id) VALUES (?, ?)');
            foreach ($dupTags->fetchAll() as $t) {
                $insTag->execute([$canonical['id'], $t['tag_id']]);
            }
            $pdo->prepare('DELETE FROM question_tags WHERE question_id = ?')->execute([$duplicate['id']]);

            $newAnswerCount = $pdo->prepare('SELECT COUNT(*) FROM answers WHERE question_id = ?');
            $newAnswerCount->execute([$canonical['id']]);
            $pdo->prepare('UPDATE questions SET answer_count = ? WHERE id = ?')
                ->execute([(int) $newAnswerCount->fetchColumn(), $canonical['id']]);

            $pdo->prepare("UPDATE questions SET status = 'closed', closed_reason = ?, closed_by = ?, merged_into_id = ? WHERE id = ?")
                ->execute(['Merged into another question', $admin['id'], $canonical['id'], $duplicate['id']]);

            $pdo->prepare('INSERT INTO redirects (from_path, to_path, status_code) VALUES (?, ?, 301)
                ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)')
                ->execute(['/q/' . $duplicate['slug'], '/q/' . $canonical['slug']]);

            $pdo->commit();
            audit_log($admin['id'], 'questions_merged', 'question', (int) $duplicate['id'], 'into ' . $canonical['id']);
            flash_set('success', "Merged \"{$duplicate['title']}\" into \"{$canonical['title']}\". Visitors to the old URL will be redirected.");
            redirect('/admin/merge_questions');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', 'Merge failed: ' . $e->getMessage());
        }
    }
}

$recentMerges = $pdo->query(
    "SELECT q.id, q.title, q.slug, q.merged_into_id, c.title AS canonical_title, c.slug AS canonical_slug
     FROM questions q JOIN questions c ON c.id = q.merged_into_id
     WHERE q.merged_into_id IS NOT NULL ORDER BY q.updated_at DESC LIMIT 20"
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-1">Merge duplicate questions</h1>
<p class="text-sm text-slate-600 mb-6">
  Moves all answers and tags from the duplicate onto the canonical question, closes the duplicate, and sets up a
  permanent redirect from its URL. This can't be undone automatically — double-check before confirming.
</p>

<form method="get" class="grid md:grid-cols-2 gap-4 max-w-2xl mb-6">
  <div>
    <label class="block text-sm font-medium mb-1">Duplicate (will be closed &amp; redirected)</label>
    <input type="text" name="duplicate" value="<?= e($dupInput) ?>" placeholder="Question ID, slug, or /q/... URL" class="w-full border rounded px-3 py-2 text-sm">
    <?php if ($dupInput !== '' && !$duplicate): ?><p class="text-xs text-red-600 mt-1">Not found.</p><?php elseif ($duplicate): ?><p class="text-xs text-slate-500 mt-1">#<?= (int) $duplicate['id'] ?> — <?= e($duplicate['title']) ?></p><?php endif; ?>
  </div>
  <div>
    <label class="block text-sm font-medium mb-1">Canonical (keeps everything)</label>
    <input type="text" name="canonical" value="<?= e($canonInput) ?>" placeholder="Question ID, slug, or /q/... URL" class="w-full border rounded px-3 py-2 text-sm">
    <?php if ($canonInput !== '' && !$canonical): ?><p class="text-xs text-red-600 mt-1">Not found.</p><?php elseif ($canonical): ?><p class="text-xs text-slate-500 mt-1">#<?= (int) $canonical['id'] ?> — <?= e($canonical['title']) ?></p><?php endif; ?>
  </div>
  <button type="submit" class="md:col-span-2 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded text-sm w-fit">Look up</button>
</form>

<?php if ($duplicate && $canonical && (int) $duplicate['id'] !== (int) $canonical['id']): ?>
  <div class="bg-amber-50 border border-amber-300 rounded-lg p-4 max-w-2xl mb-6">
    <p class="text-sm mb-3">
      Merge <strong>#<?= (int) $duplicate['id'] ?> "<?= e($duplicate['title']) ?>"</strong>
      into <strong>#<?= (int) $canonical['id'] ?> "<?= e($canonical['title']) ?>"</strong>?
    </p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="merge">
      <input type="hidden" name="duplicate" value="<?= e($dupInput) ?>">
      <input type="hidden" name="canonical" value="<?= e($canonInput) ?>">
      <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded text-sm">Confirm merge</button>
    </form>
  </div>
<?php endif; ?>

<h2 class="font-semibold text-sm mb-2">Recent merges</h2>
<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($recentMerges as $m): ?>
    <div class="p-3 text-sm">
      "<?= e($m['title']) ?>" &rarr; <a href="/q/<?= e($m['canonical_slug']) ?>" class="text-indigo-600 hover:underline">"<?= e($m['canonical_title']) ?>"</a>
    </div>
  <?php endforeach; ?>
  <?php if (!$recentMerges): ?><div class="p-3 text-sm text-slate-500">No merges yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
