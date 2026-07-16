<?php
$pageTitle = 'Dashboard — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

/** Scalar query that tolerates missing tables (mid-migration) instead of taking down the dashboard. */
function dash_count(string $sql, array $params = []): int
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// ---- Headline stats with 7-day deltas -------------------------------------
$tiles = [
    ['label' => 'Users', 'icon' => '👥', 'total' => dash_count('SELECT COUNT(*) FROM users'),
     'week' => dash_count('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')],
    ['label' => 'Questions', 'icon' => '❓', 'total' => dash_count("SELECT COUNT(*) FROM questions WHERE status <> 'draft'"),
     'week' => dash_count("SELECT COUNT(*) FROM questions WHERE status <> 'draft' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")],
    ['label' => 'Answers', 'icon' => '💬', 'total' => dash_count('SELECT COUNT(*) FROM answers'),
     'week' => dash_count('SELECT COUNT(*) FROM answers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')],
    ['label' => 'Page-worthy content', 'icon' => '📝', 'total' => dash_count('SELECT COUNT(*) FROM blog_posts WHERE status = "published"') + dash_count('SELECT COUNT(*) FROM pages WHERE is_published = 1'),
     'week' => dash_count('SELECT COUNT(*) FROM blog_posts WHERE status = "published" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')],
];

// ---- Needs-attention queue --------------------------------------------------
$queue = array_filter([
    ['label' => 'Open reports', 'count' => dash_count("SELECT COUNT(*) FROM reports WHERE status = 'open'"), 'href' => '/admin/moderation', 'critical' => true],
    ['label' => 'New feedback', 'count' => dash_count("SELECT COUNT(*) FROM feedback WHERE status = 'new'"), 'href' => '/admin/feedback', 'critical' => false],
    ['label' => 'Pending expert applications', 'count' => dash_count('SELECT COUNT(*) FROM expert_profiles WHERE is_approved = 0'), 'href' => '/admin/experts', 'critical' => false],
    ['label' => 'Suggested edits', 'count' => dash_count("SELECT COUNT(*) FROM suggested_edits WHERE status = 'pending'"), 'href' => '/admin/suggested_edits', 'critical' => false],
    ['label' => 'Unmoderated jobs', 'count' => dash_count("SELECT COUNT(*) FROM jobs WHERE status = 'pending'"), 'href' => '/admin/jobs', 'critical' => false],
], fn($q) => $q['count'] > 0);

// ---- 14-day activity series -------------------------------------------------
$series = ['questions' => [], 'answers' => [], 'signups' => []];
$labels = [];
try {
    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $days[$d] = true;
        $labels[] = date('j M', strtotime($d));
    }
    $fill = function (string $sql) use ($pdo, $days): array {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_map(fn($d) => (int) ($rows[$d] ?? 0), array_keys($days));
    };
    $series['questions'] = $fill("SELECT DATE(created_at), COUNT(*) FROM questions WHERE status <> 'draft' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)");
    $series['answers'] = $fill("SELECT DATE(created_at), COUNT(*) FROM answers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)");
    $series['signups'] = $fill("SELECT DATE(created_at), COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)");
} catch (Throwable $e) {
    $series = ['questions' => array_fill(0, 14, 0), 'answers' => array_fill(0, 14, 0), 'signups' => array_fill(0, 14, 0)];
}
$maxVal = max(1, max(array_merge(...array_values($series))));
// Validated categorical palette (fixed order): questions, answers, signups.
$chartColors = ['questions' => '#4f46e5', 'answers' => '#0d9488', 'signups' => '#d97706'];
$chartNames = ['questions' => 'Questions', 'answers' => 'Answers', 'signups' => 'New users'];

