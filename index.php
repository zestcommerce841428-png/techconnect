<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$user = current_user();

$questions = $pdo->query(
    "SELECT q.*, u.username, c.name AS category_name, c.icon AS category_icon,
            GROUP_CONCAT(t.name SEPARATOR ',') AS tags
     FROM questions q
     JOIN users u ON u.id = q.user_id
     LEFT JOIN categories c ON c.id = q.category_id
     LEFT JOIN question_tags qt ON qt.question_id = q.id
     LEFT JOIN tags t ON t.id = qt.tag_id
     WHERE q.group_id IS NULL AND q.status != 'draft' AND q.merged_into_id IS NULL
     GROUP BY q.id
     ORDER BY q.is_sponsored DESC, q.created_at DESC
     LIMIT 20"
)->fetchAll();

$trending = $pdo->query(
    "SELECT q.*, u.username, c.name AS category_name, c.icon AS category_icon,
            GROUP_CONCAT(t.name SEPARATOR ',') AS tags
     FROM questions q
     JOIN users u ON u.id = q.user_id
     LEFT JOIN categories c ON c.id = q.category_id
     LEFT JOIN question_tags qt ON qt.question_id = q.id
     LEFT JOIN tags t ON t.id = qt.tag_id
     WHERE q.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND q.group_id IS NULL AND q.status != 'draft' AND q.merged_into_id IS NULL
     GROUP BY q.id
     ORDER BY q.vote_score DESC, q.view_count DESC
     LIMIT 5"
)->fetchAll();

