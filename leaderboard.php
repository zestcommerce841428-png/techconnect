<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$period = $_GET['period'] ?? 'all'; // week | month | all

$dateFilter = match ($period) {
    'week' => 'AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
    'month' => 'AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    default => '',
};

if ($period === 'all') {
    $leaders = $pdo->query(
        'SELECT id, username, reputation,
                (SELECT COUNT(*) FROM answers WHERE user_id = users.id AND is_accepted = 1) AS accepted_count
         FROM users
         ORDER BY reputation DESC
         LIMIT 50'
    )->fetchAll();
} else {
    $sql = "SELECT u.id, u.username, u.reputation,
                   COUNT(a.id) AS period_answers,
                   SUM(CASE WHEN a.is_accepted = 1 THEN 1 ELSE 0 END) AS accepted_count
            FROM answers a
            JOIN users u ON u.id = a.user_id
            WHERE 1=1 $dateFilter
            GROUP BY u.id, u.username, u.reputation
            ORDER BY period_answers DESC, accepted_count DESC
            LIMIT 50";
    $leaders = $pdo->query($sql)->fetchAll();
}

$pageTitle = 'Leaderboard — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-4">Leaderboard</h1>
<div class="flex gap-2 mb-4">
  <a href="?period=week" class="text-sm px-3 py-1.5 rounded <?= $period === 'week' ? 'bg-indigo-600 text-white' : 'bg-white border' ?>">This week</a>
  <a href="?period=month" class="text-sm px-3 py-1.5 rounded <?= $period === 'month' ? 'bg-indigo-600 text-white' : 'bg-white border' ?>">This month</a>
  <a href="?period=all" class="text-sm px-3 py-1.5 rounded <?= $period === 'all' ? 'bg-indigo-600 text-white' : 'bg-white border' ?>">All time</a>
</div>

<div class="bg-white border rounded-lg overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-100 text-left">
      <tr><th class="p-3">#</th><th class="p-3">User</th><th class="p-3">Reputation</th><th class="p-3">Accepted answers</th></tr>
    </thead>
    <tbody>
      <?php foreach ($leaders as $i => $l): ?>
        <tr class="border-t">
          <td class="p-3 text-slate-400"><?= $i + 1 ?></td>
          <td class="p-3"><a href="/profile?u=<?= $l['id'] ?>" class="text-indigo-600 hover:underline"><?= e($l['username']) ?></a></td>
          <td class="p-3"><?= (int) $l['reputation'] ?></td>
          <td class="p-3"><?= (int) $l['accepted_count'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if (!$leaders): ?><p class="text-slate-500 mt-3">No activity yet.</p><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
