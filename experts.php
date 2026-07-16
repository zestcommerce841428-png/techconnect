<?php
require_once __DIR__ . '/includes/auth.php';
$user = current_user();

$pdo = db();
$isApprovedExpert = false;
if ($user) {
    $chk = $pdo->prepare('SELECT 1 FROM expert_profiles WHERE user_id = ? AND is_approved = 1');
    $chk->execute([$user['id']]);
    $isApprovedExpert = (bool) $chk->fetchColumn();
}
$experts = $pdo->query(
    "SELECT u.id, u.username, u.avatar, u.reputation, ep.headline, ep.bio, ep.hourly_rate_cents
     FROM expert_profiles ep JOIN users u ON u.id = ep.user_id
     WHERE ep.is_approved = 1 ORDER BY u.reputation DESC LIMIT 50"
)->fetchAll();

$pageTitle = 'Find an expert — ' . SITE_NAME;
$pageDescription = 'Book paid 1:1 consultations with approved experts on ' . SITE_NAME . '.';
require __DIR__ . '/includes/header.php';
?>
<div class="flex items-start justify-between gap-4 mb-1">
  <h1 class="text-2xl font-bold">Expert marketplace</h1>
  <?php if ($user): ?>
    <a href="<?= $isApprovedExpert ? '/expert_consultations' : '/my_consultations' ?>" class="text-xs text-indigo-600 hover:underline shrink-0 mt-1">
      <?= $isApprovedExpert ? 'My consultation requests →' : 'My bookings →' ?>
    </a>
  <?php endif; ?>
</div>
<p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Book a paid consultation with a vetted community expert. <a href="/expert_apply" class="text-indigo-600 hover:underline">Want to be listed?</a></p>

<?php if (!$experts): ?>
  <p class="text-slate-500 text-sm">No approved experts yet.</p>
<?php endif; ?>
<div class="grid gap-4 sm:grid-cols-2">
  <?php foreach ($experts as $ex): ?>
    <div class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-4">
      <div class="flex items-center gap-3 mb-2">
        <img src="<?= e($ex['avatar'] ?: '/assets/images/default-avatar.png') ?>" class="w-10 h-10 rounded-full" alt="<?= e($ex['username']) ?>'s avatar">
        <div>
          <a href="/u/<?= e($ex['username']) ?>" class="font-semibold hover:underline"><?= e($ex['username']) ?></a>
          <div class="text-xs text-slate-500"><?= e((string) $ex['reputation']) ?> reputation</div>
        </div>
      </div>
      <?php if ($ex['headline']): ?><p class="text-sm font-medium mb-1"><?= e($ex['headline']) ?></p><?php endif; ?>
      <?php if ($ex['bio']): ?><p class="text-sm text-slate-600 dark:text-slate-400 mb-3"><?= e(mb_strimwidth($ex['bio'], 0, 160, '…')) ?></p><?php endif; ?>
      <div class="flex items-center justify-between">
        <span class="text-sm font-semibold"><?= $ex['hourly_rate_cents'] ? e(format_money((int) $ex['hourly_rate_cents'])) . '/hr' : 'Rate on request' ?></span>
        <a href="/consultation_request?expert=<?= (int) $ex['id'] ?>" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Request consultation</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
