<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$targetId = (int) ($_GET['u'] ?? (current_user()['id'] ?? 0));
$stmt = $pdo->prepare('SELECT id, username, reputation FROM users WHERE id = ?');
$stmt->execute([$targetId]);
$target = $stmt->fetch();

if (!$target) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$events = $pdo->prepare('SELECT * FROM reputation_events WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$events->execute([$targetId]);
$events = $events->fetchAll();

$labels = [
    'upvoted' => 'Content upvoted',
    'downvoted' => 'Content downvoted',
    'answer_accepted' => 'Answer accepted',
    'bounty_awarded' => 'Bounty received',
    'bounty_offered' => 'Bounty offered',
];

$pageTitle = e($target['username']) . "'s reputation — " . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-1">Reputation history</h1>
<p class="text-sm text-slate-500 mb-4"><a href="/u/<?= e($target['username']) ?>" class="text-indigo-600 hover:underline"><?= e($target['username']) ?></a> · current total: <strong><?= (int) $target['reputation'] ?></strong></p>

<div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y max-w-2xl">
  <?php foreach ($events as $ev): ?>
    <div class="p-3 flex items-center justify-between text-sm">
      <div>
        <span><?= e($labels[$ev['reason']] ?? $ev['reason']) ?></span>
        <span class="text-xs text-slate-500 ml-2"><?= time_ago($ev['created_at']) ?></span>
      </div>
      <span class="font-semibold <?= $ev['points'] > 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $ev['points'] > 0 ? '+' : '' ?><?= (int) $ev['points'] ?></span>
    </div>
  <?php endforeach; ?>
  <?php if (!$events): ?><div class="p-4 text-sm text-slate-500">No reputation changes recorded yet. (Ledger tracking started recently — older reputation is reflected in the total above.)</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
