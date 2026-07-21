<?php
require_once __DIR__ . '/includes/auth.php';

// The complete platform capability inventory, grouped. The headline count is
// computed from this list — keep it honest: every entry is a real, shipped feature.
$featureGroups = [
    '❓ Questions & Answers' => [
        'Ask questions with markdown editor', 'Rich editor toolbar', 'Syntax-highlighted code blocks', 'Image & PDF uploads in posts',
        'Answers with voting', 'Accepted answers', 'Comments on questions & answers', 'Edit & delete own comments',
        'Question upvotes & downvotes', 'Answer vote scores', 'View counters', 'Draft questions (save & resume)',
        'Duplicate-question detector while typing', 'Similar/related questions panel', 'Question revision history',
        'Answer revision history', 'Visual diffs between revisions', 'Suggested edits by the community', 'Question merging (admin)',
        'Question bounties', 'Automatic bounty expiry', 'Polls attached to questions', 'Follow/watch a question',
        'Save/bookmark questions', 'Personal collections of questions', 'Public/private collections', 'Location-tagged questions',
        'Trending questions (weekly)', '@-mentions with notifications', 'Report content widget on posts',
    ],
    '🗂️ Organization & Discovery' => [
        '710-category catalog', 'Grouped topics directory with live search', 'Category pages with clean URLs', 'Searchable category picker in ask form',
        'Tags on questions', 'Tags directory with filter & sort', 'Tag pages', 'Full-site search', 'Live search suggestions dropdown',
        'Saved searches', '"/" keyboard shortcut to search', 'Home feed with category strip', 'Getting-started onboarding checklist',
        'Leaderboard (week / month / all-time)', 'Community stats page (cached)', 'Activity feed', 'Pagination everywhere',
    ],
    '👥 Community & Social' => [
        'User profiles with avatar, bio, location', 'Reputation system with point events', 'Reputation history page', 'Badges with tiers',
        'Badge showcase on profiles', '26-week contribution heatmap on profiles', 'Follow users', 'Follow tags', 'Follower notifications',
        'Private messaging inbox', 'Conversation threads', 'Block/unblock users', 'In-app notifications', 'Unread notification counter',
        'Notification preferences per type', 'Mute all notifications until a date', 'Email notifications (answers, mentions, messages)',
        'Weekly tag-digest emails', 'Groups (communities)', 'Group join/leave & member lists', 'Group-scoped questions',
        'Community polls (site-wide)', 'Roadmap with public voting', 'Public changelog', 'Testimonials', 'Community guidelines page',
        '"Available for hire" profile flag',
    ],
    '💼 Jobs & Experts' => [
        'Job board', 'Job posting form', 'Job moderation queue', 'Expert marketplace', 'Expert application & approval flow',
        'Consultation booking requests', 'Expert consultation dashboard', 'My-bookings page for clients', 'Expert payouts (admin)',
        'Consultation request emails',
    ],
    '💳 Monetization & Payments' => [
        'Pro subscriptions', 'Checkout flow', 'Checkout success/cancel pages', 'Stripe integration with signed webhooks',
        'Razorpay integration with signed webhooks', 'PayPal integration with signed webhooks', 'Orders admin', 'Invoices for users',
        'Multi-currency display', 'Live currency conversion', 'Currency switcher in header', 'Payment settings admin (test/live modes)',
        'Ad slot system with admin manager', 'Affiliate link tracker with /go redirects', 'Payment gateway abstraction layer',
    ],
    '🔐 Authentication & Account Security' => [
        'Email + password login', 'Registration with email verification', 'Password strength meter', 'Login with username or email',
        'TOTP two-factor authentication (2FA)', 'Trust-this-device for 30 days', 'Passkey / WebAuthn login', 'Passkey registration',
        'Social login (OAuth)', 'Telegram login', 'Remember-me with selector/validator tokens', 'Hashed password-reset tokens',
        'Reset invalidates all sessions & devices', 'Email change with re-verification', 'Session versioning (log out everywhere)',
        'Active session manager', 'Login history with IP & device', 'Account data export', 'Registration open/close switch',
    ],
    '🛡️ Platform Security' => [
        'CSRF protection on every form', 'Prepared statements everywhere (SQLi-proof)', 'Output escaping everywhere (XSS-proof)',
        'Session-based rate limiting', 'IP-based rate limiting on auth endpoints', 'Per-API-key rate limiting', 'Public API IP rate limiting',
        'Honeypot spam traps on public forms', 'reCAPTCHA v3 support', 'Cloudflare Turnstile support', 'Automated spam detection on posts',
        'IP address blocklist', 'Global admin audit log', 'Moderator permission matrix', 'Security headers (XFO, nosniff, referrer, permissions)',
        'HSTS', 'security.txt (RFC 9116)', 'Upload MIME sniffing + random filenames', 'PHP execution disabled in uploads dir',
        'Secrets blocked from web access (.env, config, .git)', 'Admin-only maintenance mode', 'Password hashing with modern algorithms',
        'Uniform responses against user enumeration',
    ],
    '📰 Content Management' => [
        'Blog with categories', 'Blog category admin (create/rename/delete)', 'Public blog filtering by category',
        'Full-text blog search', 'Related-posts widget (same-category, backfilled)', 'Bulk blog post import (CSV)',
        'CMS pages with categories', 'Page category admin (create/rename/delete)', 'Public pages index grouped by category',
        'Related-pages widget (same-category)', 'Breadcrumbs + structured data on CMS pages', 'Page duplication (as unpublished draft)',
        'Scheduled blog publishing', 'Blog RSS feed', 'CMS pages with SEO fields', 'Footer page management',
        'Site announcements banner', 'FAQ page', 'About / Privacy / Terms / Cookies pages', 'URL redirect manager (301s)',
        'Uploads manager (admin)', 'WYSIWYG-style markdown rendering', 'Site branding settings (logo, name, tagline)',
    ],
    '🚀 SEO & Performance' => [
        'Clean extensionless URLs', 'Canonical URLs on every page', 'Dynamic XML sitemap (pages, questions, tags, categories, blog)',
        'robots.txt', 'RSS feeds (questions & blog)', 'Open Graph tags site-wide', 'Twitter cards', 'Per-question branded share images (auto-generated)',
        'QAPage structured data', 'BreadcrumbList structured data', 'Organization structured data', 'Visible breadcrumbs on questions',
        'HTML minification (whole site)', 'Minified CSS & JS builds', 'Gzip compression', 'Browser caching rules for static assets',
        'Native lazy-loading images', 'Share-image disk caching', 'Stats query caching', '56 custom-designed error pages',
        'Custom 404 & 500 pages', 'noindex on admin', 'Meta descriptions per page',
    ],
    '📱 Mobile & PWA' => [
        'Fully responsive layout', 'PWA manifest (installable app)', 'Service worker with offline support', 'Offline fallback page',
        'Cached pages readable offline', 'Native share sheet on questions', 'Dark mode with system detection', 'Theme toggle with no-flash load',
        'Mobile navigation drawer', 'WhatsApp chat button', 'Back-to-top button', 'Touch-friendly hit targets',
    ],
    '🔌 API & Integrations' => [
        'REST API v1 with 70+ endpoints', 'API key management with show-once secrets', 'Read-only vs full-access key scopes',
        'API documentation page', 'Questions/answers/comments over API', 'Users, badges & reputation over API', 'Groups & follows over API',
        'Jobs, experts & consultations over API', 'Orders, invoices & subscriptions over API', 'Search & suggestions over API',
        'Outgoing webhooks with event types', 'Webhook delivery logs & retries', 'Telegram notifications for admin events',
        'Telegram question-of-the-day poster', 'Newsletter subscription + unsubscribe flow', 'Tawk.to live chat embed', 'Health-check endpoint',
        'Status page',
    ],
    '🧰 Admin & Operations' => [
        'Admin dashboard with weekly trends', '14-day activity chart', 'Needs-attention queue with live counts', 'Sidebar badges for pending work',
        'User management (roles, bans)', 'Moderation queue for reports', 'Feedback & bug-report manager with statuses', 'Suggested-edit review queue',
        'Bulk category visibility controls', 'Category / tag managers', 'Analytics dashboard', 'Site health checks', 'Newsletter composer',
        'Data export (CSV)', 'DB backup cron', 'Scheduled-publish cron', 'Bounty-resolution cron', 'Tag-digest cron', 'Merge & redirect tools',
        'IP block manager', 'Audit log browser', 'Integrations settings', 'Social login settings', 'Ad slot manager', 'Roadmap manager',
        'Changelog manager', 'Announcement manager', 'Testimonial manager', 'Group manager', 'Expert approval panel', 'Payout panel',
        'Poll manager', 'Badge manager', 'Moderator permissions editor', 'Branding editor', 'Global settings panel',
    ],
    '☁️ Storage & Media' => [
        'S3-compatible object storage', 'Cloudflare R2 support', 'AWS S3 support', 'DigitalOcean Spaces support',
        'Backblaze B2 support', 'Wasabi support', 'MinIO (self-hosted) support', 'One-click provider presets',
        'Live connection test (write/read/delete)', 'Automatic fallback to local disk', 'AWS Signature V4 signing',
        'Automatic image optimisation on upload', 'WebP conversion', 'Max-dimension downscaling', 'Quality control',
        'Storage usage tracking', 'Per-provider monthly cost estimates', 'Egress cost modelling', 'CDN base URL support',
    ],
    '▶️ Rich media embeds' => [
        'YouTube embeds (watch, youtu.be, Shorts, live)', 'Privacy-mode YouTube (youtube-nocookie)', 'Vimeo embeds',
        'Dailymotion embeds (dailymotion.com & dai.ly)', 'Loom screen-recording embeds', 'Streamable embeds',
        'Google Drive video embeds', 'CodePen live embeds', 'JSFiddle live embeds', 'CodeSandbox embeds',
        'Spotify embeds (track, album, playlist, episode, show)', 'Paste-a-URL embedding (no shortcode syntax)',
        'ID-only extraction (user URL never reaches the iframe)', 'Per-provider sizing (16:9 video vs fixed-height audio)',
        'Lazy-loaded iframes', 'Sandboxed referrer policy on embeds',
    ],
    '🧩 Under the Hood' => [
        'Flash message system', 'Toast notifications', 'Relative "time ago" timestamps', 'Reusable question-card component',
        'Server-side markdown rendering endpoint', 'Currency preference endpoint', 'One-click content reporting API',
        'Follow/unfollow API endpoints', 'Mark-all-notifications-read', 'Per-notification delete', 'Personal activity timeline page',
        'Reputation breakdown page', 'My drafts page', 'Saved questions page', 'Newsletter unsubscribe page',
        'Database migration system (28 migrations)', 'Environment-based configuration', 'Central error handler with request IDs',
        'Structured JSON application logging', 'Composer dependency management', 'Slug generation with uniqueness guarantee',
        'Zero-build shared-hosting deployability', 'Dev server router mirroring production rewrites',
    ],
    '📝 Feedback & Support' => [
        'Public feedback & bug-report system', 'Bug severity levels', 'Status tracking for reporters (new → resolved)',
        'Team responses visible to reporters', 'Critical-bug instant email alerts', 'Contact form with reply-to routing',
        'Consultation request form', 'Cookie consent banner', 'Accessibility skip links', 'ARIA labels & live regions',
    ],
];

