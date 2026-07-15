<?php
/** @var array|null $user set by the including page (header.php) */
if (!$user) return;

$pdo = db();
$poll = $pdo->prepare(
    "SELECT * FROM site_polls WHERE is_active = 1 AND (closes_at IS NULL OR closes_at > NOW())
     AND id NOT IN (SELECT poll_id FROM site_poll_dismissals WHERE user_id = ?)
     ORDER BY created_at DESC LIMIT 1"
);
$poll->execute([$user['id']]);
$poll = $poll->fetch();
if (!$poll) return;

$myVote = $pdo->prepare('SELECT option_id FROM site_poll_votes WHERE poll_id = ? AND user_id = ?');
$myVote->execute([$poll['id'], $user['id']]);
$myVote = $myVote->fetchColumn();

$options = $pdo->prepare(
    "SELECT o.id, o.label, COUNT(v.user_id) AS votes FROM site_poll_options o
     LEFT JOIN site_poll_votes v ON v.option_id = o.id
     WHERE o.poll_id = ? GROUP BY o.id ORDER BY o.sort_order"
);
$options->execute([$poll['id']]);
$options = $options->fetchAll();
$totalVotes = array_sum(array_column($options, 'votes'));
?>
<div class="card p-4 mb-4 relative" data-site-poll>
  <form method="post" action="/api/site_poll_vote.php" class="absolute top-2 right-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="dismiss">
    <input type="hidden" name="poll_id" value="<?= (int) $poll['id'] ?>">
    <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
    <button type="submit" aria-label="Dismiss poll" class="text-slate-400 hover:text-slate-600 text-sm leading-none px-1">&times;</button>
  </form>
  <p class="font-medium text-sm pr-6 mb-2">📊 <?= e($poll['question']) ?></p>
  <?php if ($myVote): ?>
    <div class="space-y-1.5">
      <?php foreach ($options as $o): $pct = $totalVotes ? round($o['votes'] / $totalVotes * 100) : 0; ?>
        <div>
          <div class="flex justify-between text-xs text-slate-500"><span><?= e($o['label']) ?><?= (int) $o['id'] === (int) $myVote ? ' ✓' : '' ?></span><span><?= $pct ?>%</span></div>
          <div class="h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden"><div class="h-full bg-indigo-500" style="width: <?= $pct ?>%"></div></div>
        </div>
      <?php endforeach; ?>
      <p class="text-xs text-slate-400 mt-1"><?= $totalVotes ?> vote<?= $totalVotes === 1 ? '' : 's' ?></p>
    </div>
  <?php else: ?>
    <form method="post" action="/api/site_poll_vote.php" class="flex flex-wrap gap-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="vote">
      <input type="hidden" name="poll_id" value="<?= (int) $poll['id'] ?>">
      <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
      <?php foreach ($options as $o): ?>
        <button type="submit" name="option_id" value="<?= (int) $o['id'] ?>" class="text-xs border rounded-full px-3 py-1 hover:bg-indigo-50 dark:hover:bg-slate-800 hover:border-indigo-400"><?= e($o['label']) ?></button>
      <?php endforeach; ?>
    </form>
  <?php endif; ?>
</div>
