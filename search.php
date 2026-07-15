<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$q = trim($_GET['q'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$tagSlug = trim($_GET['tag'] ?? '');
$country = trim($_GET['country'] ?? '');
$dateRange = $_GET['date'] ?? 'any'; // any | week | month | year
$answered = $_GET['answered'] ?? 'any'; // any | answered | unanswered
$sort = $_GET['sort'] ?? 'relevance'; // relevance | recent | votes
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = 'MATCH(qq.title, qq.body) AGAINST (? IN NATURAL LANGUAGE MODE)';
    $params[] = $q;
}
if ($categorySlug !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $categorySlug;
}
if ($tagSlug !== '') {
    $where[] = 'qq.id IN (SELECT qt.question_id FROM question_tags qt JOIN tags t ON t.id = qt.tag_id WHERE t.slug = ?)';
    $params[] = $tagSlug;
}
if ($country !== '') {
    $where[] = 'qq.location_country = ?';
    $params[] = $country;
}
if ($dateRange !== 'any') {
    $interval = match ($dateRange) {
        'week' => '7 DAY',
        'month' => '30 DAY',
        'year' => '365 DAY',
        default => null,
    };
    if ($interval) {
        $where[] = "qq.created_at >= DATE_SUB(NOW(), INTERVAL $interval)";
    }
}
if ($answered === 'answered') {
    $where[] = 'qq.answer_count > 0';
} elseif ($answered === 'unanswered') {
    $where[] = 'qq.answer_count = 0';
}

$orderBy = match ($sort) {
    'recent' => 'qq.created_at DESC',
    'votes' => 'qq.vote_score DESC, qq.created_at DESC',
    default => $q !== '' ? 'MATCH(qq.title, qq.body) AGAINST (' . $pdo->quote($q) . ' IN NATURAL LANGUAGE MODE) DESC' : 'qq.created_at DESC',
};

$where[] = "qq.group_id IS NULL AND qq.status != 'draft' AND qq.merged_into_id IS NULL"; // group questions are shown on their group page only
$whereSql = 'WHERE ' . implode(' AND ', $where);
$offset = paginate_offset($page, $perPage);

$sql = "SELECT qq.*, u.username, c.name AS category_name, c.icon AS category_icon,
               GROUP_CONCAT(t.name SEPARATOR ',') AS tags
        FROM questions qq
        JOIN users u ON u.id = qq.user_id
        LEFT JOIN categories c ON c.id = qq.category_id
        LEFT JOIN question_tags qt ON qt.question_id = qq.id
        LEFT JOIN tags t ON t.id = qt.tag_id
        $whereSql
        GROUP BY qq.id
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$allCategories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$countries = $pdo->query('SELECT DISTINCT location_country FROM questions WHERE location_country IS NOT NULL ORDER BY location_country')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = ($q !== '' ? 'Search: ' . $q : 'Search') . ' — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 overflow-hidden">
  <div class="md:col-span-1 min-w-0 max-w-full overflow-hidden">
    <form method="get" class="bg-white border rounded-lg p-4 space-y-3 max-w-full">
      <div>
        <label class="block text-xs font-medium mb-1">Keyword</label>
        <input type="text" name="q" value="<?= e($q) ?>" class="w-full border rounded px-2 py-1.5 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Category</label>
        <select name="category" class="w-full border rounded px-2 py-1.5 text-sm">
          <option value="">Any</option>
          <?php foreach ($allCategories as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= $categorySlug === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Tag</label>
        <input type="text" name="tag" value="<?= e($tagSlug) ?>" placeholder="e.g. php" class="w-full border rounded px-2 py-1.5 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Country</label>
        <select name="country" class="w-full border rounded px-2 py-1.5 text-sm">
          <option value="">Any</option>
          <?php foreach ($countries as $c): ?>
            <option value="<?= e($c) ?>" <?= $country === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Posted</label>
        <select name="date" class="w-full border rounded px-2 py-1.5 text-sm">
          <option value="any" <?= $dateRange === 'any' ? 'selected' : '' ?>>Any time</option>
          <option value="week" <?= $dateRange === 'week' ? 'selected' : '' ?>>Past week</option>
          <option value="month" <?= $dateRange === 'month' ? 'selected' : '' ?>>Past month</option>
          <option value="year" <?= $dateRange === 'year' ? 'selected' : '' ?>>Past year</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Status</label>
        <select name="answered" class="w-full border rounded px-2 py-1.5 text-sm">
          <option value="any" <?= $answered === 'any' ? 'selected' : '' ?>>Any</option>
          <option value="answered" <?= $answered === 'answered' ? 'selected' : '' ?>>Answered</option>
          <option value="unanswered" <?= $answered === 'unanswered' ? 'selected' : '' ?>>Unanswered</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Sort</label>
        <select name="sort" class="w-full border rounded px-2 py-1.5 text-sm">
          <option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>Relevance</option>
          <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most recent</option>
          <option value="votes" <?= $sort === 'votes' ? 'selected' : '' ?>>Top voted</option>
        </select>
      </div>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-2 rounded text-sm">Apply filters</button>
    </form>
  </div>

  <div class="md:col-span-3">
    <h1 class="text-xl font-bold mb-4"><?= $q !== '' ? 'Results for "' . e($q) . '"' : 'Search questions' ?></h1>
    <?php if (!$results): ?>
      <div class="border rounded-lg p-8 text-center bg-white dark:bg-slate-900 dark:border-slate-800 text-slate-600 dark:text-slate-400">
        <p class="mb-4">No questions match these filters.</p>
        <?php if ($q !== ''): ?>
          <p class="text-sm mb-3">Couldn't find an answer? Be the first to ask.</p>
          <a href="/ask?title=<?= urlencode($q) ?>" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Ask "<?= e(mb_strimwidth($q, 0, 60, '…')) ?>"</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($results as $q): ?>
          <?php require __DIR__ . '/includes/question_card.php'; ?>
        <?php endforeach; ?>
      </div>
      <div class="flex justify-between mt-4 text-sm">
        <?php if ($page > 1): ?>
          <a class="text-indigo-600 hover:underline" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&larr; Previous</a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if (count($results) === $perPage): ?>
          <a class="text-indigo-600 hover:underline" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &rarr;</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
