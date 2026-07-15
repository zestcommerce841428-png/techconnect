<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'IP Blocks — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'block') {
        $ip = trim($_POST['ip_address'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            flash_set('error', 'Invalid IP address.');
        } elseif ($ip === ($_SERVER['REMOTE_ADDR'] ?? '')) {
            flash_set('error', "You can't block your own current IP.");
        } else {
            $pdo->prepare('INSERT IGNORE INTO ip_blocks (ip_address, reason, blocked_by) VALUES (?, ?, ?)')
                ->execute([$ip, $reason ?: null, $admin['id']]);
            audit_log($admin['id'], 'ip_blocked', null, null, $ip);
            flash_set('success', 'IP blocked.');
        }
    } elseif ($action === 'unblock') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM ip_blocks WHERE id = ?')->execute([$id]);
        audit_log($admin['id'], 'ip_unblocked', null, $id);
        flash_set('success', 'IP unblocked.');
    }
    redirect('/admin/ip_blocks');
}

$blocks = $pdo->query(
    'SELECT b.*, u.username AS blocked_by_name FROM ip_blocks b LEFT JOIN users u ON u.id = b.blocked_by ORDER BY b.created_at DESC LIMIT 200'
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">IP blocks</h1>
<div class="bg-white border rounded-lg p-6 max-w-lg mb-6">
  <form method="post" class="flex flex-wrap gap-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="block">
    <input type="text" name="ip_address" required placeholder="IP address (v4 or v6)" class="border rounded px-3 py-2 text-sm flex-1 min-w-40">
    <input type="text" name="reason" placeholder="Reason (optional)" class="border rounded px-3 py-2 text-sm flex-1 min-w-40">
    <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded text-sm">Block</button>
  </form>
  <p class="text-xs text-slate-500 mt-2">Blocked IPs receive a 403 on every page immediately.</p>
</div>
<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($blocks as $b): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <div>
        <span class="font-mono font-medium"><?= e($b['ip_address']) ?></span>
        <?php if ($b['reason']): ?><span class="text-slate-500"> — <?= e($b['reason']) ?></span><?php endif; ?>
        <div class="text-xs text-slate-500">by <?= e($b['blocked_by_name'] ?? 'unknown') ?> · <?= e(date('M j, Y', strtotime($b['created_at']))) ?></div>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="unblock">
        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <button type="submit" class="text-xs text-indigo-600 hover:underline">Unblock</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$blocks): ?><div class="p-4 text-sm text-slate-500">No blocked IPs.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
