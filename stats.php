<?php
require_once __DIR__ . '/includes/auth.php';

// Community stats are cheap but not free — cache the computed numbers for
// 10 minutes in the settings table so hitting this page never hammers the DB.
$cache = null;
try {
    $row = db()->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'stats_cache'");
    $row->execute();
    $decoded = json_decode((string) $row->fetchColumn(), true);
    if (is_array($decoded) && ($decoded['at'] ?? 0) > time() - 600) {
        $cache = $decoded;
    }
} catch (Throwable $e) {
}

if (!$cache) {
    $pdo = db();
    $count = fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();
    $cache = [
        'at' => time(),
        'users' => $count('SELECT COUNT(*) FROM users'),
        'questions' => $count("SELECT COUNT(*) FROM questions WHERE status <> 'draft'"),
        'answers' => $count('SELECT COUNT(*) FROM answers'),
        'accepted' => $count('SELECT COUNT(*) FROM answers WHERE is_accepted = 1'),
        'categories' => $count('SELECT COUNT(*) FROM categories WHERE is_active = 1'),
        'this_week_q' => $count("SELECT COUNT(*) FROM questions WHERE status <> 'draft' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        'this_week_u' => $count('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
        'top_categories' => $pdo->query(
            "SELECT c.name, c.slug, c.icon, COUNT(q.id) AS n FROM categories c
             JOIN questions q ON q.category_id = c.id AND q.status <> 'draft'
             GROUP BY c.id ORDER BY n DESC LIMIT 8"
        )->fetchAll(),
        'top_users' => $pdo->query(
            'SELECT username, reputation FROM users ORDER BY reputation DESC LIMIT 8'
        )->fetchAll(),
    ];
    try {
        db()->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('stats_cache', ?)
                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
            ->execute([json_encode($cache)]);
    } catch (Throwable $e) {
    }
}

$answerRate = $cache['questions'] > 0 ? round($cache['accepted'] / max(1, $cache['questions']) * 100) : 0;

$pageTitle = 'Community stats — ' . SITE_NAME;
$pageDescription = 'Live statistics for the ' . SITE_NAME . ' community: members, questions, answers and top contributors.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-4xl mx-auto">
  <h1 class="text-2xl font-bold mb-1">Community stats</h1>
  <p class="text-sm text-slate-500 mb-6"><?= e(SITE_NAME) ?> in numbers — updated every few minutes.</p>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <?php foreach ([
        ['Members', $cache['users'], '👥'],
        ['Questions', $cache['questions'], '❓'],
        ['Answers', $cache['answers'], '💬'],
        ['Solved rate', $answerRate . '%', '✅'],
    ] as [$label, $value, $icon]): ?>
      <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-4 text-center">
        <div class="text-2xl mb-1"><?= $icon ?></div>
        <div class="text-2xl font-bold"><?= is_int($value) ? number_format($value) : e((string) $value) ?></div>
        <div class="text-xs text-slate-500"><?= e($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-5">
      <h2 class="text-sm font-semibold mb-3">Most active topics</h2>
      <?php if ($cache['top_categories']): ?>
        <ul class="space-y-2 text-sm">
          <?php foreach ($cache['top_categories'] as $c): ?>
            <li class="flex items-center justify-between gap-2">
              <a href="/c/<?= e($c['slug']) ?>" class="hover:underline truncate"><?= e(($c['icon'] ? $c['icon'] . ' ' : '') . $c['name']) ?></a>
              <span class="text-xs text-slate-500 shrink-0"><?= (int) $c['n'] ?> question<?= (int) $c['n'] === 1 ? '' : 's' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-sm text-slate-500">No questions yet — <a href="/ask" class="text-indigo-600 hover:underline">ask the first one</a>.</p>
      <?php endif; ?>
    </div>
    <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-5">
      <h2 class="text-sm font-semibold mb-3">Top contributors</h2>
      <ul class="space-y-2 text-sm">
        <?php foreach ($cache['top_users'] as $i => $u): ?>
          <li class="flex items-center justify-between gap-2">
            <a href="/u/<?= e($u['username']) ?>" class="hover:underline truncate"><?= ['🥇','🥈','🥉'][$i] ?? '·' ?> <?= e($u['username']) ?></a>
            <span class="text-xs text-slate-500 shrink-0"><?= number_format((int) $u['reputation']) ?> rep</span>
          </li>
        <?php endforeach; ?>
      </ul>
      <a href="/leaderboard" class="inline-block mt-3 text-xs text-indigo-600 hover:underline">Full leaderboard →</a>
    </div>
  </div>

  <p class="text-xs text-slate-400 mt-6">
    This week: <?= number_format($cache['this_week_q']) ?> new questions · <?= number_format($cache['this_week_u']) ?> new members ·
    <?= number_format($cache['categories']) ?> active topics
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
