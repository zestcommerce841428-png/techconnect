<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Expert Payouts — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'mark_paid') {
        $ref = trim($_POST['reference'] ?? '');
        $pdo->prepare("UPDATE expert_payouts SET status = 'paid', paid_at = NOW(), payout_reference = ? WHERE id = ? AND status = 'pending'")
            ->execute([$ref ?: null, $id]);
        audit_log($admin['id'], 'expert_payout_marked_paid', 'expert_payout', $id, $ref);
        flash_set('success', 'Payout marked as paid.');
    } elseif ($action === 'set_fee') {
        $pct = (float) ($_POST['fee_pct'] ?? 15);
        $pdo->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = "expert_platform_fee_pct"')
            ->execute([max(0, min(100, $pct))]);
        flash_set('success', 'Platform fee updated.');
    }
    redirect('/admin/expert_payouts');
}

$feePct = (float) setting('expert_platform_fee_pct', '15');

$rows = $pdo->query(
    "SELECT ep.*, u.username AS expert_name, c.scheduled_at FROM expert_payouts ep
     JOIN users u ON u.id = ep.expert_id JOIN consultations c ON c.id = ep.consultation_id
     ORDER BY ep.status ASC, ep.created_at DESC"
)->fetchAll();

$totalPending = array_sum(array_map(fn($r) => $r['status'] === 'pending' ? $r['net_amount_cents'] : 0, $rows));
?>
<h1 class="text-2xl font-bold mb-1">Expert payouts</h1>
<p class="text-sm text-slate-600 mb-4">Payouts are settled manually (bank transfer/UPI/etc outside the platform); this ledger just tracks what's owed and what's been paid.</p>

<div class="flex items-center gap-4 mb-6">
  <div class="bg-white border rounded-lg p-4">
    <div class="text-xs text-slate-500">Total pending payout</div>
    <div class="text-xl font-bold"><?= e(number_format($totalPending / 100, 2)) ?></div>
  </div>
  <form method="post" class="bg-white border rounded-lg p-4 flex items-center gap-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="set_fee">
    <label class="text-xs text-slate-500">Platform fee %</label>
    <input type="number" name="fee_pct" step="0.1" min="0" max="100" value="<?= e($feePct) ?>" class="w-20 border rounded px-2 py-1 text-sm">
    <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Save</button>
  </form>
</div>

<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($rows as $r): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <div>
        <div class="font-medium"><?= e($r['expert_name']) ?> — <?= e(number_format($r['net_amount_cents'] / 100, 2)) ?> <?= e($r['currency']) ?>
          <span class="text-xs text-slate-400">(gross <?= e(number_format($r['gross_amount_cents'] / 100, 2)) ?>, fee <?= e(number_format($r['platform_fee_cents'] / 100, 2)) ?>)</span>
        </div>
        <div class="text-xs text-slate-500">
          <?= $r['status'] === 'paid' ? 'Paid ' . time_ago($r['paid_at']) . ($r['payout_reference'] ? ' · ref: ' . e($r['payout_reference']) : '') : 'Pending since ' . time_ago($r['created_at']) ?>
        </div>
      </div>
      <?php if ($r['status'] === 'pending'): ?>
        <form method="post" class="flex items-center gap-2 shrink-0">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <input type="hidden" name="action" value="mark_paid">
          <input type="text" name="reference" placeholder="Reference (optional)" class="border rounded px-2 py-1 text-xs w-32">
          <button type="submit" class="text-xs bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded">Mark paid</button>
        </form>
      <?php else: ?>
        <span class="text-xs text-green-700 shrink-0">✓ paid</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="p-4 text-sm text-slate-500">No payouts yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
