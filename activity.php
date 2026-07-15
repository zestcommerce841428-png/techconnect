<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

$questions = $pdo->prepare("SELECT 'question' AS kind, title, slug, created_at FROM questions WHERE user_id = ?");
$questions->execute([$user['id']]);
$answers = $pdo->prepare(
    "SELECT 'answer' AS kind, q.title, q.slug, a.created_at FROM answers a JOIN questions q ON q.id = a.question_id WHERE a.user_id = ?"
);
$answers->execute([$user['id']]);
$votes = $pdo->prepare(
    "SELECT 'vote' AS kind, CASE WHEN v.votable_type = 'question' THEN q1.title ELSE q2.title END AS title,
            CASE WHEN v.votable_type = 'question' THEN q1.slug ELSE q2.slug END AS slug, v.created_at
     FROM votes v
     LEFT JOIN questions q1 ON v.votable_type = 'question' AND q1.id = v.votable_id
     LEFT JOIN answers a ON v.votable_type = 'answer' AND a.id = v.votable_id
     LEFT JOIN questions q2 ON q2.id = a.question_id
     WHERE v.user_id = ?"
);
$votes->execute([$user['id']]);

$events = array_merge($questions->fetchAll(), $answers->fetchAll(), $votes->fetchAll());
usort($events, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
$events = array_slice($events, 0, 100);

$labels = ['question' => 'Asked', 'answer' => 'Answered', 'vote' => 'Voted on'];
$icons = ['question' => '❓', 'answer' => '💬', 'vote' => '⬆️'];

$pageTitle = 'Your activity — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-4">Your activity</h1>
<div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y max-w-2xl">
  <?php foreach ($events as $ev): ?>
    <div class="p-3 flex items-center gap-3 text-sm">
      <span><?= $icons[$ev['kind']] ?? '•' ?></span>
      <div class="flex-1">
        <span class="text-slate-500"><?= $labels[$ev['kind']] ?? $ev['kind'] ?></span>
        <?php if ($ev['slug']): ?><a href="/q/<?= e($ev['slug']) ?>" class="text-indigo-600 hover:underline"><?= e($ev['title']) ?></a><?php else: ?><?= e($ev['title'] ?? '') ?><?php endif; ?>
      </div>
      <span class="text-xs text-slate-400"><?= time_ago($ev['created_at']) ?></span>
    </div>
  <?php endforeach; ?>
  <?php if (!$events): ?><div class="p-4 text-sm text-slate-500">No activity yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