$recommended = [];
if ($user) {
    $followedTagIds = $pdo->prepare("SELECT followable_id FROM follows WHERE follower_id = ? AND followable_type = 'tag'");
    $followedTagIds->execute([$user['id']]);
    $followedTagIds = $followedTagIds->fetchAll(PDO::FETCH_COLUMN);
    if ($followedTagIds) {
        $placeholders = implode(',', array_fill(0, count($followedTagIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT q.*, u.username, c.name AS category_name, c.icon AS category_icon,
                    GROUP_CONCAT(t.name SEPARATOR ',') AS tags
             FROM questions q
             JOIN users u ON u.id = q.user_id
             LEFT JOIN categories c ON c.id = q.category_id
             LEFT JOIN question_tags qt ON qt.question_id = q.id
             LEFT JOIN tags t ON t.id = qt.tag_id
             WHERE q.group_id IS NULL AND q.status != 'draft' AND q.merged_into_id IS NULL AND q.id IN (SELECT question_id FROM question_tags WHERE tag_id IN ($placeholders))
             GROUP BY q.id
             ORDER BY q.created_at DESC
             LIMIT 5"
        );
        $stmt->execute($followedTagIds);
        $recommended = $stmt->fetchAll();
    }
}

$categories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();

$pageTitle = setting('site_name', SITE_NAME) . ' — ' . setting('tagline', 'Ask, answer, connect');
// Onboarding checklist for logged-in users who haven't completed the basics yet.
$onboarding = null;
if ($user) {
    $steps = [
        'verify' => ['done' => !empty($user['email_verified_at']), 'label' => 'Verify your email', 'href' => '/profile'],
        'profile' => ['done' => !empty($user['bio']), 'label' => 'Add a short bio to your profile', 'href' => '/profile'],
        'ask' => ['done' => false, 'label' => 'Ask your first question', 'href' => '/ask'],
        'follow' => ['done' => false, 'label' => 'Follow a tag you care about', 'href' => '/questions'],
    ];
    $steps['ask']['done'] = (bool) $pdo->query('SELECT 1 FROM questions WHERE user_id = ' . (int) $user['id'] . ' LIMIT 1')->fetchColumn();
    $steps['follow']['done'] = (bool) $pdo->query("SELECT 1 FROM follows WHERE follower_id = " . (int) $user['id'] . " AND followable_type = 'tag' LIMIT 1")->fetchColumn();
    $remaining = array_filter($steps, fn($s) => !$s['done']);
    if ($remaining) $onboarding = $steps;
}

require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/ad_slots.php';
?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => setting('site_name', SITE_NAME),
    'url' => SITE_URL,
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => ['@type' => 'EntryPoint', 'urlTemplate' => SITE_URL . '/search?q={search_term_string}'],
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES) ?></script>
<?php if (!$user): ?>
<section class="mb-8 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white px-5 py-10 sm:px-10 text-center overflow-hidden">
  <h1 class="text-2xl sm:text-4xl font-extrabold tracking-tight break-words"><?= e(setting('site_name', SITE_NAME)) ?></h1>
  <p class="mt-3 text-indigo-100 max-w-md sm:max-w-xl mx-auto text-sm sm:text-base"><?= e(setting('tagline', 'Ask anything. Get real answers from people nearby and around the world.')) ?></p>
  <div class="mt-6 flex justify-center gap-3 flex-wrap">
    <a href="/register" class="bg-white text-indigo-700 font-semibold px-5 py-2.5 rounded-lg hover:bg-indigo-50">Join free</a>
    <a href="/questions" class="border border-white/60 px-5 py-2.5 rounded-lg hover:bg-white/10">Browse questions</a>
  </div>
</section>
<?php
$testimonials = $pdo->query('SELECT author_name, author_role, quote, rating FROM testimonials WHERE is_published = 1 ORDER BY sort_order, id DESC LIMIT 3')->fetchAll();
if ($testimonials): ?>
<section class="mb-8 grid sm:grid-cols-3 gap-4">
  <?php foreach ($testimonials as $t): ?>
    <div class="card p-4">
      <div class="text-amber-500 text-xs mb-1"><?= str_repeat('★', (int) $t['rating']) ?></div>
      <p class="text-sm text-slate-700 dark:text-slate-300">"<?= e($t['quote']) ?>"</p>
      <p class="text-xs text-slate-500 mt-2 font-medium"><?= e($t['author_name']) ?><?= $t['author_role'] ? ' — ' . e($t['author_role']) : '' ?></p>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php elseif ($onboarding): ?>
<section class="mb-6 bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-4">
  <h2 class="text-sm font-semibold mb-2">👋 Getting started (<?= count(array_filter($onboarding, fn($s) => $s['done'])) ?>/<?= count($onboarding) ?> done)</h2>
  <ul class="grid sm:grid-cols-2 gap-1.5 text-sm">
    <?php foreach ($onboarding as $step): ?>
      <li class="flex items-center gap-2 <?= $step['done'] ? 'text-slate-400 line-through' : '' ?>">
        <span><?= $step['done'] ? '✅' : '⬜' ?></span>
        <?php if ($step['done']): ?><?= e($step['label']) ?><?php else: ?><a href="<?= e($step['href']) ?>" class="text-indigo-600 hover:underline"><?= e($step['label']) ?></a><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?= ad_slot('homepage_banner') ?>
<div class="mb-6">
  <h2 class="text-sm font-semibold text-slate-500 mb-2">Browse by category</h2>
  <div class="flex flex-wrap gap-2">
    <?php foreach (array_slice($categories, 0, 20) as $c): ?>
      <a href="/c/<?= e($c['slug']) ?>" class="text-sm bg-white border rounded-full px-3 py-1.5 hover:border-indigo-400">
        <?= e($c['icon'] ? $c['icon'] . ' ' : '') . e($c['name']) ?>
      </a>
    <?php endforeach; ?>
    <?php if (count($categories) > 20): ?>
      <a href="/categories" class="text-sm border border-indigo-200 text-indigo-600 rounded-full px-3 py-1.5 hover:bg-indigo-50 font-medium">
        All <?= count($categories) ?> topics &rarr;
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($trending): ?>
  <div class="mb-6">
    <h2 class="text-lg font-bold mb-2">🔥 Trending this week</h2>
    <div class="space-y-2">
      <?php foreach ($trending as $q): ?>
        <?php require __DIR__ . '/includes/question_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($recommended): ?>
  <div class="mb-6">
    <h2 class="text-lg font-bold mb-2">From tags you follow</h2>
    <div class="space-y-2">
      <?php foreach ($recommended as $q): ?>
        <?php require __DIR__ . '/includes/question_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="flex items-center justify-between mb-4">
  <?php if ($user): ?>
    <h1 class="text-2xl font-bold">Recent questions</h1>
  <?php else: ?>
    <h2 class="text-2xl font-bold">Recent questions</h2>
  <?php endif; ?>
  <a href="/questions" class="text-indigo-600 hover:underline text-sm">Browse all &rarr;</a>
</div>

<?php if (!$questions): ?>
  <div class="border rounded-lg p-8 text-center bg-white">
    <p class="text-slate-600 mb-3">No questions yet. Be the first to ask something!</p>
    <a href="/ask" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Ask a question</a>
  </div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($questions as $q): ?>
      <?php require __DIR__ . '/includes/question_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