$totalFeatures = array_sum(array_map('count', $featureGroups));

$pageTitle = $totalFeatures . '+ platform features — ' . SITE_NAME;
$pageDescription = SITE_NAME . ' ships ' . $totalFeatures . '+ real features: Q&A, community, experts, payments, security, PWA, API and a full admin suite.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-4xl mx-auto">
  <div class="text-center mb-8">
    <h1 class="text-3xl font-bold"><?= $totalFeatures ?>+ features. All real. All included.</h1>
    <p class="text-sm text-slate-500 mt-2 max-w-xl mx-auto">Everything below is live on <?= e(SITE_NAME) ?> today — no demos, no "coming soon".
      Spot something missing? <a href="/feedback?type=feature" class="text-indigo-600 hover:underline">Request it</a>.</p>
  </div>

  <div class="space-y-6">
    <?php foreach ($featureGroups as $group => $items): ?>
      <section class="bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-5">
        <h2 class="font-semibold mb-3"><?= e($group) ?> <span class="text-xs font-normal text-slate-400">(<?= count($items) ?>)</span></h2>
        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm text-slate-700 dark:text-slate-300">
          <?php foreach ($items as $item): ?>
            <li class="flex gap-2"><span class="text-green-600 shrink-0">✓</span><span><?= e($item) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </div>

  <div class="text-center mt-8">
    <a href="/register" class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white font-medium px-6 py-3 rounded-lg">Join <?= e(SITE_NAME) ?> free</a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
