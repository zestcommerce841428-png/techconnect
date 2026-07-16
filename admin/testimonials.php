<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Testimonials — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['author_name'] ?? '');
        $role = trim($_POST['author_role'] ?? '');
        $quote = trim($_POST['quote'] ?? '');
        $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
        if (mb_strlen($name) < 2 || mb_strlen($quote) < 10) {
            flash_set('error', 'Please provide a name and a quote (10+ characters).');
        } else {
            $pdo->prepare('INSERT INTO testimonials (author_name, author_role, quote, rating, is_published) VALUES (?, ?, ?, ?, 1)')
                ->execute([$name, $role ?: null, $quote, $rating]);
            flash_set('success', 'Testimonial added.');
        }
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE testimonials SET is_published = NOT is_published WHERE id = ?')->execute([(int) $_POST['id']]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM testimonials WHERE id = ?')->execute([(int) $_POST['id']]);
    }
    redirect('/admin/testimonials');
}

$rows = $pdo->query('SELECT * FROM testimonials ORDER BY sort_order, id DESC')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Testimonials</h1>
<div class="bg-white border rounded-lg p-6 max-w-lg mb-6">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="text" name="author_name" required placeholder="Name" class="w-full border rounded px-3 py-2 text-sm">
    <input type="text" name="author_role" placeholder="Role / company (optional)" class="w-full border rounded px-3 py-2 text-sm">
    <textarea name="quote" required rows="3" placeholder="Quote" class="w-full border rounded px-3 py-2 text-sm"></textarea>
    <select name="rating" class="border rounded px-2 py-1.5 text-sm">
      <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('★', $i) ?></option><?php endfor; ?>
    </select>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Add testimonial</button>
  </form>
</div>
<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($rows as $t): ?>
    <div class="p-4 flex items-start justify-between gap-4 text-sm">
      <div>
        <div class="font-medium"><?= e($t['author_name']) ?> <?php if ($t['author_role']): ?><span class="text-slate-500">— <?= e($t['author_role']) ?></span><?php endif; ?></div>
        <div class="text-amber-500 text-xs"><?= str_repeat('★', (int) $t['rating']) ?></div>
        <p class="text-slate-600 mt-1"><?= e($t['quote']) ?></p>
      </div>
      <div class="flex gap-2 shrink-0">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
          <button type="submit" name="action" value="toggle" class="text-xs text-indigo-600 hover:underline"><?= $t['is_published'] ? 'Unpublish' : 'Publish' ?></button>
        </form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
          <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="p-4 text-sm text-slate-500">No testimonials yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
