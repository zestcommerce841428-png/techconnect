<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$pageTitle = $pageTitle ?? SITE_NAME . ' — Ask, answer, connect on tech';
$pageDescription = $pageDescription ?? 'A worldwide community where people ask tech questions and get real answers from nearby and global experts.';
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
<meta name="theme-color" content="#0f172a">
<link rel="stylesheet" href="/assets/css/tailwind.min.css">
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col">
<header class="bg-slate-900 text-white">
  <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
    <a href="/index.php" class="font-bold text-lg"><?= e(SITE_NAME) ?></a>
    <nav class="flex items-center gap-4 text-sm">
      <a href="/questions.php" class="hover:underline">Browse</a>
      <a href="/jobs.php" class="hover:underline">Jobs</a>
      <?php if ($user): ?>
        <a href="/ask.php" class="bg-indigo-500 hover:bg-indigo-400 px-3 py-1.5 rounded">Ask</a>
        <a href="/inbox.php" class="hover:underline">Inbox</a>
        <a href="/profile.php" class="hover:underline"><?= e($user['username']) ?></a>
        <a href="/logout.php" class="hover:underline">Logout</a>
      <?php else: ?>
        <a href="/login.php" class="hover:underline">Login</a>
        <a href="/register.php" class="bg-indigo-500 hover:bg-indigo-400 px-3 py-1.5 rounded">Join</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="flex-1 max-w-5xl mx-auto px-4 py-6 w-full">
<?php if ($msg = flash_get('error')): ?>
  <div class="mb-4 rounded border border-red-300 bg-red-50 text-red-800 px-4 py-2"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('success')): ?>
  <div class="mb-4 rounded border border-green-300 bg-green-50 text-green-800 px-4 py-2"><?= e($msg) ?></div>
<?php endif; ?>
