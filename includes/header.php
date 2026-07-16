<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$unreadNotifCount = $user ? unread_notification_count((int) $user['id']) : 0;

if (setting('maintenance_mode', '0') === '1' && (!$user || !in_array($user['role'], ['admin', 'moderator'], true))) {
    http_response_code(503);
    header('Retry-After: 3600');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Down for maintenance</title></head>'
        . '<body style="font-family:system-ui;text-align:center;padding:4rem;">'
        . '<h1>We\'ll be right back</h1><p>The site is temporarily down for maintenance.</p></body></html>';
    exit;
}

$siteName = setting('site_name', SITE_NAME);
$pageTitle = $pageTitle ?? $siteName . ' — ' . setting('tagline', 'Ask, answer, connect');
$pageDescription = $pageDescription ?? 'A worldwide community where people ask questions and get real answers from nearby and global experts.';
$logoPath = setting('logo_path');
$faviconPath = setting('favicon_path');
$ogImagePath = SITE_URL . '/assets/images/og-image.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e(SITE_URL . $_SERVER['REQUEST_URI']) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:url" content="<?= e(SITE_URL . $_SERVER['REQUEST_URI']) ?>">
<meta property="og:image" content="<?= e($ogImagePath) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDescription) ?>">
<meta name="twitter:image" content="<?= e($ogImagePath) ?>">
<meta name="theme-color" content="#0f172a">
<?php if ($faviconPath): ?>
  <link rel="icon" href="<?= e($faviconPath) ?>">
<?php else: ?>
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
<?php endif; ?>
<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">
<link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> — Latest questions" href="<?= e(SITE_URL) ?>/rss">
<link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> Blog" href="<?= e(SITE_URL) ?>/rss?feed=blog">
<link rel="stylesheet" href="/assets/css/tailwind.min.css">
<script>
  // Inline (not deferred) so the theme class is set before first paint — avoids a flash of the wrong theme.
  (function () {
    var stored = localStorage.getItem('theme');
    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (stored === 'dark' || (!stored && prefersDark)) {
      document.documentElement.classList.add('dark');
    }
  })();
</script>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $siteName,
    'url' => SITE_URL,
    'logo' => SITE_URL . '/assets/images/android-chrome-512x512.png',
], JSON_UNESCAPED_SLASHES) ?></script>
<?php $gaId = setting('ga_measurement_id'); if ($gaId): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaId) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', <?= json_encode($gaId) ?>, { anonymize_ip: true });
</script>
<?php endif; ?>
<?php $captchaProvider = setting('captcha_provider', 'none'); ?>
<?php if ($captchaProvider === 'recaptcha' && setting('recaptcha_site_key')): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= e(setting('recaptcha_site_key')) ?>"></script>
<?php elseif ($captchaProvider === 'turnstile' && setting('turnstile_site_key')): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</head>
<body class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 min-h-screen flex flex-col">
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 bg-indigo-600 text-white px-3 py-1.5 rounded z-50">Skip to content</a>
<?php
// Site-wide announcement banner (admin-managed, one live at a time).
try {
    $announcement = db()->query('SELECT message, link_url, style FROM announcements WHERE enabled = 1 ORDER BY created_at DESC LIMIT 1')->fetch();
} catch (Throwable $e) {
    $announcement = null;
}
if ($announcement):
    $bannerClasses = match ($announcement['style']) {
        'success' => 'bg-green-600',
        'warning' => 'bg-amber-500',
        default => 'bg-indigo-600',
    };
?>
<div class="<?= $bannerClasses ?> text-white text-sm text-center px-4 py-2">
  <?= e($announcement['message']) ?>
  <?php if ($announcement['link_url']): ?><a href="<?= e($announcement['link_url']) ?>" class="underline font-medium ml-1">Learn more</a><?php endif; ?>
