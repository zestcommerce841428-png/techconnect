<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$search = trim($_GET['q'] ?? '');
$tagSlug = trim($_GET['tag'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$scope = $_GET['scope'] ?? 'all'; // all | mine-country | mine-city
$sort = $_GET['sort'] ?? 'recent'; // recent | votes | unanswered
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$user = current_user();
$where = [];
$params = [];

if ($search !== '') {
    $where[] = 'MATCH(q.title, q.body) AGAINST (? IN NATURAL LANGUAGE MODE)';
    $params[] = $search;
}
if ($tagSlug !== '') {
    $where[] = 'q.id IN (SELECT qt.question_id FROM question_tags qt JOIN tags t ON t.id = qt.tag_id WHERE t.slug = ?)';
    $params[] = $tagSlug;
}
$activeCategory = null;
if ($categorySlug !== '') {
    $catStmt = $pdo->prepare('SELECT * FROM categories WHERE slug = ?');
    $catStmt->execute([$categorySlug]);
    $activeCategory = $catStmt->fetch();
    if ($activeCategory) {
        $where[] = 'q.category_id = ?';
        $params[] = $activeCategory['id'];
    }
}
if ($scope === 'mine-country' && $user && !empty($user['location_country'] ?? null)) {
    $where[] = 'q.location_country = ?';
    $params[] = $user['location_country'];
} elseif ($scope === 'mine-city' && $user && !empty($user['location_city'] ?? null)) {
    $where[] = 'q.location_city = ?';
    $params[] = $user['location_city'];
}

$orderBy = match ($sort) {
    'votes' => 'q.vote_score DESC, q.created_at DESC',
    'unanswered' => 'q.answer_count ASC, q.created_at DESC',
    default => 'q.created_at DESC',
};

$where[] = "q.group_id IS NULL AND q.status != 'draft' AND q.merged_into_id IS NULL"; // group questions are shown on their group page only
$whereSql = 'WHERE ' . implode(' AND ', $where);
$offset = paginate_offset($page, $perPage);

$sql = "SELECT q.*, u.username, c.name AS category_name, c.icon AS category_icon,
               GROUP_CONCAT(t.name SEPARATOR ',') AS tags
        FROM questions q
        JOIN users u ON u.id = q.user_id
        LEFT JOIN categories c ON c.id = q.category_id
        LEFT JOIN question_tags qt ON qt.question_id = q.id
        LEFT JOIN tags t ON t.id = qt.tag_id
        $whereSql
        GROUP BY q.id
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

$popularTags = $pdo->query('SELECT name, slug FROM tags ORDER BY use_count DESC LIMIT 15')->fetchAll();
$allCategories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();

$pageTitle = ($activeCategory ? $activeCategory['name'] . ' questions' : 'Browse questions') . ' — ' . SITE_NAME;

// Thin content. A category/tag page holding one question is a near-empty page,
// and the catalog mass-produces ~700 of them — indexed together they read as a
// site-wide quality problem, not merely 700 weak pages, and that can suppress
// the pages worth ranking. So the bar is "has real content", not "is non-empty":
// stay crawlable (follow keeps the links out useful) but stay out of the index
// until the page earns it. Categories index themselves as they fill up; nothing
// needs to be flipped by hand.
//
// Only page 1 is judged: there, count($questions) is the true total for anything
// below $perPage, so the threshold is exact. Deeper pages of a paginated listing
// belong to a category that already cleared the bar.
const THIN_LISTING_MIN_QUESTIONS = 3;
$isFilteredListing = $categorySlug !== '' || $tagSlug !== '';
if ($isFilteredListing && $page === 1 && count($questions) < THIN_LISTING_MIN_QUESTIONS) {
    $pageRobots = 'noindex, follow';
}
require __DIR__ . '/includes/header.php';
?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
  <div class="md:col-span-3">
    <?php if ($activeCategory): ?>
      <div class="mb-3 flex items-center gap-2">
        <span class="text-lg"><?= e($activeCategory['icon'] ?? '') ?></span>
        <h1 class="text-xl font-bold"><?= e($activeCategory['name']) ?></h1>
        <a href="/questions" class="text-xs text-indigo-600 hover:underline">Clear category</a>
      </div>
    <?php endif; ?>
    <form method="get" class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2 mb-4">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search questions..." class="col-span-2 sm:flex-1 sm:min-w-[200px] border rounded-lg px-3 py-2">
      <select name="category" class="border rounded-lg px-3 py-2 min-w-0">
        <option value="">All categories</option>
        <?php foreach ($allCategories as $c): ?>
          <option value="<?= e($c['slug']) ?>" <?= $categorySlug === $c['slug'] ? 'selected' : '' ?>><?= e($c['icon'] ? $c['icon'] . ' ' : '') . e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="scope" class="border rounded-lg px-3 py-2 min-w-0">
        <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>Worldwide</option>
        <option value="mine-country" <?= $scope === 'mine-country' ? 'selected' : '' ?>>My country</option>
        <option value="mine-city" <?= $scope === 'mine-city' ? 'selected' : '' ?>>My city</option>
      </select>
      <select name="sort" class="border rounded-lg px-3 py-2 min-w-0">
        <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most recent</option>
        <option value="votes" <?= $sort === 'votes' ? 'selected' : '' ?>>Top voted</option>
        <option value="unanswered" <?= $sort === 'unanswered' ? 'selected' : '' ?>>Unanswered</option>
      </select>
      <?php if ($tagSlug !== ''): ?><input type="hidden" name="tag" value="<?= e($tagSlug) ?>"><?php endif; ?>
      <button type="submit" class="col-span-2 sm:col-auto bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg">Search</button>
    </form>

    <?php if (!$questions): ?>
      <div class="border rounded-lg p-8 text-center bg-white dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-400">
        <p class="mb-4">No questions match your filters.</p>
        <?php if ($search !== ''): ?>
          <p class="text-sm mb-3">Couldn't find an answer? Be the first to ask.</p>
          <a href="/ask?title=<?= urlencode($search) ?>" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Ask "<?= e(mb_strimwidth($search, 0, 60, '…')) ?>"</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($questions as $q): ?>
          <?php require __DIR__ . '/includes/question_card.php'; ?>
        <?php endforeach; ?>
      </div>
      <div class="flex justify-between mt-4 text-sm">
        <?php if ($page > 1): ?>
          <a class="text-indigo-600 hover:underline" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&larr; Previous</a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if (count($questions) === $perPage): ?>
          <a class="text-indigo-600 hover:underline" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &rarr;</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="md:col-span-1">
    <div class="bg-white border rounded-lg p-4">
      <h3 class="font-semibold mb-2 text-sm">Popular tags</h3>
      <div class="flex flex-wrap gap-1">
        <?php foreach ($popularTags as $t): ?>
          <a href="?tag=<?= e($t['slug']) ?>" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-0.5 rounded"><?= e($t['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
