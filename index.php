<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$questions = $pdo->query(
    "SELECT q.*, u.username,
            GROUP_CONCAT(t.name SEPARATOR ',') AS tags
     FROM questions q
     JOIN users u ON u.id = q.user_id
     LEFT JOIN question_tags qt ON qt.question_id = q.id
     LEFT JOIN tags t ON t.id = qt.tag_id
     GROUP BY q.id
     ORDER BY q.is_sponsored DESC, q.created_at DESC
     LIMIT 20"
)->fetchAll();

$pageTitle = SITE_NAME . ' — Ask, answer, connect on tech';
require __DIR__ . '/includes/header.php';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-bold">Recent questions</h1>
  <a href="/questions.php" class="text-indigo-600 hover:underline text-sm">Browse all &rarr;</a>
</div>

<?php if (!$questions): ?>
  <div class="border rounded-lg p-8 text-center bg-white">
    <p class="text-slate-600 mb-3">No questions yet. Be the first to ask something!</p>
    <a href="/ask.php" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Ask a question</a>
  </div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($questions as $q): ?>
      <?php require __DIR__ . '/includes/question_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
