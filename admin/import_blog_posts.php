<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Import blog posts — Admin';
require __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/audit.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission($admin, 'manage_blog');

$pdo = db();
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        flash_set('error', 'Please choose a CSV file to upload.');
        redirect('/admin/import_blog_posts');
    }
    if ($_FILES['csv']['size'] > 5 * 1024 * 1024) {
        flash_set('error', 'CSV too large (max 5 MB — blog bodies run longer than Q&A answers).');
        redirect('/admin/import_blog_posts');
    }

    $publish = !empty($_POST['publish']);

    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($fh);
    $header = array_map(fn($h) => strtolower(trim((string) $h)), $header ?: []);
    // Same Excel/Google Sheets BOM fix as admin/import_questions.php: their
    // exports prefix a UTF-8 BOM, so the first column arrives as
    // "\xEF\xBB\xBFtitle" and looks absent. trim() does not touch it — it is
    // bytes, not whitespace.
    if (isset($header[0]) && str_starts_with($header[0], "\xEF\xBB\xBF")) {
        $header[0] = substr($header[0], 3);
    }
    $required = ['title', 'body'];
    if (array_diff($required, $header)) {
        fclose($fh);
        flash_set('error', 'CSV must have a header row including at least: title, body. Optional: excerpt, category, meta_description.');
        redirect('/admin/import_blog_posts');
    }

    $col = array_flip($header);
    $findCategory = $pdo->prepare('SELECT id FROM blog_categories WHERE slug = ? OR name = ? LIMIT 1');
    $catalog = [];
    foreach ($pdo->query('SELECT name, slug FROM blog_categories') as $c) {
        $catalog[] = ['name' => $c['name'], 'slug' => $c['slug'],
                      'hay' => mb_strtolower($c['name'] . ' ' . str_replace('-', ' ', $c['slug']))];
    }
    /** Same fuzzy-match logic as admin/import_questions.php's suggestCategories(),
     *  tuned against real category catalogs there: substring evidence always
     *  outranks edit distance, and short input gets a tighter distance limit so
     *  a 3-letter needle doesn't match on coincidence. */
    $suggestCategories = function (string $value) use ($catalog): array {
        $needle = mb_strtolower(trim($value));
        if ($needle === '' || !$catalog) {
            return [];
        }
        $exact = [];
        $fuzzy = [];
        foreach ($catalog as $c) {
            if (str_contains($c['hay'], $needle) || str_contains($needle, mb_strtolower($c['name']))) {
                $exact[$c['slug']] = -mb_strlen($c['name']);
                continue;
            }
            $best = PHP_INT_MAX;
            foreach (array_merge([mb_strtolower($c['name']), str_replace('-', ' ', $c['slug'])],
                                 preg_split('/[\s&\-]+/', $c['hay'], -1, PREG_SPLIT_NO_EMPTY) ?: []) as $probe) {
                $best = min($best, levenshtein($needle, $probe));
            }
            $limit = mb_strlen($needle) <= 5 ? 1 : 2;
            if ($best <= $limit) {
                $fuzzy[$c['slug']] = -$best;
            }
        }
        $scored = $exact ?: $fuzzy;
        arsort($scored);
        return array_slice(array_keys($scored), 0, 3);
    };

    $insert = $pdo->prepare(
        'INSERT INTO blog_posts (author_id, blog_category_id, title, slug, excerpt, body, meta_description, status, published_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $dupCheck = $pdo->prepare('SELECT id FROM blog_posts WHERE title = ? LIMIT 1');

    $results = ['imported' => 0, 'skipped' => [], 'row' => 1];
    while (($row = fgetcsv($fh)) !== false && $results['imported'] < 500) {
        $results['row']++;
        $title = trim((string) ($row[$col['title']] ?? ''));
        $body = trim((string) ($row[$col['body']] ?? ''));
        if (mb_strlen($title) < 5 || mb_strlen($title) > 200) {
            $results['skipped'][] = "Row {$results['row']}: title must be 5-200 characters.";
            continue;
        }
        if (mb_strlen($body) < 100) {
            // Thin content is the exact failure mode this whole importer exists
            // to avoid — a 30-word "post" is worse for SEO than no post at all.
            $results['skipped'][] = "Row {$results['row']}: body must be at least 100 characters — thin posts hurt SEO more than an empty blog.";
            continue;
        }
        $dupCheck->execute([$title]);
        if ($dupCheck->fetchColumn()) {
            $results['skipped'][] = "Row {$results['row']}: a post with this exact title already exists.";
            continue;
        }

        $excerpt = isset($col['excerpt']) ? mb_substr(trim((string) ($row[$col['excerpt']] ?? '')), 0, 300) : null;
        $metaDescription = isset($col['meta_description']) ? mb_substr(trim((string) ($row[$col['meta_description']] ?? '')), 0, 255) : null;

        $categoryId = null;
        if (isset($col['category']) && trim((string) ($row[$col['category']] ?? '')) !== '') {
            $catVal = trim($row[$col['category']]);
            $findCategory->execute([slugify($catVal), $catVal]);
            $categoryId = (int) $findCategory->fetchColumn() ?: null;
            if (!$categoryId) {
                $hints = $suggestCategories($catVal);
                $results['skipped'][] = "Row {$results['row']}: unknown category \"{$catVal}\"."
                    . ($hints ? ' Did you mean: ' . implode(', ', $hints) . '?' : ' Create it first in /admin/blog_categories.');
                continue;
            }
        }

        $status = $publish ? 'published' : 'draft';
        $publishedAt = $publish ? date('Y-m-d H:i:s') : null;
        $insert->execute([$admin['id'], $categoryId, $title, unique_slug('blog_posts', $title), $excerpt ?: null, $body, $metaDescription ?: null, $status, $publishedAt]);
        $results['imported']++;
    }
    fclose($fh);
    audit_log($admin['id'], 'blog_posts_imported', 'blog_post', null, $results['imported'] . ' imported, ' . count($results['skipped']) . ' skipped');
}
?>
<h1 class="text-2xl font-bold mb-4">Import blog posts from CSV</h1>

<?php if ($results): ?>
  <div class="bg-white border rounded-lg p-4 mb-6">
    <p class="text-sm font-medium text-green-700">✅ Imported <?= (int) $results['imported'] ?> post<?= $results['imported'] === 1 ? '' : 's' ?>.</p>
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
    <code class="bg-slate-100 px-1 rounded">excerpt</code>, <code class="bg-slate-100 px-1 rounded">category</code>
    (name or slug) and <code class="bg-slate-100 px-1 rounded">meta_description</code> optional.
    Markdown is supported in the body. Max 500 imports per upload; exact-title duplicates are skipped.
    Files exported from Excel or Google Sheets work as-is.
  </p>
  <p class="text-sm mb-4">
    <a href="/assets/sample_blog_posts.csv" download class="text-indigo-600 hover:underline">⬇ Download a sample CSV</a>
    <span class="text-slate-400">— shows the exact format.</span>
    &middot; <a href="/admin/blog_categories" class="text-indigo-600 hover:underline">Manage categories</a> before importing if your CSV references new ones.
  </p>
  <form method="post" enctype="multipart/form-data" class="space-y-4">
    <?= csrf_field() ?>
    <div>
      <label for="imp_csv" class="block text-sm font-medium mb-1">CSV file</label>
      <input id="imp_csv" type="file" name="csv" accept=".csv,text/csv" required class="text-sm">
    </div>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="publish" value="1">
      Publish immediately (otherwise imported as drafts — review in <a href="/admin/blog" class="text-indigo-600 underline">Blog admin</a> first)
    </label>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm font-medium">Import</button>
  </form>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
