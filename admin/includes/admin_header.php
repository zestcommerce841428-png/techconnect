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
<body class="bg-slate-50 text-slate-900 min-h-screen flex">
<aside class="w-56 bg-slate-900 text-white p-4 shrink-0">
  <div class="font-bold mb-6"><?= e(SITE_NAME) ?> Admin</div>
  <nav class="space-y-1 text-sm">
    <a href="/admin/dashboard.php" class="block px-2 py-1.5 rounded hover:bg-slate-800">Dashboard</a>
    <a href="/admin/users.php" class="block px-2 py-1.5 rounded hover:bg-slate-800">Users</a>
    <a href="/admin/moderation.php" class="block px-2 py-1.5 rounded hover:bg-slate-800">Moderation</a>
    <a href="/admin/jobs.php" class="block px-2 py-1.5 rounded hover:bg-slate-800">Jobs</a>
    <?php if ($admin['role'] === 'admin'): ?>
      <a href="/admin/settings.php" class="block px-2 py-1.5 rounded hover:bg-slate-800">Settings</a>
    <?php endif; ?>
    <a href="/index.php" class="block px-2 py-1.5 rounded hover:bg-slate-800 mt-4 text-slate-400">&larr; Back to site</a>
  </nav>
</aside>
<main class="flex-1 p-6">
<?php if ($msg = flash_get('error')): ?>
  <div class="mb-4 rounded border border-red-300 bg-red-50 text-red-800 px-4 py-2"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('success')): ?>
  <div class="mb-4 rounded border border-green-300 bg-green-50 text-green-800 px-4 py-2"><?= e($msg) ?></div>
<?php endif; ?>
