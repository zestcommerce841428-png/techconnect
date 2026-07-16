<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

$profile = $pdo->prepare('SELECT * FROM expert_profiles WHERE user_id = ? AND is_approved = 1');
$profile->execute([$user['id']]);
if (!$profile->fetch()) {
    flash_set('error', 'Only approved experts can view this page.');
    redirect('/experts');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $own = $pdo->prepare('SELECT * FROM consultations WHERE id = ? AND expert_id = ?');
    $own->execute([$id, $user['id']]);
    $c = $own->fetch();

    if ($c) {
        if ($action === 'quote' && $c['status'] === 'requested') {
            $amount = (float) ($_POST['amount'] ?? 0);
            $scheduledAt = trim($_POST['scheduled_at'] ?? '');
            if ($amount >= 1) {
                $pdo->prepare('UPDATE consultations SET quoted_amount_cents = ?, scheduled_at = ? WHERE id = ?')
                    ->execute([(int) round($amount * 100), $scheduledAt ?: null, $id]);
                flash_set('success', 'Quote sent. The requester can now pay to confirm.');
            } else {
                flash_set('error', 'Enter a valid price.');
            }
        } elseif ($action === 'complete' && $c['status'] === 'confirmed') {
            $pdo->prepare("UPDATE consultations SET status = 'completed' WHERE id = ?")->execute([$id]);
            flash_set('success', 'Marked as completed.');
        } elseif ($action === 'decline' && $c['status'] === 'requested') {
            $pdo->prepare("UPDATE consultations SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            flash_set('success', 'Request declined.');
        }
    }
    redirect('/expert_consultations');
}

$rows = $pdo->prepare(
    "SELECT c.*, u.username AS requester_name FROM consultations c JOIN users u ON u.id = c.requester_id
     WHERE c.expert_id = ? ORDER BY FIELD(c.status,'requested','confirmed','completed','cancelled'), c.created_at DESC"
);
$rows->execute([$user['id']]);
$consultations = $rows->fetchAll();

$payoutStmt = $pdo->prepare('SELECT * FROM expert_payouts WHERE expert_id = ? ORDER BY created_at DESC');
$payoutStmt->execute([$user['id']]);
$payouts = $payoutStmt->fetchAll();
$totalPending = array_sum(array_map(fn($p) => $p['status'] === 'pending' ? $p['net_amount_cents'] : 0, $payouts));
$totalPaid = array_sum(array_map(fn($p) => $p['status'] === 'paid' ? $p['net_amount_cents'] : 0, $payouts));

$statusColor = ['requested' => 'bg-amber-100 text-amber-700', 'confirmed' => 'bg-blue-100 text-blue-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-slate-100 text-slate-600'];

$pageTitle = 'My consultations — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <h1 class="text-2xl font-bold mb-1">My consultations</h1>
  <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Quote requests, and track what you're owed.</p>

  <div class="grid grid-cols-2 gap-3 mb-6">
    <div class="card p-4 text-center">
      <div class="text-xs text-slate-500">Pending payout</div>
      <div class="text-xl font-bold"><?= e(number_format($totalPending / 100, 2)) ?></div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-xs text-slate-500">Paid out to date</div>
      <div class="text-xl font-bold"><?= e(number_format($totalPaid / 100, 2)) ?></div>
    </div>
  </div>

  <div class="space-y-3">
    <?php foreach ($consultations as $c): ?>
      <div class="card p-4 text-sm">
        <div class="flex items-center justify-between gap-2 mb-1">
          <div class="font-medium">Request from <?= e($c['requester_name']) ?></div>
          <span class="text-xs px-2 py-0.5 rounded-full <?= $statusColor[$c['status']] ?>"><?= ucfirst($c['status']) ?></span>
        </div>
        <?php if ($c['message']): ?><p class="text-slate-600 dark:text-slate-400 mb-2"><?= e($c['message']) ?></p><?php endif; ?>
        <?php if ($c['quoted_amount_cents']): ?><p class="text-xs text-slate-500 mb-2">Quoted: <?= e(number_format($c['quoted_amount_cents'] / 100, 2)) ?></p><?php endif; ?>

        <?php if ($c['status'] === 'requested' && !$c['quoted_amount_cents']): ?>
          <form method="post" class="flex flex-wrap items-center gap-2 mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="action" value="quote">
            <label for="quote_amount_<?= (int) $c['id'] ?>" class="sr-only">Quoted price</label>
            <input id="quote_amount_<?= (int) $c['id'] ?>" type="number" name="amount" step="0.01" min="1" required placeholder="Price" class="w-24 border rounded px-2 py-1.5 text-sm">
            <label for="quote_time_<?= (int) $c['id'] ?>" class="sr-only">Proposed date and time</label>
            <input id="quote_time_<?= (int) $c['id'] ?>" type="datetime-local" name="scheduled_at" class="border rounded px-2 py-1.5 text-sm">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded text-xs">Send quote</button>
          </form>
          <form method="post" class="inline mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button type="submit" name="action" value="decline" class="text-xs text-red-600 hover:underline">Decline</button>
          </form>
        <?php elseif ($c['status'] === 'confirmed'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button type="submit" name="action" value="complete" class="text-xs bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded">Mark session completed</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$consultations): ?><p class="text-sm text-slate-500">No consultation requests yet.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
