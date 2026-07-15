<?php
require_once __DIR__ . '/oauth/oauth.php';
$enabledProviders = oauth_enabled_providers();
$telegramBotUsername = setting('telegram_login_bot_username', '');
$providerLabels = ['google' => 'Google', 'facebook' => 'Facebook', 'github' => 'GitHub', 'microsoft' => 'Microsoft', 'linkedin' => 'LinkedIn', 'twitter' => 'X', 'zoho' => 'Zoho'];
$providerIcons = ['google' => '🔴', 'facebook' => '🔵', 'github' => '⚫', 'microsoft' => '🟦', 'linkedin' => '💼', 'twitter' => '✖️', 'zoho' => '🟠'];
?>
<?php if ($enabledProviders || $telegramBotUsername): ?>
  <div class="my-5">
    <div class="flex items-center gap-3 text-xs text-slate-400 mb-3">
      <span class="flex-1 border-t border-slate-200 dark:border-slate-700"></span>
      or continue with
      <span class="flex-1 border-t border-slate-200 dark:border-slate-700"></span>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <?php foreach ($enabledProviders as $p): ?>
        <a href="/oauth_start?provider=<?= e($p) ?>" class="flex items-center justify-center gap-2 border rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
          <span><?= $providerIcons[$p] ?? '' ?></span> <?= e($providerLabels[$p] ?? ucfirst($p)) ?>
        </a>
      <?php endforeach; ?>
      <?php if ($telegramBotUsername): ?>
        <a href="https://oauth.telegram.org/auth?bot_id=<?= e($telegramBotUsername) ?>&origin=<?= urlencode(SITE_URL) ?>&return_to=<?= urlencode(SITE_URL . '/telegram_login') ?>"
           class="flex items-center justify-center gap-2 border rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
          <span>✈️</span> Telegram
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
