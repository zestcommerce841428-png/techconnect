<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Uploads — Admin';
require __DIR__ . '/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $uploadId = (int) $_POST['upload_id'];
    $stmt = $pdo->prepare('SELECT path FROM uploads WHERE id = ?');
    $stmt->execute([$uploadId]);
    $path = $stmt->fetchColumn();
    if ($path) {
        $fullPath = __DIR__ . '/..' . $path;
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
        $pdo->prepare('DELETE FROM uploads WHERE id = ?')->execute([$uploadId]);
        flash_set('success', 'File deleted.');
    }
    redirect('/admin/uploads.php');
}

$uploads = $pdo->query(
    'SELECT u.*, us.username FROM uploads u JOIN users us ON us.id = u.user_id ORDER BY u.created_at DESC LIMIT 100'
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Uploaded files</h1>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
  <?php foreach ($uploads as $u): ?>
    <div class="bg-white border rounded-lg p-3">
      <?php if (str_starts_with($u['mime'], 'image/')): ?>
        <img src="<?= e($u['path']) ?>" alt="" class="w-full h-24 object-cover rounded mb-2">
      <?php else: ?>
        <div class="w-full h-24 bg-slate-100 rounded mb-2 flex items-center justify-center text-slate-400 text-xs">PDF</div>
      <?php endif; ?>
      <div class="text-xs truncate" title="<?= e($u['original_name']) ?>"><?= e($u['original_name']) ?></div>
      <div class="text-xs text-slate-500">by <?= e($u['username']) ?> &middot; <?= round($u['size'] / 1024) ?> KB</div>
      <form method="post" onsubmit="return confirm('Delete this file?');" class="mt-1">
        <?= csrf_field() ?>
        <input type="hidden" name="upload_id" value="<?= $u['id'] ?>">
        <button class="text-xs text-red-600 hover:underline">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$uploads): ?><p class="text-slate-500">No uploads yet.</p><?php endif; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
