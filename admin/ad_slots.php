<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Ad Slots — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$knownSlots = ['sidebar' => 'Sidebar (questions/search pages)', 'homepage_banner' => 'Homepage banner', 'question_footer' => 'Below each question'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $slotKey = $_POST['slot_key'] ?? '';
    if (!isset($knownSlots[$slotKey])) {
        flash_set('error', 'Unknown slot.');
        redirect('/admin/ad_slots');
    }
    $html = trim($_POST['html_content'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $pdo->prepare(
        'INSERT INTO ad_slots (slot_key, name, html_content, link_url, enabled) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), link_url = VALUES(link_url), enabled = VALUES(enabled)'
    )->execute([$slotKey, $knownSlots[$slotKey], $html ?: null, $linkUrl ?: null, $enabled]);
    flash_set('success', 'Ad slot saved.');
    redirect('/admin/ad_slots');
}

$rows = [];
foreach ($pdo->query('SELECT * FROM ad_slots')->fetchAll() as $r) {
    $rows[$r['slot_key']] = $r;
}
?>
<h1 class="text-2xl font-bold mb-2">Ad slots</h1>
<p class="text-sm text-slate-600 mb-6">Paste raw HTML/ad-network embed code (trusted admin input, rendered as-is). Pro members never see ads.</p>
<div class="space-y-6 max-w-2xl">
<?php foreach ($knownSlots as $key => $name): $row = $rows[$key] ?? ['html_content' => '', 'link_url' => '', 'enabled' => 0]; ?>
  <div class="bg-white border rounded-lg p-6">
    <h2 class="font-semibold mb-3"><?= e($name) ?></h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="slot_key" value="<?= e($key) ?>">
      <textarea name="html_content" rows="4" placeholder="Ad embed HTML" class="w-full border rounded px-3 py-2 text-sm font-mono"><?= e($row['html_content'] ?? '') ?></textarea>
      <input type="url" name="link_url" placeholder="Fallback link URL (optional, used only for image ads)" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($row['link_url'] ?? '') ?>">
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="enabled" <?= !empty($row['enabled']) ? 'checked' : '' ?>>
        Enabled
      </label>
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save</button>
    </form>
  </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
