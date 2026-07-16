<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
$pageTitle = 'Currencies — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$baseCurrency = setting('currency', 'USD');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $symbol = trim($_POST['symbol'] ?? '');
        $rate = (float) ($_POST['rate_to_base'] ?? 0);
        if (!preg_match('/^[A-Z]{3}$/', $code) || $name === '' || $rate <= 0) {
            flash_set('error', 'Please provide a valid 3-letter code, name, and positive rate.');
        } else {
            $pdo->prepare('INSERT INTO currencies (code, name, symbol, rate_to_base) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), symbol = VALUES(symbol), rate_to_base = VALUES(rate_to_base)')
                ->execute([$code, $name, $symbol ?: $code, $rate]);
            flash_set('success', 'Currency saved.');
        }
    } elseif ($action === 'update_rate') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $rate = (float) ($_POST['rate_to_base'] ?? 0);
        if ($rate > 0) {
            $pdo->prepare('UPDATE currencies SET rate_to_base = ? WHERE code = ?')->execute([$rate, $code]);
            audit_log($admin['id'], 'currency_rate_updated', 'currency', 0, "$code=$rate");
        }
    } elseif ($action === 'toggle') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($code !== $baseCurrency) {
            $pdo->prepare('UPDATE currencies SET is_enabled = 1 - is_enabled WHERE code = ?')->execute([$code]);
        }
    } elseif ($action === 'delete') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($code !== $baseCurrency) {
            $pdo->prepare('DELETE FROM currencies WHERE code = ?')->execute([$code]);
        }
    }
    redirect('/admin/currencies');
}

$currencies = $pdo->query('SELECT * FROM currencies ORDER BY code')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-1">Currencies</h1>
<p class="text-sm text-slate-600 mb-4">
  Prices are charged in the site's base currency (<strong><?= e($baseCurrency) ?></strong>, set in
  <a href="/admin/settings" class="text-indigo-600 hover:underline">Settings</a>). These conversion rates only control
  the <em>display</em> currency visitors can switch to — checkout always shows and charges the base-currency amount.
  Rates are "1 <?= e($baseCurrency) ?> = X currency" and must be kept up to date manually.
</p>

<div class="bg-white border rounded-lg p-6 max-w-lg mb-6">
  <h2 class="font-semibold mb-3 text-sm">Add / update a currency</h2>
  <form method="post" class="grid grid-cols-4 gap-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <input type="text" name="code" required maxlength="3" placeholder="Code (SGD)" class="border rounded px-2 py-1.5 text-sm">
    <input type="text" name="name" required placeholder="Name" class="border rounded px-2 py-1.5 text-sm">
    <input type="text" name="symbol" placeholder="Symbol (S$)" class="border rounded px-2 py-1.5 text-sm">
    <input type="number" name="rate_to_base" step="0.000001" min="0.000001" required placeholder="Rate" class="border rounded px-2 py-1.5 text-sm">
    <button type="submit" class="col-span-4 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save</button>
  </form>
</div>

<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($currencies as $c): ?>
    <div class="p-4 flex items-center justify-between gap-4 text-sm">
      <div>
        <div class="font-medium"><?= e($c['symbol']) ?> <?= e($c['code']) ?> — <?= e($c['name']) ?>
          <?= $c['code'] === $baseCurrency ? '<span class="text-xs text-indigo-600 ml-1">(base)</span>' : '' ?>
        </div>
        <div class="text-xs text-slate-500">1 <?= e($baseCurrency) ?> = <?= e($c['rate_to_base']) ?> <?= e($c['code']) ?> · updated <?= time_ago($c['updated_at']) ?></div>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <?php if ($c['code'] !== $baseCurrency): ?>
          <form method="post" class="flex items-center gap-1">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_rate">
            <input type="hidden" name="code" value="<?= e($c['code']) ?>">
            <input type="number" name="rate_to_base" step="0.000001" value="<?= e($c['rate_to_base']) ?>" class="w-24 border rounded px-2 py-1 text-xs">
            <button type="submit" class="text-xs bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded">Update</button>
          </form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e($c['code']) ?>">
            <button type="submit" name="action" value="toggle" class="text-xs <?= $c['is_enabled'] ? 'text-amber-600' : 'text-green-600' ?> hover:underline"><?= $c['is_enabled'] ? 'Disable' : 'Enable' ?></button>
          </form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e($c['code']) ?>">
            <button type="submit" name="action" value="delete" class="text-xs text-red-600 hover:underline">Delete</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
