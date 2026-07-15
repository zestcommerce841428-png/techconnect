<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api_auth.php';
$user = require_login();
$pdo = db();

$newKey = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $label = trim($_POST['label'] ?? '') ?: 'Unnamed key';
        $count = $pdo->prepare('SELECT COUNT(*) FROM api_keys WHERE user_id = ? AND revoked_at IS NULL');
        $count->execute([$user['id']]);
        if ((int) $count->fetchColumn() >= 5) {
            flash_set('error', 'You can have up to 5 active API keys. Revoke one first.');
        } else {
            $scope = ($_POST['scope'] ?? '') === 'read_only' ? 'read_only' : 'full_access';
            $newKey = api_key_create($user['id'], mb_substr($label, 0, 120), $scope);
        }
    } elseif ($action === 'revoke') {
        $pdo->prepare('UPDATE api_keys SET revoked_at = NOW() WHERE id = ? AND user_id = ?')
            ->execute([(int) $_POST['id'], $user['id']]);
        flash_set('success', 'API key revoked.');
        redirect('/api_keys');
    }
}

$stmt = $pdo->prepare('SELECT id, label, scope, key_prefix, last_used_at, created_at, revoked_at FROM api_keys WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$keys = $stmt->fetchAll();

$pageTitle = 'API keys — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-1">API keys</h1>
<p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Use these to access the <a href="/api_docs" class="text-indigo-600 hover:underline">public API</a> programmatically. Keep them secret — treat them like a password.</p>

<?php if ($newKey): ?>
  <div class="mb-6 rounded-lg border border-green-300 bg-green-50 dark:bg-green-950 dark:border-green-800 p-4">
    <p class="text-sm font-medium text-green-800 dark:text-green-300 mb-2">New key created — copy it now, it won't be shown again:</p>
    <code class="block bg-white dark:bg-slate-900 border rounded px-3 py-2 text-sm break-all"><?= e($newKey) ?></code>
  </div>
<?php endif; ?>

<div class="card p-6 mb-6 max-w-md">
  <form method="post" class="flex flex-wrap gap-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="text" name="label" placeholder="Key label (e.g. My script)" class="flex-1 min-w-0 border rounded-lg px-3 py-2 text-sm">
    <select name="scope" class="border rounded-lg px-2 py-2 text-sm">
      <option value="full_access">Full access (read + write)</option>
      <option value="read_only">Read-only</option>
    </select>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm">Generate</button>
  </form>
  <p class="text-xs text-slate-500 mt-2">Read-only keys can browse questions/tags/users but cannot post questions via the API.</p>
</div>

<div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y">
  <?php foreach ($keys as $k): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <div>
        <div class="font-medium"><?= e($k['label']) ?> <code class="text-xs text-slate-500">tc_<?= e($k['key_prefix']) ?>…</code>
          <span class="text-xs px-1.5 py-0.5 rounded <?= $k['scope'] === 'read_only' ? 'bg-slate-100 text-slate-600' : 'bg-indigo-100 text-indigo-700' ?>"><?= $k['scope'] === 'read_only' ? 'read-only' : 'full access' ?></span>
        </div>
        <div class="text-xs text-slate-500">
          Created <?= time_ago($k['created_at']) ?>
          <?= $k['last_used_at'] ? ' · last used ' . time_ago($k['last_used_at']) : ' · never used' ?>
          <?= $k['revoked_at'] ? ' · <span class="text-red-600">revoked</span>' : '' ?>
        </div>
      </div>
      <?php if (!$k['revoked_at']): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
          <button type="submit" name="action" value="revoke" class="text-xs text-red-600 hover:underline">Revoke</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$keys): ?><div class="p-4 text-sm text-slate-500">No API keys yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
