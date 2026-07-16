<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Webhooks — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$eventOptions = ['question.created' => 'New question', 'answer.created' => 'New answer', 'user.registered' => 'New user', 'order.paid' => 'Order paid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $label = trim($_POST['label'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $events = array_intersect($_POST['events'] ?? [], array_keys($eventOptions));
        if (mb_strlen($label) < 2 || !filter_var($url, FILTER_VALIDATE_URL) || !$events) {
            flash_set('error', 'Please provide a label, a valid URL, and at least one event.');
        } else {
            $secret = bin2hex(random_bytes(24));
            $pdo->prepare('INSERT INTO webhooks (label, url, secret, events) VALUES (?, ?, ?, ?)')
                ->execute([$label, $url, $secret, implode(',', $events)]);
            audit_log($admin['id'], 'webhook_created', 'webhook', (int) $pdo->lastInsertId(), $url);
            flash_set('success', 'Webhook created.');
        }
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE webhooks SET enabled = NOT enabled WHERE id = ?')->execute([(int) $_POST['id']]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM webhooks WHERE id = ?')->execute([(int) $_POST['id']]);
        audit_log($admin['id'], 'webhook_deleted', 'webhook', (int) $_POST['id']);
    }
    redirect('/admin/webhooks');
}

$webhooks = $pdo->query('SELECT * FROM webhooks ORDER BY created_at DESC')->fetchAll();
$recentDeliveries = $pdo->query(
    'SELECT d.*, w.label FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id ORDER BY d.created_at DESC LIMIT 30'
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-2">Webhooks</h1>
<p class="text-sm text-slate-600 mb-6">Notify external services (Zapier, Make, your own server) when things happen here. Payloads are JSON, signed with HMAC-SHA256 in the <code class="bg-slate-100 px-1 rounded text-xs">X-Webhook-Signature</code> header.</p>

<div class="bg-white border rounded-lg p-6 max-w-xl mb-6">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="text" name="label" required placeholder="Label (e.g. Slack notifier)" class="w-full border rounded px-3 py-2 text-sm">
    <input type="url" name="url" required placeholder="https://example.com/webhook" class="w-full border rounded px-3 py-2 text-sm">
    <div class="flex flex-wrap gap-3">
      <?php foreach ($eventOptions as $key => $label): ?>
        <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="events[]" value="<?= e($key) ?>"> <?= e($label) ?></label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Add webhook</button>
  </form>
</div>

<div class="bg-white border rounded-lg divide-y mb-8">
  <?php foreach ($webhooks as $w): ?>
    <div class="p-4 text-sm">
      <div class="flex items-center justify-between gap-4">
        <div>
          <span class="font-medium"><?= e($w['label']) ?></span>
          <span class="text-xs px-1.5 py-0.5 rounded ml-1 <?= $w['enabled'] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?>"><?= $w['enabled'] ? 'enabled' : 'disabled' ?></span>
          <div class="text-xs text-slate-500 font-mono"><?= e($w['url']) ?></div>
          <div class="text-xs text-slate-400 mt-1">Events: <?= e(str_replace(',', ', ', $w['events'])) ?></div>
          <div class="text-xs text-slate-400">Secret: <code><?= e(substr($w['secret'], 0, 12)) ?>…</code></div>
        </div>
        <div class="flex gap-2 shrink-0">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
            <button type="submit" name="action" value="toggle" class="text-xs text-indigo-600 hover:underline"><?= $w['enabled'] ? 'Disable' : 'Enable' ?></button>
          </form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
            <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$webhooks): ?><div class="p-4 text-sm text-slate-500">No webhooks yet.</div><?php endif; ?>
</div>

<h2 class="font-semibold mb-2">Recent deliveries</h2>
<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($recentDeliveries as $d): ?>
    <div class="p-3 flex items-center justify-between text-sm">
      <span><?= e($d['label']) ?> — <?= e($d['event']) ?></span>
      <span class="<?= $d['success'] ? 'text-green-600' : 'text-red-600' ?>"><?= $d['success'] ? '✓' : '✗' ?> <?= $d['response_code'] ?? 'no response' ?> · <?= time_ago($d['created_at']) ?></span>
    </div>
  <?php endforeach; ?>
  <?php if (!$recentDeliveries): ?><div class="p-4 text-sm text-slate-500">No deliveries yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
