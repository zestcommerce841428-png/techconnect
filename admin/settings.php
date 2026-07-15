<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Settings — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}
?>
<h1 class="text-2xl font-bold mb-4">Site settings</h1>
<div class="bg-white border rounded-lg p-6 max-w-lg">
  <p class="text-sm text-slate-600 mb-4">
    Core site settings (site name, SMTP, feature flags) currently live in
    <code class="bg-slate-100 px-1 rounded">config.php</code> on the server. Edit that file
    directly and re-deploy to change them. A database-backed settings UI can be added
    once the community has grown enough to need runtime configuration changes.
  </p>
  <dl class="text-sm space-y-1">
    <div><dt class="inline font-medium">Site name:</dt> <dd class="inline"><?= e(SITE_NAME) ?></dd></div>
    <div><dt class="inline font-medium">Site URL:</dt> <dd class="inline"><?= e(SITE_URL) ?></dd></div>
    <div><dt class="inline font-medium">SMTP configured:</dt> <dd class="inline"><?= SMTP_HOST ? 'Yes' : 'No' ?></dd></div>
  </dl>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
