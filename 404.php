<?php
require_once __DIR__ . '/includes/db.php';

// Admin-configured redirects get first crack at any unmatched path.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
try {
    $stmt = db()->prepare('SELECT id, to_path, status_code FROM redirects WHERE from_path = ?');
    $stmt->execute([$requestPath]);
    $redirect = $stmt->fetch();
    if ($redirect) {
        db()->prepare('UPDATE redirects SET hits = hits + 1 WHERE id = ?')->execute([$redirect['id']]);
        header('Location: ' . $redirect['to_path'], true, (int) $redirect['status_code']);
        exit;
    }
} catch (Throwable $e) {
    // fall through to a normal 404 if the redirects table isn't available
}

require_once __DIR__ . '/includes/auth.php';
http_response_code(404);

/**
 * A 404 is a visitor about to leave. Mine the URL they tried for keywords and
 * offer real matches — a dead-end page converts far worse than a rescue attempt.
 */
$guess = preg_replace('~^/(q|c|tag|u|g|blog|page)/~', '', $requestPath);
$terms = array_values(array_filter(
    preg_split('/[^a-z0-9]+/i', (string) $guess) ?: [],
    fn($t) => mb_strlen($t) >= 3 && !in_array(strtolower($t), ['the', 'and', 'for', 'php', 'html', 'index', 'www'], true)
));

$suggestions = [];
$searchPrefill = trim(implode(' ', array_slice($terms, 0, 6)));
try {
    if ($terms) {
        $slice = array_slice($terms, 0, 4);
        $where = implode(' OR ', array_fill(0, count($slice), 'title LIKE ?'));
        $params = array_map(fn($t) => '%' . addcslashes($t, '%_\\') . '%', $slice);
        $sug = db()->prepare(
            "SELECT title, slug FROM questions
             WHERE status <> 'draft' AND merged_into_id IS NULL AND group_id IS NULL AND ($where)
             ORDER BY view_count DESC LIMIT 5"
        );
        $sug->execute($params);
        $suggestions = $sug->fetchAll();
    }
    // Fall back to the community's most-viewed questions so the page is never a dead end.
    if (!$suggestions) {
        $suggestions = db()->query(
            "SELECT title, slug FROM questions
             WHERE status <> 'draft' AND merged_into_id IS NULL AND group_id IS NULL
             ORDER BY view_count DESC LIMIT 5"
        )->fetchAll();
        $terms = [];
    }
} catch (Throwable $e) {
    $suggestions = [];
}

$pageTitle = 'Page not found — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-xl mx-auto text-center py-12">
  <div class="text-6xl font-bold text-slate-200 dark:text-slate-700 mb-3">404</div>
  <h1 class="text-xl font-semibold mb-2">Page not found</h1>
  <p class="text-slate-500 mb-6 text-sm">That page doesn't exist or was moved — but the answer you want might still be here.</p>

  <form action="/search" method="get" role="search" class="flex gap-2 mb-8">
    <label for="nf-search" class="sr-only">Search questions</label>
    <input id="nf-search" type="search" name="q" value="<?= e($searchPrefill) ?>" autofocus
           placeholder="Search questions…" class="flex-1 border rounded-lg px-3 py-2.5 text-sm">
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2.5 rounded-lg text-sm font-medium">Search</button>
  </form>

  <?php if ($suggestions): ?>
    <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y dark:divide-slate-800 text-left">
      <div class="px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wide">
        <?= $terms ? 'Questions that might match' : 'Popular questions' ?>
      </div>
      <?php foreach ($suggestions as $s): ?>
        <a href="/q/<?= e($s['slug']) ?>" class="block px-4 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 truncate"><?= e($s['title']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="flex flex-wrap justify-center gap-2 mt-8 text-sm">
    <a href="/" class="px-4 py-2 rounded-lg border bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800">🏠 Home</a>
    <a href="/questions" class="px-4 py-2 rounded-lg border bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800">📋 All questions</a>
    <a href="/categories" class="px-4 py-2 rounded-lg border bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800">🗂️ Browse topics</a>
    <a href="/ask" class="px-4 py-2 rounded-lg border bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800">✍️ Ask a question</a>
  </div>
  <p class="text-xs text-slate-400 mt-6">Think this page should exist? <a href="/feedback?type=bug&amp;page=<?= urlencode($requestPath) ?>" class="text-indigo-600 hover:underline">Report a broken link</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
