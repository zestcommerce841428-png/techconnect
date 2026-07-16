<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Feedback — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();
$statuses = ['new', 'in_review', 'planned', 'resolved', 'dismissed'];
$types = ['bug' => 'Bug', 'feature' => 'Feature', 'ui' => 'UI/UX', 'performance' => 'Performance', 'other' => 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'update' && $id) {
        $status = in_array($_POST['status'] ?? '', $statuses, true) ? $_POST['status'] : 'new';
        $note = trim($_POST['admin_note'] ?? '');
        $prev = $pdo->prepare('SELECT user_id, status, subject FROM feedback WHERE id = ?');
        $prev->execute([$id]);
        $prevRow = $prev->fetch();
        $pdo->prepare('UPDATE feedback SET status = ?, admin_note = ?, handled_by = ? WHERE id = ?')
            ->execute([$status, $note !== '' ? $note : null, $admin['id'], $id]);
        // Close the loop: tell the reporter when their feedback reaches an outcome state.
        if ($prevRow && $prevRow['user_id'] && $prevRow['status'] !== $status
            && in_array($status, ['planned', 'resolved', 'dismissed'], true)) {
            $pdo->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, "feedback_update", JSON_OBJECT("subject", ?, "status", ?))')
                ->execute([$prevRow['user_id'], mb_substr($prevRow['subject'], 0, 120), $status]);
        }
        audit_log($admin['id'], 'feedback_updated', 'feedback', $id, $status);
        flash_set('success', 'Feedback #' . $id . ' updated.');
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM feedback WHERE id = ?')->execute([$id]);
        audit_log($admin['id'], 'feedback_deleted', 'feedback', $id);
        flash_set('success', 'Feedback deleted.');
    }
    // Rebuild the return filter from whitelisted values rather than echoing raw input into the header.
    parse_str($_POST['return_query'] ?? '', $rq);
    $safeReturn = http_build_query(array_filter([
        'status' => in_array($rq['status'] ?? '', $statuses, true) ? $rq['status'] : null,
        'type' => isset($types[$rq['type'] ?? '']) ? $rq['type'] : null,
        'page' => max(0, (int) ($rq['page'] ?? 0)) ?: null,
    ]));
    redirect('/admin/feedback' . ($safeReturn ? '?' . $safeReturn : ''));
}

$filterStatus = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$filterType = isset($types[$_GET['type'] ?? '']) ? $_GET['type'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];
if ($filterStatus !== '') { $where[] = 'f.status = ?'; $params[] = $filterStatus; }
if ($filterType !== '') { $where[] = 'f.type = ?'; $params[] = $filterType; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM feedback f $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT f.*, u.username FROM feedback f LEFT JOIN users u ON u.id = f.user_id
    $whereSql ORDER BY FIELD(f.status, 'new') DESC, f.created_at DESC LIMIT $perPage OFFSET " . paginate_offset($page, $perPage));
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) c FROM feedback GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$returnQuery = http_build_query(array_filter(['status' => $filterStatus, 'type' => $filterType, 'page' => $page > 1 ? $page : null]));

$severityStyles = ['critical' => 'bg-red-100 text-red-800', 'high' => 'bg-orange-100 text-orange-800', 'medium' => 'bg-amber-100 text-amber-800', 'low' => 'bg-slate-100 text-slate-600'];
?>
<h1 class="text-2xl font-bold mb-4">Feedback &amp; bug reports <span class="text-base font-normal text-slate-500">(<?= $total ?>)</span></h1>

<div class="flex flex-wrap gap-2 mb-4 text-sm">
  <a href="/admin/feedback" class="px-3 py-1.5 rounded border <?= $filterStatus === '' && $filterType === '' ? 'bg-slate-900 text-white' : 'bg-white hover:bg-slate-100' ?>">All</a>
  <?php foreach ($statuses as $s): ?>
    <a href="/admin/feedback?status=<?= $s ?>" class="px-3 py-1.5 rounded border <?= $filterStatus === $s ? 'bg-slate-900 text-white' : 'bg-white hover:bg-slate-100' ?>">
      <?= ucwords(str_replace('_', ' ', $s)) ?> (<?= (int) ($counts[$s] ?? 0) ?>)
    </a>
  <?php endforeach; ?>
  <span class="mx-1 border-l"></span>
  <?php foreach ($types as $val => $label): ?>
    <a href="/admin/feedback?type=<?= $val ?><?= $filterStatus ? '&status=' . $filterStatus : '' ?>" class="px-3 py-1.5 rounded border <?= $filterType === $val ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-slate-100' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="space-y-3">
  <?php foreach ($rows as $r): ?>
    <div class="bg-white border rounded-lg p-4 text-sm">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
          <div class="font-medium">#<?= (int) $r['id'] ?> · <?= e($r['subject']) ?></div>
          <div class="text-xs text-slate-500 mt-0.5">
            <?= e($types[$r['type']] ?? $r['type']) ?>
            <?php if ($r['severity']): ?>
              · <span class="px-1.5 py-0.5 rounded <?= $severityStyles[$r['severity']] ?? '' ?>"><?= e($r['severity']) ?></span>
            <?php endif; ?>
            · by <?php if ($r['username']): ?><a href="/profile?u=<?= e($r['username']) ?>" class="text-indigo-600 hover:underline"><?= e($r['username']) ?></a><?php else: ?><?= e($r['email'] ?? 'anonymous') ?><?php endif; ?>
            · <?= time_ago($r['created_at']) ?>
            <?php if ($r['page_url']): ?> · <a href="<?= e($r['page_url']) ?>" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">page</a><?php endif; ?>
          </div>
        </div>
        <form method="post" onsubmit="return confirm('Delete this feedback?')">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <input type="hidden" name="return_query" value="<?= e($returnQuery) ?>">
          <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
        </form>
      </div>
      <p class="mt-2 text-slate-700 whitespace-pre-line"><?= e($r['message']) ?></p>
      <?php if ($r['user_agent']): ?><p class="mt-1 text-xs text-slate-400 truncate" title="<?= e($r['user_agent']) ?>">UA: <?= e($r['user_agent']) ?></p><?php endif; ?>
      <form method="post" class="mt-3 flex flex-wrap items-start gap-2 border-t pt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <input type="hidden" name="return_query" value="<?= e($returnQuery) ?>">
        <select name="status" class="border rounded px-2 py-1.5 text-xs">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="admin_note" value="<?= e($r['admin_note'] ?? '') ?>" placeholder="Internal / team response note (shown to the reporter once resolved, planned or dismissed)"
               class="flex-1 min-w-[200px] border rounded px-2 py-1.5 text-xs">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded text-xs">Save</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="bg-white border rounded-lg p-6 text-sm text-slate-500">No feedback matches this filter.</div><?php endif; ?>
</div>

<?php $totalPages = (int) ceil($total / $perPage); if ($totalPages > 1): ?>
  <div class="flex gap-2 mt-4 text-sm">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="/admin/feedback?<?= e(http_build_query(array_filter(['status' => $filterStatus, 'type' => $filterType, 'page' => $p]))) ?>"
         class="px-3 py-1.5 rounded border <?= $p === $page ? 'bg-slate-900 text-white' : 'bg-white hover:bg-slate-100' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
