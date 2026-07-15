<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/reputation.php';
$pageTitle = 'Suggested Edits — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM suggested_edits WHERE id = ? AND status = 'pending'");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();

    if ($edit) {
        if ($action === 'approve') {
            if ($edit['target_type'] === 'question') {
                $cur = $pdo->prepare('SELECT title, body FROM questions WHERE id = ?');
                $cur->execute([$edit['target_id']]);
                $current = $cur->fetch();
                if ($current) {
                    $pdo->prepare('INSERT INTO question_revisions (question_id, editor_id, prior_title, prior_body) VALUES (?, ?, ?, ?)')
                        ->execute([$edit['target_id'], $edit['proposer_id'], $current['title'], $current['body']]);
                    $pdo->prepare('UPDATE questions SET title = ?, body = ?, last_edited_at = NOW() WHERE id = ?')
                        ->execute([$edit['proposed_title'] ?: $current['title'], $edit['proposed_body'], $edit['target_id']]);
                }
            } else {
                $cur = $pdo->prepare('SELECT body FROM answers WHERE id = ?');
                $cur->execute([$edit['target_id']]);
                $current = $cur->fetch();
                if ($current) {
                    $pdo->prepare('INSERT INTO answer_revisions (answer_id, editor_id, prior_body) VALUES (?, ?, ?)')
                        ->execute([$edit['target_id'], $edit['proposer_id'], $current['body']]);
                    $pdo->prepare('UPDATE answers SET body = ?, last_edited_at = NOW() WHERE id = ?')
                        ->execute([$edit['proposed_body'], $edit['target_id']]);
                }
            }
            award_rep((int) $edit['proposer_id'], 2, 'edit_approved', $edit['target_type'], (int) $edit['target_id']);
            $pdo->prepare("UPDATE suggested_edits SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$admin['id'], $id]);
            audit_log($admin['id'], 'suggested_edit_approved', $edit['target_type'], (int) $edit['target_id']);
            flash_set('success', 'Edit approved and applied.');
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE suggested_edits SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$admin['id'], $id]);
            flash_set('success', 'Edit rejected.');
        }
    }
    redirect('/admin/suggested_edits');
}

$pending = $pdo->query(
    "SELECT se.*, u.username AS proposer_name,
            COALESCE(q1.title, q2.title) AS context_title,
            COALESCE(q1.slug, q2.slug) AS context_slug
     FROM suggested_edits se
     JOIN users u ON u.id = se.proposer_id
     LEFT JOIN questions q1 ON se.target_type = 'question' AND q1.id = se.target_id
     LEFT JOIN answers a ON se.target_type = 'answer' AND a.id = se.target_id
     LEFT JOIN questions q2 ON q2.id = a.question_id
     WHERE se.status = 'pending' ORDER BY se.created_at ASC"
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Suggested edits (<?= count($pending) ?> pending)</h1>
<div class="space-y-4 max-w-2xl">
  <?php foreach ($pending as $e): ?>
    <div class="bg-white border rounded-lg p-4 text-sm">
      <div class="flex items-center justify-between mb-2">
        <div>
          <span class="text-xs px-1.5 py-0.5 rounded bg-slate-100 uppercase"><?= e($e['target_type']) ?></span>
          <a href="/q/<?= e($e['context_slug']) ?>" class="text-indigo-600 hover:underline ml-1"><?= e($e['context_title']) ?></a>
        </div>
        <span class="text-xs text-slate-500">by <?= e($e['proposer_name']) ?> · <?= time_ago($e['created_at']) ?></span>
      </div>
      <?php if ($e['edit_summary']): ?><p class="text-xs text-slate-500 italic mb-2">"<?= e($e['edit_summary']) ?>"</p><?php endif; ?>
      <?php if ($e['proposed_title']): ?><p class="font-medium mb-1">New title: <?= e($e['proposed_title']) ?></p><?php endif; ?>
      <div class="bg-slate-50 border rounded p-2 text-xs whitespace-pre-wrap max-h-40 overflow-y-auto"><?= e($e['proposed_body']) ?></div>
      <form method="post" class="flex gap-2 mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
        <button type="submit" name="action" value="approve" class="text-xs bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded">Approve & apply</button>
        <button type="submit" name="action" value="reject" class="text-xs bg-slate-200 hover:bg-slate-300 px-3 py-1.5 rounded">Reject</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$pending): ?><p class="text-sm text-slate-500">No pending suggestions.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
