<?php
$footerPages = db()->query('SELECT slug, title FROM pages WHERE is_published = 1 AND show_in_footer = 1' . sd_filter() . ' ORDER BY title')->fetchAll();
$socialLinks = [
    'Twitter' => setting('social_twitter'),
    'Facebook' => setting('social_facebook'),
    'LinkedIn' => setting('social_linkedin'),
    'Instagram' => setting('social_instagram'),
];
$footerText = setting('footer_text');
$telegramChannel = setting('telegram_channel_url');
$telegramGroup = setting('telegram_group_url');
$whatsappNumber = preg_replace('/[^0-9]/', '', setting('whatsapp_number', ''));
$tawkWidgetId = setting('tawk_widget_id');
?>
</main>
<footer class="bg-slate-900 text-slate-300 text-sm">
  <?php if (setting('newsletter_enabled', '1') === '1'): ?>
  <div class="border-b border-slate-800">
    <div class="max-w-5xl mx-auto px-4 py-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-white font-medium text-sm">Get the weekly digest</p>
        <p class="text-xs text-slate-400">New questions and top answers, once a week. No spam.</p>
      </div>
      <form data-newsletter-form class="flex gap-2">
        <input type="email" name="email" required placeholder="you@example.com" class="border border-slate-700 bg-slate-800 text-white placeholder-slate-500 rounded-lg px-3 py-2 text-sm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap">Subscribe</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
  <div class="max-w-5xl mx-auto px-4 py-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <span>&copy; <?= date('Y') ?> <?= e($siteName ?? SITE_NAME) ?></span>
      <nav class="flex flex-wrap gap-4">
        <a href="/about" class="hover:underline">About</a>
        <a href="/features" class="hover:underline">Features</a>
        <a href="/blog" class="hover:underline">Blog</a>
        <a href="/contact" class="hover:underline">Contact</a>
        <a href="/feedback" class="hover:underline">Feedback</a>
        <a href="/guidelines" class="hover:underline">Guidelines</a>
        <a href="/privacy" class="hover:underline">Privacy</a>
        <a href="/terms" class="hover:underline">Terms</a>
        <a href="/cookies" class="hover:underline">Cookies</a>
        <a href="/faq" class="hover:underline">FAQ</a>
        <a href="/changelog" class="hover:underline">Changelog</a>
        <a href="/roadmap" class="hover:underline">Roadmap</a>
        <a href="/categories" class="hover:underline">Topics</a>
        <a href="/tags" class="hover:underline">Tags</a>
        <a href="/collections" class="hover:underline">Collections</a>
        <a href="/api_docs" class="hover:underline">API</a>
        <a href="/stats" class="hover:underline">Stats</a>
        <a href="/status" class="hover:underline">Status</a>
        <a href="/rss" class="hover:underline">RSS</a>
        <?php $dedicatedSlugs = ['about', 'privacy', 'terms', 'cookies', 'guidelines', 'faq']; ?>
        <?php foreach ($footerPages as $fp): ?>
          <?php if (!in_array($fp['slug'], $dedicatedSlugs, true)): ?>
            <a href="/page/<?= e($fp['slug']) ?>" class="hover:underline"><?= e($fp['title']) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </div>
    <?php if (array_filter($socialLinks) || $telegramChannel || $telegramGroup): ?>
      <div class="flex flex-wrap gap-4 mt-3">
        <?php foreach ($socialLinks as $label => $url): ?>
          <?php if ($url): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener" class="hover:underline text-xs"><?= e($label) ?></a><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($telegramChannel): ?><a href="<?= e($telegramChannel) ?>" target="_blank" rel="noopener" class="hover:underline text-xs">Telegram Channel</a><?php endif; ?>
        <?php if ($telegramGroup): ?><a href="<?= e($telegramGroup) ?>" target="_blank" rel="noopener" class="hover:underline text-xs">Telegram Group</a><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($footerText): ?><p class="text-xs text-slate-500 mt-3"><?= e($footerText) ?></p><?php endif; ?>
  </div>
</footer>

<?php if ($whatsappNumber): ?>
<a href="https://wa.me/<?= e($whatsappNumber) ?>?text=<?= urlencode(setting('whatsapp_default_message', 'Hi!')) ?>"
   target="_blank" rel="noopener" aria-label="Chat on WhatsApp"
   class="fixed bottom-5 right-5 z-40 w-14 h-14 rounded-full bg-green-500 hover:bg-green-400 text-white flex items-center justify-center text-2xl shadow-lg">
  💬
</a>
<?php endif; ?>

<?php if ($tawkWidgetId): ?>
<script type="text/javascript">
  var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
  (function () {
    var s1 = document.createElement("script"), s0 = document.getElementsByTagName("script")[0];
    s1.async = true;
    s1.src = 'https://embed.tawk.to/' + <?= json_encode(trim($tawkWidgetId, '/')) ?>;
    s1.charset = 'UTF-8';
    s1.setAttribute('crossorigin', '*');
    s0.parentNode.insertBefore(s1, s0);
  })();
</script>
<?php endif; ?>

<button type="button" id="back-to-top" aria-label="Back to top"
        class="hidden fixed bottom-5 left-5 z-40 w-11 h-11 rounded-full bg-slate-900/80 hover:bg-slate-900 text-white text-lg shadow-lg backdrop-blur">↑</button>

<div id="cookie-consent" class="hidden fixed bottom-0 inset-x-0 z-50 bg-slate-900 text-slate-200 text-sm">
  <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    <span>We use cookies for login sessions and to improve <?= e($siteName ?? SITE_NAME) ?>. See our <a href="/cookies" class="underline">cookie policy</a>.</span>
    <button type="button" id="cookie-consent-ok" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-1.5 rounded-lg text-sm font-medium">Got it</button>
  </div>
</div>

<script>
(function () {
  var top = document.getElementById('back-to-top');
  window.addEventListener('scroll', function () {
    top.classList.toggle('hidden', window.scrollY < 600);
  }, { passive: true });
  top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
  }

  var consent = document.getElementById('cookie-consent');
  try {
    if (!localStorage.getItem('cookie_consent')) consent.classList.remove('hidden');
    document.getElementById('cookie-consent-ok').addEventListener('click', function () {
      localStorage.setItem('cookie_consent', '1');
      consent.classList.add('hidden');
    });
  } catch (e) { /* private mode without storage — leave banner hidden */ }
})();
</script>

<script src="/assets/dist/js/theme.js"></script>
<script src="/assets/dist/js/ui.js"></script>
<script src="/assets/dist/js/search_suggest.js"></script>
</body>
</html>
