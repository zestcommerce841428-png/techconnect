<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Moderation — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['report_id'])) {
        $reportId = (int) $_POST['report_id'];
        $action = $_POST['action'];

        if ($action === 'delete_target') {
            $r = $pdo->prepare('SELECT target_type, target_id FROM reports WHERE id = ?');
            $r->execute([$reportId]);
            $report = $r->fetch();
            if ($report) {
                $table = match ($report['target_type']) {
                    'question' => 'questions', 'answer' => 'answers', 'comment' => 'comments', default => null,
                };
                if ($table) {
                    $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$report['target_id']]);
                    audit_log($admin['id'], 'delete_reported_content', $report['target_type'], (int) $report['target_id']);
                }
            }
            $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?")->execute([$reportId]);
        } elseif ($action === 'dismiss') {
            $pdo->prepare("UPDATE reports SET status = 'dismissed' WHERE id = ?")->execute([$reportId]);
        }
        flash_set('success', 'Report updated.');
    } elseif (isset($_POST['flag_id'])) {
        $flagId = (int) $_POST['flag_id'];
        $action = $_POST['action'];
        if ($action === 'clear_flag') {
            $pdo->prepare("UPDATE spam_flags SET status = 'cleared' WHERE id = ?")->execute([$flagId]);
        } elseif ($action === 'remove_flagged') {
            $f = $pdo->prepare('SELECT target_type, target_id FROM spam_flags WHERE id = ?');
            $f->execute([$flagId]);
            $flag = $f->fetch();
            if ($flag) {
                $table = match ($flag['target_type']) {
                    'question' => 'questions', 'answer' => 'answers', 'comment' => 'comments', default => null,
                };
                if ($table) {
                    $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$flag['target_id']]);
                    audit_log($admin['id'], 'remove_spam_flagged_content', $flag['target_type'], (int) $flag['target_id']);
                }
            }
            $pdo->prepare("UPDATE spam_flags SET status = 'removed' WHERE id = ?")->execute([$flagId]);
        }
        flash_set('success', 'Flag updated.');
    }
    redirect('/admin/moderation');
}

$reports = $pdo->query(
    "SELECT r.*, u.username AS reporter
     FROM reports r JOIN users u ON u.id = r.reporter_id
     WHERE r.status = 'open' ORDER BY r.created_at ASC LIMIT 50"
)->fetchAll();

$flags = $pdo->query("SELECT * FROM spam_flags WHERE status = 'pending' ORDER BY score DESC, created_at ASC LIMIT 50")->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Open reports</h1>
<?php if (!$reports): ?>
  <p class="text-slate-500 mb-6">No open reports. 🎉</p>
<?php endif; ?>
<div class="space-y-3 mb-8">
  <?php foreach ($reports as $r): ?>
    <div class="bg-white border rounded-lg p-4">
      <div class="text-sm text-slate-600 mb-1"><?= e($r['target_type']) ?> #<?= (int) $r['target_id'] ?> reported by <?= e($r['reporter']) ?></div>
      <div class="text-sm mb-3">Reason: <?= e($r['reason']) ?></div>
      <div class="flex gap-2">
        <form method="post" onsubmit="return confirm('Delete the reported content?');">
          <?= csrf_field() ?>
          <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
          <input type="hidden" name="action" value="delete_target">
          <button class="text-xs bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded">Delete content</button>
        </form>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
          <input type="hidden" name="action" value="dismiss">
          <button class="text-xs bg-slate-200 hover:bg-slate-300 px-3 py-1.5 rounded">Dismiss</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<h1 class="text-2xl font-bold mb-4">Auto-flagged spam</h1>
<?php if (!$flags): ?>
  <p class="text-slate-500">Nothing flagged right now.</p>
<?php endif; ?>
<div class="space-y-3">
  <?php foreach ($flags as $f): ?>
    <div class="bg-white border rounded-lg p-4">
      <div class="text-sm text-slate-600 mb-1"><?= e($f['target_type']) ?> #<?= (int) $f['target_id'] ?> &middot; score <?= (int) $f['score'] ?></div>
      <div class="text-sm mb-3">Reason: <?= e($f['reason']) ?></div>
      <div class="flex gap-2">
        <form method="post" onsubmit="return confirm('Delete this content?');">
          <?= csrf_field() ?>
          <input type="hidden" name="flag_id" value="<?= $f['id'] ?>">
          <input type="hidden" name="action" value="remove_flagged">
          <button class="text-xs bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded">Delete content</button>
        </form>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="flag_id" value="<?= $f['id'] ?>">
          <input type="hidden" name="action" value="clear_flag">
          <button class="text-xs bg-slate-200 hover:bg-slate-300 px-3 py-1.5 rounded">Not spam</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
