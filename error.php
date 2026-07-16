<?php
/**
 * Single professional error page covering every status code in
 * includes/error_catalog.php. Deliberately standalone — no db.php/auth.php —
 * since 5xx pages (and 4xx ones thrown while the DB is unreachable) must
 * still render. Apache sets REDIRECT_STATUS automatically for any path
 * reached via an ErrorDocument directive; ?code= is a manual-testing fallback.
 */
require_once __DIR__ . '/includes/error_catalog.php';

$code = (int) ($_SERVER['REDIRECT_STATUS'] ?? $_GET['code'] ?? 500);
if ($code < 400 || $code > 599) {
    $code = 500;
}
http_response_code($code);

$meta = error_meta($code);
$siteName = defined('SITE_NAME') ? SITE_NAME : 'PuchoNow';
$requestId = substr(bin2hex(random_bytes(4)), 0, 8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($meta['title']) ?> (<?= $code ?>) — <?= htmlspecialchars($siteName) ?></title>
<meta name="robots" content="noindex">
<style>
  :root { color-scheme: light dark; }
  * { box-sizing: border-box; }
  body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: #f8fafc; color: #0f172a;
    display: flex; align-items: center; justify-content: center;
    min-height: 100vh; margin: 0; padding: 1.5rem;
  }
  @media (prefers-color-scheme: dark) {
    body { background: #0f172a; color: #f1f5f9; }
    .card { background: #1e293b !important; border-color: #334155 !important; }
    .btn-secondary { background: #334155 !important; color: #f1f5f9 !important; }
    .meta { color: #94a3b8 !important; }
  }
  .card {
    max-width: 30rem; width: 100%; text-align: center;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem;
    padding: 2.5rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.06);
  }
  .icon { font-size: 2.75rem; line-height: 1; margin-bottom: .5rem; }
  .code { font-size: 3rem; font-weight: 800; color: #cbd5e1; letter-spacing: -.02em; }
  h1 { font-size: 1.25rem; font-weight: 700; margin: .25rem 0 .5rem; }
  p.msg { color: #64748b; margin: 0 0 1.5rem; line-height: 1.5; }
  .actions { display: flex; gap: .6rem; justify-content: center; flex-wrap: wrap; }
  a.btn {
    display: inline-block; text-decoration: none; font-size: .875rem; font-weight: 500;
    padding: .55rem 1.1rem; border-radius: .5rem;
  }
  .btn-primary { background: #4f46e5; color: #fff; }
  .btn-primary:hover { background: #4338ca; }
  .btn-secondary { background: #f1f5f9; color: #0f172a; }
  .btn-secondary:hover { background: #e2e8f0; }
  .meta { margin-top: 1.5rem; font-size: .75rem; color: #94a3b8; }
</style>
</head>
<body>
  <div class="card">
    <div class="icon"><?= $meta['icon'] ?></div>
    <div class="code"><?= $code ?></div>
    <h1><?= htmlspecialchars($meta['title']) ?></h1>
    <p class="msg"><?= htmlspecialchars($meta['message']) ?></p>
    <div class="actions">
      <a href="/" class="btn btn-primary">Go home</a>
      <?php if ($code === 401 || $code === 407 || $code === 511): ?>
        <a href="/login" class="btn btn-secondary">Sign in</a>
      <?php elseif ($code >= 500 || $code === 429 || $code === 503): ?>
        <a href="/status" class="btn btn-secondary">System status</a>
      <?php else: ?>
        <a href="/search" class="btn btn-secondary">Search</a>
      <?php endif; ?>
      <a href="/contact" class="btn btn-secondary">Contact support</a>
    </div>
    <div class="meta">Error reference: <?= $requestId ?></div>
  </div>
</body>
</html>
