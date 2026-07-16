<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Community Polls — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $question = trim($_POST['question'] ?? '');
        $options = array_values(array_filter(array_map('trim', $_POST['options'] ?? [])));
        $closesAt = trim($_POST['closes_at'] ?? '');
        if (mb_strlen($question) < 5 || count($options) < 2) {
            flash_set('error', 'Provide a question and at least 2 options.');
        } else {
            // Only one poll is active at a time — deactivate any others before creating this one.
            $pdo->exec('UPDATE site_polls SET is_active = 0 WHERE is_active = 1');
            $pdo->prepare('INSERT INTO site_polls (question, closes_at, created_by) VALUES (?, ?, ?)')
                ->execute([$question, $closesAt ?: null, $admin['id']]);
            $pollId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO site_poll_options (poll_id, label, sort_order) VALUES (?, ?, ?)');
            foreach ($options as $i => $label) {
                $ins->execute([$pollId, mb_substr($label, 0, 150), $i]);
            }
            audit_log($admin['id'], 'site_poll_created', 'site_poll', $pollId);
            flash_set('success', 'Poll created and is now live.');
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $poll = $pdo->prepare('SELECT is_active FROM site_polls WHERE id = ?');
        $poll->execute([$id]);
        $isActive = $poll->fetchColumn();
        if ($isActive === false) { redirect('/admin/site_polls'); }
        if (!$isActive) {
            $pdo->exec('UPDATE site_polls SET is_active = 0 WHERE is_active = 1');
        }
        $pdo->prepare('UPDATE site_polls SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM site_polls WHERE id = ?')->execute([$id]);
        audit_log($admin['id'], 'site_poll_deleted', 'site_poll', $id);
    }
    redirect('/admin/site_polls');
}

$polls = $pdo->query('SELECT * FROM site_polls ORDER BY created_at DESC')->fetchAll();
foreach ($polls as &$p) {
    $opts = $pdo->prepare(
        "SELECT o.id, o.label, COUNT(v.user_id) AS votes FROM site_poll_options o
         LEFT JOIN site_poll_votes v ON v.option_id = o.id
         WHERE o.poll_id = ? GROUP BY o.id ORDER BY o.sort_order"
    );
    $opts->execute([$p['id']]);
    $p['options'] = $opts->fetchAll();
    $p['total_votes'] = array_sum(array_column($p['options'], 'votes'));
}
unset($p);
?>
<h1 class="text-2xl font-bold mb-1">Community polls</h1>
<p class="text-sm text-slate-600 mb-6">Only one poll can be active at a time; it appears as a dismissible widget for logged-in visitors site-wide.</p>

<div class="bg-white border rounded-lg p-6 max-w-lg mb-6">
  <h2 class="font-semibold mb-3 text-sm">New poll</h2>
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="text" name="question" required minlength="5" placeholder="Poll question" class="w-full border rounded px-3 py-2 text-sm">
    <?php for ($i = 0; $i < 5; $i++): ?>
      <input type="text" name="options[]" maxlength="150" placeholder="Option <?= $i + 1 ?><?= $i > 1 ? ' (optional)' : '' ?>" class="w-full border rounded px-3 py-2 text-sm">
    <?php endfor; ?>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Closes at (optional)</label>
      <input type="datetime-local" name="closes_at" class="border rounded px-2 py-1.5 text-sm">
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Create &amp; go live</button>
  </form>
</div>

<div class="space-y-3">
  <?php foreach ($polls as $p): ?>
    <div class="bg-white border rounded-lg p-4 text-sm">
      <div class="flex items-center justify-between gap-2 mb-2">
        <div class="font-medium"><?= e($p['question']) ?> <?= $p['is_active'] ? '<span class="text-xs text-green-600">live</span>' : '<span class="text-xs text-slate-400">inactive</span>' ?></div>
        <div class="flex items-center gap-2 shrink-0">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button type="submit" name="action" value="toggle" class="text-xs <?= $p['is_active'] ? 'text-amber-600' : 'text-green-600' ?> hover:underline"><?= $p['is_active'] ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button type="submit" name="action" value="delete" onclick="return confirm('Delete this poll?')" class="text-xs text-red-600 hover:underline">Delete</button>
          </form>
        </div>
      </div>
      <?php foreach ($p['options'] as $o): $pct = $p['total_votes'] ? round($o['votes'] / $p['total_votes'] * 100) : 0; ?>
        <div class="mb-1">
          <div class="flex justify-between text-xs text-slate-500"><span><?= e($o['label']) ?></span><span><?= (int) $o['votes'] ?> (<?= $pct ?>%)</span></div>
          <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-indigo-500" style="width: <?= $pct ?>%"></div></div>
        </div>
      <?php endforeach; ?>
      <p class="text-xs text-slate-400 mt-2"><?= (int) $p['total_votes'] ?> total votes<?= $p['closes_at'] ? ' · closes ' . e(date('M j, Y g:i A', strtotime($p['closes_at']))) : '' ?></p>
    </div>
  <?php endforeach; ?>
  <?php if (!$polls): ?><p class="text-sm text-slate-500">No polls yet.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
