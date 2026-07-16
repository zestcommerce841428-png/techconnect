<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Import questions — Admin';
require __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/audit.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Admins only.');
}

$pdo = db();
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        flash_set('error', 'Please choose a CSV file to upload.');
        redirect('/admin/import_questions');
    }
    if ($_FILES['csv']['size'] > 2 * 1024 * 1024) {
        flash_set('error', 'CSV too large (max 2 MB).');
        redirect('/admin/import_questions');
    }

    $asUserId = (int) ($_POST['as_user_id'] ?? 0) ?: (int) $admin['id'];
    $userCheck = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $userCheck->execute([$asUserId]);
    if (!$userCheck->fetchColumn()) {
        $asUserId = (int) $admin['id'];
    }
    $publish = !empty($_POST['publish']);

    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($fh);
    $header = array_map(fn($h) => strtolower(trim((string) $h)), $header ?: []);
    $required = ['title', 'body'];
    if (array_diff($required, $header)) {
        fclose($fh);
        flash_set('error', 'CSV must have a header row including at least: title, body. Optional: category, tags.');
        redirect('/admin/import_questions');
    }

    $col = array_flip($header);
    $findCategory = $pdo->prepare('SELECT id FROM categories WHERE slug = ? OR name = ? LIMIT 1');
    $findTag = $pdo->prepare('SELECT id FROM tags WHERE slug = ? LIMIT 1');
    $makeTag = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
    $linkTag = $pdo->prepare('INSERT IGNORE INTO question_tags (question_id, tag_id) VALUES (?, ?)');
    $bumpTag = $pdo->prepare('UPDATE tags SET use_count = use_count + 1 WHERE id = ?');
    $insert = $pdo->prepare('INSERT INTO questions (user_id, category_id, title, slug, body, status) VALUES (?, ?, ?, ?, ?, ?)');
    $dupCheck = $pdo->prepare('SELECT id FROM questions WHERE title = ? LIMIT 1');

    $results = ['imported' => 0, 'skipped' => [], 'row' => 1];
    while (($row = fgetcsv($fh)) !== false && $results['imported'] < 500) {
        $results['row']++;
        $title = trim((string) ($row[$col['title']] ?? ''));
        $body = trim((string) ($row[$col['body']] ?? ''));
        if (mb_strlen($title) < 10 || mb_strlen($title) > 200) {
            $results['skipped'][] = "Row {$results['row']}: title must be 10-200 characters.";
            continue;
        }
        if (mb_strlen($body) < 20) {
            $results['skipped'][] = "Row {$results['row']}: body must be at least 20 characters.";
            continue;
        }
        $dupCheck->execute([$title]);
        if ($dupCheck->fetchColumn()) {
            $results['skipped'][] = "Row {$results['row']}: a question with this exact title already exists.";
            continue;
        }

        $categoryId = null;
        if (isset($col['category']) && trim((string) ($row[$col['category']] ?? '')) !== '') {
            $catVal = trim($row[$col['category']]);
            $findCategory->execute([slugify($catVal), $catVal]);
            $categoryId = (int) $findCategory->fetchColumn() ?: null;
            if (!$categoryId) {
                $results['skipped'][] = "Row {$results['row']}: unknown category \"{$catVal}\".";
                continue;
            }
        }

        $insert->execute([$asUserId, $categoryId, $title, unique_slug('questions', $title), $body, $publish ? 'open' : 'draft']);
        $questionId = (int) $pdo->lastInsertId();

        if (isset($col['tags'])) {
            foreach (array_slice(array_filter(array_map('trim', explode('|', (string) ($row[$col['tags']] ?? '')))), 0, 5) as $tagName) {
                $tagSlug = slugify($tagName);
                $findTag->execute([$tagSlug]);
                $tagId = (int) $findTag->fetchColumn();
                if (!$tagId) {
                    $makeTag->execute([mb_substr($tagName, 0, 50), $tagSlug]);
                    $tagId = (int) $pdo->lastInsertId();
                }
                $linkTag->execute([$questionId, $tagId]);
                $bumpTag->execute([$tagId]);
            }
        }
        $results['imported']++;
    }
    fclose($fh);
    audit_log($admin['id'], 'questions_imported', 'question', null, $results['imported'] . ' imported, ' . count($results['skipped']) . ' skipped');
}

$admins = $pdo->query("SELECT id, username FROM users WHERE role IN ('admin', 'moderator') ORDER BY username")->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Import questions from CSV</h1>

<?php if ($results): ?>
  <div class="bg-white border rounded-lg p-4 mb-6">
    <p class="text-sm font-medium text-green-700">✅ Imported <?= (int) $results['imported'] ?> question<?= $results['imported'] === 1 ? '' : 's' ?>.</p>
    <?php if ($results['skipped']): ?>
      <p class="text-sm font-medium text-amber-700 mt-2">Skipped <?= count($results['skipped']) ?>:</p>
      <ul class="text-xs text-slate-600 mt-1 space-y-0.5 max-h-48 overflow-y-auto">
        <?php foreach ($results['skipped'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="bg-white border rounded-lg p-6 max-w-2xl">
  <p class="text-sm text-slate-600 mb-4">
    Upload a CSV with a header row. Columns: <code class="bg-slate-100 px-1 rounded">title</code> and
    <code class="bg-slate-100 px-1 rounded">body</code> (required),
    <code class="bg-slate-100 px-1 rounded">category</code> (name or slug) and
    <code class="bg-slate-100 px-1 rounded">tags</code> (pipe-separated, e.g. <code class="bg-slate-100 px-1 rounded">php|mysql</code>) optional.
    Markdown is supported in the body. Max 500 rows per upload; exact-title duplicates are skipped.
  </p>
  <form method="post" enctype="multipart/form-data" class="space-y-4">
    <?= csrf_field() ?>
    <div>
      <label for="imp_csv" class="block text-sm font-medium mb-1">CSV file</label>
      <input id="imp_csv" type="file" name="csv" accept=".csv,text/csv" required class="text-sm">
    </div>
    <div>
      <label for="imp_user" class="block text-sm font-medium mb-1">Post as</label>
      <select id="imp_user" name="as_user_id" class="border rounded px-3 py-2 text-sm">
        <?php foreach ($admins as $a): ?>
          <option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === (int) $admin['id'] ? 'selected' : '' ?>><?= e($a['username']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="publish" value="1">
      Publish immediately (otherwise imported as drafts you can review in <a href="/drafts" class="text-indigo-600 underline">drafts</a>)
    </label>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm font-medium">Import</button>
  </form>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