// ---- Recent items -------------------------------------------------------------
try {
    $recentQuestions = $pdo->query("SELECT q.id, q.title, q.slug, q.created_at, u.username FROM questions q JOIN users u ON u.id = q.user_id WHERE q.status <> 'draft' ORDER BY q.created_at DESC LIMIT 6")->fetchAll();
    $recentUsers = $pdo->query('SELECT id, username, email, created_at, reputation FROM users ORDER BY created_at DESC LIMIT 6')->fetchAll();
} catch (Throwable $e) {
    $recentQuestions = $recentUsers = [];
}
?>
<div class="flex flex-wrap items-center justify-between gap-2 mb-5">
  <h1 class="text-2xl font-bold">Dashboard</h1>
  <div class="text-sm text-slate-500"><?= e(date('l, j F Y')) ?></div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach ($tiles as $t): ?>
    <div class="bg-white border rounded-lg p-4">
      <div class="flex items-center justify-between">
        <span class="text-sm text-slate-500"><?= e($t['label']) ?></span>
        <span><?= $t['icon'] ?></span>
      </div>
      <div class="text-2xl font-bold mt-1"><?= number_format($t['total']) ?></div>
      <div class="text-xs mt-1 <?= $t['week'] > 0 ? 'text-green-600' : 'text-slate-400' ?>">
        <?= $t['week'] > 0 ? '+' . number_format($t['week']) . ' this week' : 'no change this week' ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($queue): ?>
  <div class="bg-white border rounded-lg p-4 mb-6">
    <h2 class="text-sm font-semibold mb-3">⚡ Needs attention</h2>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($queue as $q): ?>
        <a href="<?= e($q['href']) ?>" class="inline-flex items-center gap-2 text-sm px-3 py-1.5 rounded-full border <?= $q['critical'] ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' ?>">
          <?= e($q['label']) ?>
          <span class="font-bold"><?= (int) $q['count'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php else: ?>
  <div class="bg-white border rounded-lg p-4 mb-6 text-sm text-green-700">✅ All clear — no reports, feedback or approvals waiting.</div>
<?php endif; ?>

<div class="bg-white border rounded-lg p-4 mb-6">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <h2 class="text-sm font-semibold">Activity — last 14 days</h2>
    <div class="flex gap-4 text-xs text-slate-600">
      <?php foreach ($chartNames as $key => $name): ?>
        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:<?= $chartColors[$key] ?>"></span><?= e($name) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="flex items-end gap-1 h-36" role="img" aria-label="Daily questions, answers and signups for the last 14 days">
    <?php for ($i = 0; $i < 14; $i++): ?>
      <div class="flex-1 flex items-end justify-center gap-px h-full group relative" tabindex="0">
        <?php foreach ($series as $key => $vals): $h = round($vals[$i] / $maxVal * 100); ?>
          <div class="w-1/4 max-w-[10px] rounded-t-[3px]" style="height:<?= max($vals[$i] > 0 ? 4 : 1, $h) ?>%;background:<?= $vals[$i] > 0 ? $chartColors[$key] : '#e2e8f0' ?>"></div>
        <?php endforeach; ?>
        <div class="hidden group-hover:block group-focus:block absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-xs rounded px-2 py-1.5 whitespace-nowrap z-10">
          <span class="font-semibold"><?= e($labels[$i]) ?></span>
          <?php foreach ($series as $key => $vals): ?>
            · <?= e($chartNames[$key]) ?>: <?= $vals[$i] ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endfor; ?>
  </div>
  <div class="flex justify-between text-[10px] text-slate-400 mt-1">
    <span><?= e($labels[0]) ?></span><span><?= e($labels[6]) ?></span><span><?= e($labels[13]) ?></span>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <div class="bg-white border rounded-lg">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h2 class="text-sm font-semibold">Latest questions</h2>
      <a href="/questions" class="text-xs text-indigo-600 hover:underline">View all</a>
    </div>
    <div class="divide-y">
      <?php foreach ($recentQuestions as $q): ?>
        <a href="/q/<?= e($q['slug']) ?>" class="block px-4 py-2.5 hover:bg-slate-50 text-sm">
          <div class="font-medium truncate"><?= e($q['title']) ?></div>
          <div class="text-xs text-slate-500">by <?= e($q['username']) ?> · <?= time_ago($q['created_at']) ?></div>
        </a>
      <?php endforeach; ?>
      <?php if (!$recentQuestions): ?><div class="px-4 py-6 text-sm text-slate-400 text-center">No questions yet.</div><?php endif; ?>
    </div>
  </div>
  <div class="bg-white border rounded-lg">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h2 class="text-sm font-semibold">Newest members</h2>
      <a href="/admin/users" class="text-xs text-indigo-600 hover:underline">Manage users</a>
    </div>
    <div class="divide-y">
      <?php foreach ($recentUsers as $u): ?>
        <div class="px-4 py-2.5 flex items-center justify-between text-sm">
          <div class="min-w-0">
            <div class="font-medium truncate"><?= e($u['username']) ?></div>
            <div class="text-xs text-slate-500 truncate"><?= e($u['email']) ?></div>
          </div>
          <div class="text-xs text-slate-400 shrink-0 ml-3"><?= time_ago($u['created_at']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$recentUsers): ?><div class="px-4 py-6 text-sm text-slate-400 text-center">No users yet.</div><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
