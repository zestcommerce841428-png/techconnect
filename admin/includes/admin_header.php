<?php
require_once __DIR__ . '/../../includes/auth.php';
$admin = require_role('admin', 'moderator');
$pageTitle = $pageTitle ?? 'Admin — ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/css/tailwind.min.css">
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col md:flex-row">
<aside class="w-full md:w-56 bg-slate-900 text-white p-4 shrink-0">
  <div class="flex items-center justify-between md:block">
    <div class="font-bold md:mb-6"><?= e(SITE_NAME) ?> Admin</div>
    <button type="button" onclick="document.getElementById('admin-nav').classList.toggle('hidden')" class="md:hidden p-1.5 rounded hover:bg-slate-800" aria-label="Toggle admin menu">☰</button>
  </div>
  <nav id="admin-nav" class="hidden md:block space-y-1 text-sm mt-3 md:mt-0">
    <a href="/admin/dashboard" class="block px-2 py-1.5 rounded hover:bg-slate-800">Dashboard</a>
    <a href="/admin/users" class="block px-2 py-1.5 rounded hover:bg-slate-800">Users</a>
    <a href="/admin/moderation" class="block px-2 py-1.5 rounded hover:bg-slate-800">Moderation</a>
    <a href="/admin/feedback" class="block px-2 py-1.5 rounded hover:bg-slate-800">Feedback</a>
    <a href="/admin/merge_questions" class="block px-2 py-1.5 rounded hover:bg-slate-800">Merge Questions</a>
    <a href="/admin/suggested_edits" class="block px-2 py-1.5 rounded hover:bg-slate-800">Suggested Edits</a>
    <a href="/admin/jobs" class="block px-2 py-1.5 rounded hover:bg-slate-800">Jobs</a>
    <a href="/admin/uploads" class="block px-2 py-1.5 rounded hover:bg-slate-800">Uploads</a>
    <a href="/admin/pages" class="block px-2 py-1.5 rounded hover:bg-slate-800">Pages</a>
    <a href="/admin/blog" class="block px-2 py-1.5 rounded hover:bg-slate-800">Blog</a>
    <a href="/admin/categories" class="block px-2 py-1.5 rounded hover:bg-slate-800">Categories</a>
    <a href="/admin/tags" class="block px-2 py-1.5 rounded hover:bg-slate-800">Tags</a>
    <a href="/admin/announcements" class="block px-2 py-1.5 rounded hover:bg-slate-800">Announcements</a>
    <a href="/admin/testimonials" class="block px-2 py-1.5 rounded hover:bg-slate-800">Testimonials</a>
    <a href="/admin/changelog" class="block px-2 py-1.5 rounded hover:bg-slate-800">Changelog</a>
    <a href="/admin/roadmap" class="block px-2 py-1.5 rounded hover:bg-slate-800">Roadmap</a>
    <a href="/admin/analytics" class="block px-2 py-1.5 rounded hover:bg-slate-800">Analytics</a>
    <a href="/admin/experts" class="block px-2 py-1.5 rounded hover:bg-slate-800">Experts</a>
    <a href="/admin/groups" class="block px-2 py-1.5 rounded hover:bg-slate-800">Groups</a>
    <a href="/admin/security_2fa" class="block px-2 py-1.5 rounded hover:bg-slate-800">My 2FA</a>
    <?php if ($admin['role'] === 'admin'): ?>
      <a href="/admin/audit_log" class="block px-2 py-1.5 rounded hover:bg-slate-800">Audit Log</a>
      <a href="/admin/ip_blocks" class="block px-2 py-1.5 rounded hover:bg-slate-800">IP Blocks</a>
      <a href="/admin/redirects" class="block px-2 py-1.5 rounded hover:bg-slate-800">Redirects</a>
      <a href="/admin/newsletter" class="block px-2 py-1.5 rounded hover:bg-slate-800">Newsletter</a>
      <a href="/admin/site_health" class="block px-2 py-1.5 rounded hover:bg-slate-800">Site Health</a>
      <a href="/admin/moderator_permissions" class="block px-2 py-1.5 rounded hover:bg-slate-800">Mod Permissions</a>
      <a href="/admin/badges" class="block px-2 py-1.5 rounded hover:bg-slate-800">Badges</a>
    <?php endif; ?>
    <?php if ($admin['role'] === 'admin'): ?>
      <div class="mt-4 mb-1 px-2 text-xs uppercase tracking-wide text-slate-500">Monetization</div>
      <a href="/admin/orders" class="block px-2 py-1.5 rounded hover:bg-slate-800">Orders</a>
      <a href="/admin/payment_settings" class="block px-2 py-1.5 rounded hover:bg-slate-800">Payment Settings</a>
      <a href="/admin/currencies" class="block px-2 py-1.5 rounded hover:bg-slate-800">Currencies</a>
      <a href="/admin/social_login_settings" class="block px-2 py-1.5 rounded hover:bg-slate-800">Social Login</a>
      <a href="/admin/integrations" class="block px-2 py-1.5 rounded hover:bg-slate-800">Integrations</a>
      <a href="/admin/webhooks" class="block px-2 py-1.5 rounded hover:bg-slate-800">Webhooks</a>
      <a href="/admin/expert_payouts" class="block px-2 py-1.5 rounded hover:bg-slate-800">Expert Payouts</a>
      <a href="/admin/site_polls" class="block px-2 py-1.5 rounded hover:bg-slate-800">Community Polls</a>
      <a href="/admin/ad_slots" class="block px-2 py-1.5 rounded hover:bg-slate-800">Ad Slots</a>
      <a href="/admin/site_settings" class="block px-2 py-1.5 rounded hover:bg-slate-800">Branding</a>
      <a href="/admin/settings" class="block px-2 py-1.5 rounded hover:bg-slate-800">Settings</a>
    <?php endif; ?>
    <a href="/index" class="block px-2 py-1.5 rounded hover:bg-slate-800 mt-4 text-slate-400">&larr; Back to site</a>
  </nav>
</aside>
<main class="flex-1 p-4 md:p-6 min-w-0">
<?php if ($msg = flash_get('error')): ?>
  <div class="mb-4 rounded border border-red-300 bg-red-50 text-red-800 px-4 py-2"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('success')): ?>
  <div class="mb-4 rounded border border-green-300 bg-green-50 text-green-800 px-4 py-2"><?= e($msg) ?></div>
<?php endif; ?>
