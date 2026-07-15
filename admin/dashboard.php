<?php
require __DIR__ . '/includes/admin_header.php';

$pdo = db();
$stats = [
    'Users' => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'Questions' => $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn(),
    'Answers' => $pdo->query('SELECT COUNT(*) FROM answers')->fetchColumn(),
    'Open reports' => $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn(),
];
?>
<h1 class="text-2xl font-bold mb-4">Dashboard</h1>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
  <?php foreach ($stats as $label => $value): ?>
    <div class="bg-white border rounded-lg p-4">
      <div class="text-2xl font-bold"><?= (int) $value ?></div>
      <div class="text-sm text-slate-500"><?= e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
