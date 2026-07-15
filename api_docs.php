<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'API Documentation — ' . SITE_NAME;
$pageDescription = 'Public REST API reference for ' . SITE_NAME . '.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold mb-2">API documentation</h1>
  <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">
    All endpoints return JSON. Read endpoints are public; write endpoints require an API key from
    <a href="/api_keys" class="text-indigo-600 hover:underline">your API keys page</a>, sent as
    <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-xs">Authorization: Bearer tc_...</code>.
    Keys can be scoped to <strong>read-only</strong> or <strong>full access</strong> when generated — a read-only key
    is rejected with a 403 on any write endpoint (like <code class="bg-slate-100 dark:bg-slate-800 px-1 rounded text-xs">POST /api/v1/ask.php</code>),
    so it's safe to hand out to scripts that should never be able to post content.
  </p>

  <div class="space-y-6">
    <?php
    $endpoints = [
      ['GET', '/api/v1/questions.php?page=1&tag=php&category=technology', 'List recent questions, optionally filtered by tag or category.'],
      ['GET', '/api/v1/question.php?slug=your-question-slug', 'Fetch a single question with its answers.'],
      ['POST', '/api/v1/ask.php', 'Create a question. Requires API key. Body: title, body, category, tags (comma-separated).'],
      ['GET', '/api/v1/tags.php', 'List the top 100 tags by usage.'],
      ['GET', '/api/v1/categories.php', 'List active categories.'],
      ['GET', '/api/v1/users.php?username=someone', 'Fetch a public profile summary.'],
      ['GET', '/api/v1/search.php?q=keyword', 'Full-text search across questions.'],
      ['GET', '/api/v1/me.php', 'Confirm your API key is valid and see which account it belongs to.'],
    ];
    foreach ($endpoints as [$method, $path, $desc]): ?>
      <div class="card p-4">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-mono font-bold px-2 py-0.5 rounded <?= $method === 'GET' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?>"><?= $method ?></span>
          <code class="text-sm"><?= e($path) ?></code>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-400"><?= e($desc) ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <h2 class="text-lg font-semibold mt-8 mb-2">Rate limits</h2>
  <p class="text-sm text-slate-600 dark:text-slate-400">Write endpoints are limited to 20 requests/hour per API key. Read endpoints are unauthenticated and rate-limited by IP at the web server level.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
