<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $label = trim($_POST['label'] ?? '');
    $target = trim($_POST['target_url'] ?? '');
    if (mb_strlen($label) < 2 || !filter_var($target, FILTER_VALIDATE_URL)) {
        flash_set('error', 'Please provide a label and a valid target URL.');
    } else {
        $slug = null;
        for ($i = 0; $i < 5; $i++) {
            $candidate = substr(bin2hex(random_bytes(4)), 0, 6);
            $check = $pdo->prepare('SELECT id FROM affiliate_links WHERE slug = ?');
            $check->execute([$candidate]);
            if (!$check->fetch()) { $slug = $candidate; break; }
        }
        if ($slug) {
            $pdo->prepare('INSERT INTO affiliate_links (user_id, label, target_url, slug) VALUES (?, ?, ?, ?)')
                ->execute([$user['id'], $label, $target, $slug]);
            flash_set('success', 'Affiliate link created.');
        } else {
            flash_set('error', 'Could not generate a unique link, please try again.');
        }
    }
    redirect('/affiliate');
}

$stmt = $pdo->prepare('SELECT * FROM affiliate_links WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$links = $stmt->fetchAll();

$pageTitle = 'Affiliate links — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-4">Your affiliate links</h1>
<div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-6 mb-6 max-w-lg">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="text" name="label" required placeholder="Label (e.g. My blog post)" class="w-full border rounded px-3 py-2 text-sm">
    <input type="url" name="target_url" required placeholder="https://destination.example.com/..." class="w-full border rounded px-3 py-2 text-sm">
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Create link</button>
  </form>
</div>

<div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg divide-y">
  <?php foreach ($links as $l): ?>
    <div class="p-4 flex items-center justify-between gap-4">
      <div>
        <div class="font-medium"><?= e($l['label']) ?></div>
        <div class="text-sm text-indigo-600"><?= e(SITE_URL . '/go/' . $l['slug']) ?></div>
      </div>
      <div class="text-sm text-slate-500 shrink-0"><?= (int) $l['clicks'] ?> clicks · <?= (int) $l['conversions'] ?> conversions</div>
    </div>
  <?php endforeach; ?>
  <?php if (!$links): ?><div class="p-4 text-sm text-slate-500">No affiliate links yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
