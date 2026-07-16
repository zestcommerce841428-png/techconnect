<?php
require_once __DIR__ . '/includes/auth.php';
$user = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'submit') {
        rate_limit('roadmap_submit', 5, 3600);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (mb_strlen($title) < 5 || mb_strlen($description) < 10) {
            flash_set('error', 'Please provide a title (5+ chars) and description (10+ chars).');
        } else {
            $pdo->prepare('INSERT INTO roadmap_items (title, description, submitted_by) VALUES (?, ?, ?)')
                ->execute([$title, $description, $user['id']]);
            $itemId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO roadmap_votes (item_id, user_id) VALUES (?, ?)')->execute([$itemId, $user['id']]);
            $pdo->prepare('UPDATE roadmap_items SET vote_count = 1 WHERE id = ?')->execute([$itemId]);
            flash_set('success', 'Idea submitted — thanks! It now shows under review.');
        }
    } elseif ($action === 'vote') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $check = $pdo->prepare('SELECT id FROM roadmap_votes WHERE item_id = ? AND user_id = ?');
        $check->execute([$itemId, $user['id']]);
        if ($check->fetchColumn()) {
            $pdo->prepare('DELETE FROM roadmap_votes WHERE item_id = ? AND user_id = ?')->execute([$itemId, $user['id']]);
            $pdo->prepare('UPDATE roadmap_items SET vote_count = GREATEST(0, vote_count - 1) WHERE id = ?')->execute([$itemId]);
        } else {
            $pdo->prepare('INSERT INTO roadmap_votes (item_id, user_id) VALUES (?, ?)')->execute([$itemId, $user['id']]);
            $pdo->prepare('UPDATE roadmap_items SET vote_count = vote_count + 1 WHERE id = ?')->execute([$itemId]);
        }
    }
    redirect('/roadmap');
}

$statusMeta = [
    'under_review' => ['label' => 'Under review', 'color' => 'bg-slate-100 text-slate-700'],
    'planned'      => ['label' => 'Planned', 'color' => 'bg-blue-100 text-blue-700'],
    'in_progress'  => ['label' => 'In progress', 'color' => 'bg-amber-100 text-amber-700'],
    'shipped'      => ['label' => 'Shipped', 'color' => 'bg-green-100 text-green-700'],
    'declined'     => ['label' => 'Declined', 'color' => 'bg-red-100 text-red-700'],
];

$items = $pdo->query('SELECT * FROM roadmap_items WHERE status != "declined" ORDER BY FIELD(status, "in_progress","planned","under_review","shipped"), vote_count DESC')->fetchAll();

$myVotes = [];
if ($user) {
    $stmt = $pdo->prepare('SELECT item_id FROM roadmap_votes WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $myVotes = array_column($stmt->fetchAll(), 'item_id');
}

$pageTitle = 'Roadmap — ' . SITE_NAME;
$pageDescription = 'Vote on what we build next, or submit your own idea.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <h1 class="text-2xl font-bold mb-1">Roadmap</h1>
  <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Vote for the ideas you want most, or suggest your own.</p>

  <?php if ($user): ?>
    <details class="card p-4 mb-6">
      <summary class="cursor-pointer font-medium text-sm">+ Suggest an idea</summary>
      <form method="post" class="space-y-3 mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit">
        <input type="text" name="title" required minlength="5" maxlength="150" placeholder="Idea title" class="w-full border rounded px-3 py-2 text-sm">
        <textarea name="description" required minlength="10" rows="3" placeholder="What should we build, and why?" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Submit idea</button>
      </form>
    </details>
  <?php else: ?>
    <p class="text-sm text-slate-500 mb-6"><a href="/login" class="text-indigo-600 hover:underline">Log in</a> to vote or submit ideas.</p>
  <?php endif; ?>

  <div class="space-y-3">
    <?php foreach ($items as $item): $meta = $statusMeta[$item['status']]; $voted = in_array((int) $item['id'], $myVotes, true); ?>
      <div class="card p-4 flex items-start gap-4">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="vote">
          <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
          <button type="submit" <?= $user ? '' : 'disabled' ?> aria-pressed="<?= $voted ? 'true' : 'false' ?>" aria-label="<?= $voted ? 'Remove upvote from' : 'Upvote' ?> <?= e($item['title']) ?>, currently <?= (int) $item['vote_count'] ?> votes" class="flex flex-col items-center gap-0.5 w-12 py-1.5 rounded border text-xs font-semibold <?= $voted ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-indigo-400' ?>">
            <span aria-hidden="true">&#9650;</span><span><?= (int) $item['vote_count'] ?></span>
          </button>
        </form>
        <div class="min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $meta['color'] ?>"><?= e($meta['label']) ?></span>
          </div>
          <h2 class="font-semibold"><?= e($item['title']) ?></h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 mt-1"><?= e($item['description']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="text-sm text-slate-500">No roadmap items yet — be the first to suggest one.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
