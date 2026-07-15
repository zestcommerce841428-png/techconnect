<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Analytics — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

function daily_counts(PDO $pdo, string $table, string $dateColumn = 'created_at', int $days = 30): array
{
    $stmt = $pdo->prepare(
        "SELECT DATE($dateColumn) AS d, COUNT(*) AS c FROM $table
         WHERE $dateColumn >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE($dateColumn) ORDER BY d ASC"
    );
    $stmt->execute([$days]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $result[$date] = (int) ($rows[$date] ?? 0);
    }
    return $result;
}

$signups = daily_counts($pdo, 'users');
$questionsPerDay = daily_counts($pdo, 'questions');
$answersPerDay = daily_counts($pdo, 'answers');

$totals = [
    'Total users' => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'Total questions' => $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn(),
    'Total answers' => $pdo->query('SELECT COUNT(*) FROM answers')->fetchColumn(),
    'Answered rate' => (function () use ($pdo) {
        $total = (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
        $answered = (int) $pdo->query('SELECT COUNT(*) FROM questions WHERE answer_count > 0')->fetchColumn();
        return $total ? round($answered / $total * 100) . '%' : 'N/A';
    })(),
];
?>
<h1 class="text-2xl font-bold mb-4">Analytics</h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <?php foreach ($totals as $label => $value): ?>
    <div class="bg-white border rounded-lg p-4">
      <div class="text-2xl font-bold"><?= e((string) $value) ?></div>
      <div class="text-sm text-slate-500"><?= e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php
function render_chart(string $title, array $data): void {
    $max = max(1, max($data));
    echo '<div class="bg-white border rounded-lg p-4 mb-6"><h2 class="font-semibold text-sm mb-3">' . e($title) . ' (last 30 days)</h2>';
    echo '<div class="flex items-end gap-0.5 h-32">';
    foreach ($data as $date => $count) {
        $heightPct = max(2, round($count / $max * 100));
        echo '<div class="flex-1 bg-indigo-500 rounded-t" style="height:' . $heightPct . '%" title="' . e($date) . ': ' . $count . '"></div>';
    }
    echo '</div></div>';
}
render_chart('New signups', $signups);
render_chart('New questions', $questionsPerDay);
render_chart('New answers', $answersPerDay);
?>

<div class="bg-white border rounded-lg p-4">
  <h2 class="font-semibold text-sm mb-2">Export data</h2>
  <div class="flex gap-2 text-sm">
    <a href="/admin/export.php?table=users" class="text-indigo-600 hover:underline">Users CSV</a>
    <a href="/admin/export.php?table=questions" class="text-indigo-600 hover:underline">Questions CSV</a>
    <a href="/admin/export.php?table=answers" class="text-indigo-600 hover:underline">Answers CSV</a>
    <a href="/admin/export.php?table=jobs" class="text-indigo-600 hover:underline">Jobs CSV</a>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
