<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$pdo = db();

$questions = $pdo->prepare(
    "SELECT q.*, u.username, c.name AS category_name, c.icon AS category_icon,
            GROUP_CONCAT(t.name SEPARATOR ',') AS tags
     FROM saved_questions sq
     JOIN questions q ON q.id = sq.question_id
     JOIN users u ON u.id = q.user_id
     LEFT JOIN categories c ON c.id = q.category_id
     LEFT JOIN question_tags qt ON qt.question_id = q.id
     LEFT JOIN tags t ON t.id = qt.tag_id
     WHERE sq.user_id = ?
     GROUP BY q.id
     ORDER BY sq.created_at DESC"
);
$questions->execute([$user['id']]);
$questions = $questions->fetchAll();

$pageTitle = 'Saved questions — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-4">Saved questions</h1>
<?php if (!$questions): ?>
  <div class="border rounded-lg p-8 text-center bg-white text-slate-600">You haven't saved any questions yet.</div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($questions as $q): ?>
      <?php require __DIR__ . '/includes/question_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
