<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Trash — Admin';
require __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/audit.php';

$pdo = db();

// Content trashed longer than this is offered for cleanup (never auto-purged —
// destroying data on a timer without an explicit action is how people lose work).
const TRASH_RETENTION_DAYS = 30;

// Trash cannot work until migration 031 adds the soft-delete columns. Say so
// plainly instead of throwing a 500 at whoever opens the page.
if (!soft_deletes_ready()) {
    echo '<h1 class="text-2xl font-bold mb-4">🗑️ Trash</h1>'
        . '<div class="bg-white border rounded-lg p-6 max-w-xl">'
        . '<p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-3">'
        . 'Trash is not active yet — database migration <code>031_soft_deletes.sql</code> has not been applied.</p>'
        . '<p class="text-sm text-slate-600">Run <code>migrations/031_soft_deletes.sql</code> in phpMyAdmin, then reload this page. '
        . 'Until then, deleting a post or page still removes it immediately.</p></div>';
    require __DIR__ . '/includes/admin_footer.php';
    exit;
}

/** Both content types share the same shape, so the whole page is table-driven. */
$types = [
    'blog' => [
        'table' => 'blog_posts',
        'label' => 'Blog post',
        'permission' => 'manage_blog',
        'edit_url' => '/admin/blog?id=',
        'select' => 'SELECT p.id, p.title, p.slug, p.deleted_at, u.username AS deleted_by_name
                     FROM blog_posts p LEFT JOIN users u ON u.id = p.deleted_by
                     WHERE p.deleted_at IS NOT NULL ORDER BY p.deleted_at DESC',
    ],
    'pages' => [
        'table' => 'pages',
        'label' => 'CMS page',
        'permission' => 'manage_pages',
        'edit_url' => '/admin/pages?id=',
        'select' => 'SELECT p.id, p.title, p.slug, p.deleted_at, u.username AS deleted_by_name
                     FROM pages p LEFT JOIN users u ON u.id = p.deleted_by
                     WHERE p.deleted_at IS NOT NULL ORDER BY p.deleted_at DESC',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if (!isset($types[$type])) {
        flash_set('error', 'Unknown content type.');
        redirect('/admin/trash');
    }
    // Restoring/destroying content requires the same permission as editing it.
    require_permission($admin, $types[$type]['permission']);
    $table = $types[$type]['table'];

    if ($action === 'restore' && $id) {
        $pdo->prepare("UPDATE {$table} SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND deleted_at IS NOT NULL")
            ->execute([$id]);
        audit_log($admin['id'], $type . '_restored', $type, $id);
        flash_set('success', $types[$type]['label'] . ' restored.');
    } elseif ($action === 'purge' && $id) {
        // Permanent: only ever reachable from Trash, only for already-trashed rows.
        $pdo->prepare("DELETE FROM {$table} WHERE id = ? AND deleted_at IS NOT NULL")->execute([$id]);
        audit_log($admin['id'], $type . '_purged', $type, $id, 'permanently deleted from trash');
        flash_set('success', $types[$type]['label'] . ' permanently deleted.');
    } elseif ($action === 'empty') {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE deleted_at IS NOT NULL");
        $stmt->execute();
        $n = $stmt->rowCount();
        audit_log($admin['id'], $type . '_trash_emptied', $type, null, $n . ' items permanently deleted');
        flash_set('success', $n . ' item' . ($n === 1 ? '' : 's') . ' permanently deleted.');
    }
    redirect('/admin/trash');
}

// Only surface types this admin may act on.
$visible = [];
foreach ($types as $key => $cfg) {
    if (user_can($admin, $cfg["permission"])) {
        try {
            $visible[$key] = ['cfg' => $cfg, 'rows' => $pdo->query($cfg['select'])->fetchAll()];
        } catch (Throwable $e) {
            $visible[$key] = ['cfg' => $cfg, 'rows' => [], 'error' => true];
        }
    }
}
$totalTrashed = array_sum(array_map(fn($v) => count($v['rows']), $visible));
?>
<div class="flex flex-wrap items-center justify-between gap-2 mb-1">
  <h1 class="text-2xl font-bold">🗑️ Trash</h1>
  <a href="<?= ($_GET['from'] ?? '') === 'pages' ? '/admin/pages' : '/admin/blog' ?>" class="text-sm text-indigo-600">&larr; Back to content</a>
</div>
<p class="text-sm text-slate-500 mb-5">
  Deleted content is kept here so it can be recovered. Nothing is removed automatically —
  items older than <?= TRASH_RETENTION_DAYS ?> days are flagged for cleanup, but only you can permanently delete them.
</p>

<?php if ($totalTrashed === 0): ?>
  <div class="bg-white border rounded-lg p-10 text-center">
    <div class="text-4xl mb-2">✨</div>
    <p class="font-medium">Trash is empty</p>
    <p class="text-sm text-slate-500 mt-1">Deleted posts and pages will appear here, recoverable in one click.</p>
  </div>
<?php endif; ?>

<?php foreach ($visible as $key => $data): $rows = $data['rows']; $cfg = $data['cfg']; ?>
  <?php if (!$rows) continue; ?>
  <div class="bg-white border rounded-lg mb-6">
    <div class="px-4 py-3 border-b flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-sm font-semibold"><?= e($cfg['label']) ?>s <span class="text-slate-400 font-normal">(<?= count($rows) ?>)</span></h2>
      <form method="post" onsubmit="return confirm('Permanently delete all <?= count($rows) ?> trashed <?= e(strtolower($cfg['label'])) ?>s? This cannot be undone.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="empty">
        <input type="hidden" name="type" value="<?= e($key) ?>">
        <button class="text-xs text-red-600">Empty this section</button>
      </form>
    </div>
    <div class="divide-y">
      <?php foreach ($rows as $r): $age = (int) floor((time() - strtotime($r['deleted_at'])) / 86400); ?>
        <div class="p-4 flex flex-wrap items-center justify-between gap-3 text-sm">
          <div class="min-w-0">
            <div class="font-medium truncate"><?= e($r['title']) ?></div>
            <div class="text-xs text-slate-500">
              /<?= e($r['slug']) ?> · deleted <?= time_ago($r['deleted_at']) ?>
              <?= $r['deleted_by_name'] ? ' by ' . e($r['deleted_by_name']) : '' ?>
              <?php if ($age >= TRASH_RETENTION_DAYS): ?>
                <span class="ml-1 text-amber-700 bg-amber-100 rounded px-1.5 py-0.5">older than <?= TRASH_RETENTION_DAYS ?> days</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="type" value="<?= e($key) ?>">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="text-xs bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">↩ Restore</button>
            </form>
            <form method="post" onsubmit="return confirm('Permanently delete &quot;<?= e(addslashes($r['title'])) ?>&quot;? This cannot be undone.')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="purge">
              <input type="hidden" name="type" value="<?= e($key) ?>">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="text-xs border border-red-200 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded">Delete forever</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