</div>
<?php endif; ?>
<header class="bg-slate-900 dark:bg-slate-900 text-white sticky top-0 z-40 shadow">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
    <a href="/" class="font-bold text-lg flex items-center gap-2 shrink-0">
      <?php if ($logoPath): ?><img src="<?= e($logoPath) ?>" alt="<?= e($siteName) ?>" class="h-7 w-auto"><?php else: ?><?= e($siteName) ?><?php endif; ?>
    </a>

    <form action="/search" method="get" class="hidden md:block flex-1 max-w-md relative" role="search" autocomplete="off">
      <input type="search" name="q" placeholder="Search questions…  (press /)" data-header-search
             class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-400 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
      <div data-search-suggestions class="hidden absolute left-0 right-0 top-full mt-1 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg overflow-hidden z-50"></div>
    </form>

    <nav class="hidden lg:flex items-center gap-4 text-sm ml-auto" aria-label="Primary">
      <a href="/questions" class="hover:text-indigo-300">Browse</a>
      <a href="/groups" class="hover:text-indigo-300">Groups</a>
      <a href="/jobs" class="hover:text-indigo-300">Jobs</a>
      <a href="/experts" class="hover:text-indigo-300">Experts</a>
      <a href="/blog" class="hover:text-indigo-300">Blog</a>
      <?php if ($user && empty($user['is_pro'])): ?><a href="/pro" class="text-amber-300 hover:text-amber-200">Go Pro</a><?php endif; ?>
      <?php if ($user): ?>
        <a href="/ask" class="bg-indigo-500 hover:bg-indigo-400 px-3 py-1.5 rounded-lg font-medium">Ask</a>
        <div class="relative" data-user-menu>
          <button type="button" data-user-menu-toggle class="relative flex items-center gap-1.5 hover:text-indigo-300" aria-haspopup="true" aria-expanded="false">
            <?php if (!empty($user['avatar'])): ?><img src="<?= e($user['avatar']) ?>" alt="" class="w-6 h-6 rounded-full object-cover"><?php else: ?><span class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-xs font-bold"><?= e(mb_strtoupper(mb_substr($user['username'], 0, 1))) ?></span><?php endif; ?>
            <?= e($user['username']) ?> ▾
            <?php if ($unreadNotifCount > 0): ?>
              <span class="absolute -top-1.5 -left-1.5 bg-red-500 text-white text-[10px] leading-none font-bold rounded-full min-w-[1.1rem] h-[1.1rem] flex items-center justify-center px-0.5" aria-label="<?= $unreadNotifCount ?> unread notifications"><?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?></span>
            <?php endif; ?>
          </button>
          <div data-user-menu-panel class="hidden absolute right-0 mt-2 w-44 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg shadow-lg border dark:border-slate-700 py-1 text-sm">
            <a href="/profile" class="block px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Profile</a>
            <a href="/feed" class="block px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Feed</a>
            <a href="/saved" class="block px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Saved</a>
            <a href="/inbox" class="flex items-center justify-between px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Inbox<?php if ($unreadNotifCount > 0): ?><span class="bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[1.1rem] h-[1.1rem] flex items-center justify-center px-0.5"><?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?></span><?php endif; ?></a>
            <a href="/invoices" class="block px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Invoices</a>
            <a href="/notification_settings" class="block px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Settings</a>
            <a href="/security_account" class="block px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Account security</a>
            <a href="/security_sessions" class="block px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Login activity</a>
            <?php if (in_array($user['role'], ['admin', 'moderator'], true)): ?>
              <a href="/admin/dashboard" class="block px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Admin panel</a>
            <?php endif; ?>
            <hr class="my-1 border-slate-200 dark:border-slate-700">
            <a href="/logout" class="block px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">Log out</a>
          </div>
        </div>
      <?php else: ?>
        <a href="/login" class="hover:text-indigo-300">Log in</a>
        <a href="/register" class="bg-indigo-500 hover:bg-indigo-400 px-3 py-1.5 rounded-lg font-medium">Join free</a>
      <?php endif; ?>
      <form method="post" action="/api/set_currency.php" class="inline-block">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
        <select name="currency" onchange="this.form.submit()" title="Display currency" class="bg-slate-800 text-slate-200 text-xs rounded px-1.5 py-1.5 border border-slate-700">
          <?php foreach (currency_list() as $c): ?>
            <option value="<?= e($c['code']) ?>" <?= display_currency() === $c['code'] ? 'selected' : '' ?>><?= e($c['code']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <button type="button" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode" class="p-1.5 rounded hover:bg-slate-800">🌓</button>
    </nav>

    <button type="button" data-mobile-nav-toggle aria-label="Open menu" aria-expanded="false" class="lg:hidden ml-auto p-2 rounded hover:bg-slate-800 text-xl leading-none">☰</button>
  </div>

  <nav data-mobile-nav class="hidden lg:hidden border-t border-slate-800 px-4 py-3 space-y-1 text-sm" aria-label="Mobile">
    <form action="/search" method="get" role="search" class="mb-2">
      <input type="search" name="q" placeholder="Search questions…" class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-400 rounded-lg px-3 py-2 text-sm">
    </form>
    <?php
    $mobileLinks = [['/questions', 'Browse'], ['/groups', 'Groups'], ['/jobs', 'Jobs'], ['/experts', 'Experts'], ['/blog', 'Blog'], ['/leaderboard', 'Leaderboard']];
    if ($user) {
        array_push($mobileLinks, ['/ask', 'Ask a question'], ['/feed', 'Feed'], ['/saved', 'Saved'], ['/inbox', 'Inbox' . ($unreadNotifCount > 0 ? ' (' . $unreadNotifCount . ')' : '')], ['/profile', 'Profile'], ['/logout', 'Log out']);
    } else {
        array_push($mobileLinks, ['/login', 'Log in'], ['/register', 'Join free']);
    }
    foreach ($mobileLinks as [$href, $label]): ?>
      <a href="<?= e($href) ?>" class="block px-2 py-2 rounded hover:bg-slate-800"><?= e($label) ?></a>
    <?php endforeach; ?>
    <button type="button" data-theme-toggle class="block w-full text-left px-2 py-2 rounded hover:bg-slate-800">🌓 Toggle dark mode</button>
  </nav>
</header>
<?php if ($user && empty($user['email_verified_at'])): ?>
<div class="bg-amber-50 dark:bg-amber-950 border-b border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-sm px-4 py-2">
  <div class="max-w-6xl mx-auto flex items-center justify-between gap-3 flex-wrap">
    <span>📧 Please verify your email address to secure your account.</span>
    <form method="post" action="/verify_email"><?= csrf_field() ?><button type="submit" class="underline font-medium">Resend verification email</button></form>
  </div>
</div>
<?php endif; ?>
<main id="main-content" class="flex-1 max-w-6xl mx-auto px-4 py-6 w-full min-w-0 overflow-x-hidden">
<?php require __DIR__ . '/site_poll_widget.php'; ?>
<?php if ($msg = flash_get('error')): ?>
  <div role="alert" class="mb-4 rounded border border-red-300 bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200 dark:border-red-800 px-4 py-2"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('success')): ?>
  <div role="status" class="mb-4 rounded border border-green-300 bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200 dark:border-green-800 px-4 py-2"><?= e($msg) ?></div>
<?php endif; ?>
