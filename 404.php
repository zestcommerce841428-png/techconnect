<?php
require_once __DIR__ . '/includes/db.php';

// Admin-configured redirects get first crack at any unmatched path.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
try {
    $stmt = db()->prepare('SELECT id, to_path, status_code FROM redirects WHERE from_path = ?');
    $stmt->execute([$requestPath]);
    $redirect = $stmt->fetch();
    if ($redirect) {
        db()->prepare('UPDATE redirects SET hits = hits + 1 WHERE id = ?')->execute([$redirect['id']]);
        header('Location: ' . $redirect['to_path'], true, (int) $redirect['status_code']);
        exit;
    }
} catch (Throwable $e) {
    // fall through to a normal 404 if the redirects table isn't available
}

require_once __DIR__ . '/includes/auth.php';
http_response_code(404);
$pageTitle = 'Page not found — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="text-center py-16">
  <div class="text-6xl font-bold text-slate-200 mb-4">404</div>
  <h1 class="text-xl font-semibold mb-2">Page not found</h1>
  <p class="text-slate-500 mb-6">The page you're looking for doesn't exist or was moved.</p>
  <div class="flex justify-center gap-3">
    <a href="/" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Go home</a>
    <a href="/search" class="bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded text-sm">Search</a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
