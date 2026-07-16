<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$pdo = db();

$followedUserIds = $pdo->prepare("SELECT followable_id FROM follows WHERE follower_id = ? AND followable_type = 'user'");
$followedUserIds->execute([$user['id']]);
$followedUserIds = $followedUserIds->fetchAll(PDO::FETCH_COLUMN);

$followedTagIds = $pdo->prepare("SELECT followable_id FROM follows WHERE follower_id = ? AND followable_type = 'tag'");
$followedTagIds->execute([$user['id']]);
$followedTagIds = $followedTagIds->fetchAll(PDO::FETCH_COLUMN);

$questions = [];
if ($followedUserIds || $followedTagIds) {
    $where = [];
    $params = [];
    if ($followedUserIds) {
        $where[] = 'q.user_id IN (' . implode(',', array_fill(0, count($followedUserIds), '?')) . ')';
        $params = array_merge($params, $followedUserIds);
    }
    if ($followedTagIds) {
        $where[] = 'q.id IN (SELECT question_id FROM question_tags WHERE tag_id IN (' . implode(',', array_fill(0, count($followedTagIds), '?')) . '))';
        $params = array_merge($params, $followedTagIds);
    }
    $sql = "SELECT q.*, u.username, c.name AS category_name, c.icon AS category_icon,
                   GROUP_CONCAT(t.name SEPARATOR ',') AS tags
            FROM questions q
            JOIN users u ON u.id = q.user_id
            LEFT JOIN categories c ON c.id = q.category_id
            LEFT JOIN question_tags qt ON qt.question_id = q.id
            LEFT JOIN tags t ON t.id = qt.tag_id
            WHERE q.group_id IS NULL AND q.status != 'draft' AND q.merged_into_id IS NULL AND (" . implode(' OR ', $where) . ")
            GROUP BY q.id
            ORDER BY q.created_at DESC
            LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll();
}

$pageTitle = 'Your feed — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-4">Your feed</h1>
<?php if (!$followedUserIds && !$followedTagIds): ?>
  <div class="border rounded-lg p-8 text-center bg-white text-slate-600">
    Follow people or tags to see their activity here. Try following a tag from any question page.
  </div>
<?php elseif (!$questions): ?>
  <div class="border rounded-lg p-8 text-center bg-white text-slate-600">No recent activity from who/what you follow yet.</div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($questions as $q): ?>
      <?php require __DIR__ . '/includes/question_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
